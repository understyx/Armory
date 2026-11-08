<?php

namespace App\Tests\MessageHandler;

use App\Entity\CharacterClaimChallenge;
use App\Entity\User;
use App\Enum\ItemTypes;
use App\Message\ScanCharacterClaimMessage;
use App\MessageHandler\ScanCharacterClaimMessageHandler;
use App\Repository\CharacterClaimChallengeRepository;
use App\Repository\CharacterClaimRepository;
use App\Service\ArmoryScraperService;
use App\Service\CharacterClaimSlotPicker;
use App\Service\CharacterRefreshTracker;
use App\Service\CharacterSnapshotUpdater;
use App\Service\CharacterUpdateThrottle;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

final class ScanCharacterClaimMessageHandlerTest extends TestCase
{
    public function testNullFetchLeavesStatePendingAndRedispatchesWithDelay(): void
    {
        $challenge = $this->challenge(CharacterClaimChallenge::SCANNING_BASELINE);
        $scraper = $this->createMock(ArmoryScraperService::class);
        $scraper->expects(self::once())->method('fetchArmoryHtml')->with('Testchar', 'Icecrown', 'summary')->willReturn(null);
        $scraper->expects(self::never())->method('checkCharacterExists');
        $scraper->expects(self::never())->method('extractEquippedItemsData');
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')->with(
            self::callback(static fn (object $message): bool => $message instanceof ScanCharacterClaimMessage && $message->challengeId === 5 && $message->phase === ScanCharacterClaimMessage::BASELINE),
            self::callback(static function (array $stamps): bool {
                foreach ($stamps as $stamp) {
                    if ($stamp instanceof DelayStamp) {
                        return $stamp->getDelay() === 30_000;
                    }
                }

                return false;
            }),
        )->willReturn(new Envelope(new \stdClass()));
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        $this->handler($challenge, $scraper, $entityManager, $bus)(new ScanCharacterClaimMessage(5, ScanCharacterClaimMessage::BASELINE));

        self::assertSame(CharacterClaimChallenge::SCANNING_BASELINE, $challenge->getState());
        self::assertNull($challenge->getScanLeaseUntil());
    }

    public function testNotFoundIsRecordedAsARealOutcome(): void
    {
        $challenge = $this->challenge(CharacterClaimChallenge::SCANNING_BASELINE);
        $scraper = $this->scraper('<not-found>', false);
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        $this->handler($challenge, $scraper, $entityManager, $bus)(new ScanCharacterClaimMessage(5, ScanCharacterClaimMessage::BASELINE));

        self::assertSame(CharacterClaimChallenge::NOT_FOUND, $challenge->getState());
    }

    public function testBaselineStoresTwoSelectedItems(): void
    {
        $challenge = $this->challenge(CharacterClaimChallenge::SCANNING_BASELINE);
        $scraper = $this->scraper('<summary>', true, [
            ['id' => 100, 'name' => 'Helm'],
            ['id' => 200, 'name' => 'Necklace'],
        ]);
        $bus = $this->createMock(MessageBusInterface::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        $this->handler($challenge, $scraper, $entityManager, $bus)(new ScanCharacterClaimMessage(5, ScanCharacterClaimMessage::BASELINE));

        self::assertSame(CharacterClaimChallenge::AWAITING_REMOVAL, $challenge->getState());
        self::assertCount(2, $challenge->getRequiredSlots());
        self::assertEqualsCanonicalizing(['head', 'neck'], array_column($challenge->getRequiredSlots(), 'slot'));
    }

    public function testBaselineWithNoItemsRequestsARealRetryFromTheUser(): void
    {
        $challenge = $this->challenge(CharacterClaimChallenge::SCANNING_BASELINE);
        $scraper = $this->scraper('<summary>', true, []);
        $bus = $this->createMock(MessageBusInterface::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);

        $this->handler($challenge, $scraper, $entityManager, $bus)(new ScanCharacterClaimMessage(5, ScanCharacterClaimMessage::BASELINE));

        self::assertSame(CharacterClaimChallenge::NO_ITEMS, $challenge->getState());
    }

    public function testVerifyPassTransfersExistingCharacterOwnership(): void
    {
        $owner = new User();
        $owner->setDiscordId('new-owner');
        $challenge = $this->challenge(CharacterClaimChallenge::SCANNING_VERIFY, $owner);
        $challenge->setRequiredSlots([['slot' => 'head', 'id' => 100, 'name' => 'Head: Helm', 'icon' => null]]);
        $claimRepository = $this->createMock(CharacterClaimRepository::class);
        $claimRepository->expects(self::once())
            ->method('transferOwnership')
            ->with($owner, 'Testchar', 'Icecrown', self::isInstanceOf(\DateTimeImmutable::class));
        $scraper = $this->scraper('<summary>', true, []);
        $bus = $this->createMock(MessageBusInterface::class);
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects(self::once())->method('commit');
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('getConnection')->willReturn($connection);
        $entityManager->expects(self::once())->method('flush');

        $this->handler($challenge, $scraper, $entityManager, $bus, $claimRepository)(new ScanCharacterClaimMessage(5, ScanCharacterClaimMessage::VERIFY));

        self::assertSame(CharacterClaimChallenge::VERIFIED, $challenge->getState());
    }

    public function testVerifyFailureKeepsOnlyItemsStillEquipped(): void
    {
        $challenge = $this->challenge(CharacterClaimChallenge::SCANNING_VERIFY);
        $challenge->setRequiredSlots([
            ['slot' => 'head', 'id' => 100, 'name' => 'Head: Helm', 'icon' => null],
            ['slot' => 'neck', 'id' => 200, 'name' => 'Neck: Necklace', 'icon' => null],
        ]);
        $scraper = $this->scraper('<summary>', true, [
            ['id' => 200, 'name' => 'Necklace'],
        ]);
        $claimRepository = $this->createMock(CharacterClaimRepository::class);
        $claimRepository->expects(self::never())->method('findOneBy');
        $bus = $this->createMock(MessageBusInterface::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);

        $this->handler($challenge, $scraper, $entityManager, $bus, $claimRepository)(new ScanCharacterClaimMessage(5, ScanCharacterClaimMessage::VERIFY));

        self::assertSame(CharacterClaimChallenge::AWAITING_REMOVAL, $challenge->getState());
        self::assertSame([['slot' => 'neck', 'id' => 200, 'name' => 'Neck: Necklace', 'icon' => null]], $challenge->getRequiredSlots());
    }

    public function testExpiredChallengeStopsWithoutFetchingOrRedispatching(): void
    {
        $repository = $this->createMock(CharacterClaimChallengeRepository::class);
        $repository->expects(self::once())->method('expireIfNeeded');
        $repository->expects(self::once())->method('tryAcquireScan')->willReturn(false);
        $this->assertScanIgnored($repository);
    }

    public function testDuplicateMessageIsIgnoredWhileAnotherScanHoldsTheLease(): void
    {
        $repository = $this->createMock(CharacterClaimChallengeRepository::class);
        $repository->expects(self::exactly(2))->method('expireIfNeeded');
        $repository->expects(self::exactly(2))->method('tryAcquireScan')->willReturn(false);
        $scraper = $this->createMock(ArmoryScraperService::class);
        $scraper->expects(self::never())->method('fetchArmoryHtml');
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('flush');
        $handler = new ScanCharacterClaimMessageHandler(
            $repository,
            $this->createMock(CharacterClaimRepository::class),
            $entityManager,
            $scraper,
            $this->slotPicker(),
            $bus,
        );
        $message = new ScanCharacterClaimMessage(5, ScanCharacterClaimMessage::VERIFY);

        $handler($message);
        $handler($message);
    }

    public function testStaleMessageForReplacedChallengeDoesNotFetchWarmane(): void
    {
        $repository = $this->createMock(CharacterClaimChallengeRepository::class);
        $repository->expects(self::once())->method('expireIfNeeded');
        $repository->expects(self::once())->method('tryAcquireScan')->willReturn(false);
        $this->assertScanIgnored($repository);
    }

    public function testScannerHasNoCharacterRefreshDependencies(): void
    {
        $constructor = (new \ReflectionClass(ScanCharacterClaimMessageHandler::class))->getConstructor();
        $types = array_map(static fn (\ReflectionParameter $parameter): ?string => $parameter->getType()?->__toString(), $constructor->getParameters());

        self::assertNotContains(CharacterSnapshotUpdater::class, $types);
        self::assertNotContains(CharacterUpdateThrottle::class, $types);
        self::assertNotContains(CharacterRefreshTracker::class, $types);
    }

    private function challenge(string $state, ?User $user = null): CharacterClaimChallenge
    {
        $challenge = new CharacterClaimChallenge($user ?? (new User())->setDiscordId('123'), 'Testchar', 'Icecrown', new \DateTimeImmutable());
        $challenge->setState($state);
        $id = new \ReflectionProperty($challenge, 'id');
        $id->setValue($challenge, 5);

        return $challenge;
    }

    /** @param list<array<string, mixed>> $items */
    private function scraper(string $html, bool $exists, array $items = []): ArmoryScraperService
    {
        $scraper = $this->createMock(ArmoryScraperService::class);
        $scraper->method('fetchArmoryHtml')->willReturn($html);
        $scraper->method('checkCharacterExists')->willReturn($exists);
        $scraper->method('extractEquippedItemsData')->willReturn($items);

        return $scraper;
    }

    private function handler(
        CharacterClaimChallenge $challenge,
        ArmoryScraperService $scraper,
        EntityManagerInterface $entityManager,
        MessageBusInterface $bus,
        ?CharacterClaimRepository $claimRepository = null,
    ): ScanCharacterClaimMessageHandler {
        $repository = $this->createMock(CharacterClaimChallengeRepository::class);
        $repository->method('expireIfNeeded');
        $repository->method('tryAcquireScan')->willReturn(true);
        $repository->method('find')->with(5)->willReturn($challenge);

        return new ScanCharacterClaimMessageHandler(
            $repository,
            $claimRepository ?? $this->createMock(CharacterClaimRepository::class),
            $entityManager,
            $scraper,
            $this->slotPicker(),
            $bus,
        );
    }

    private function assertScanIgnored(CharacterClaimChallengeRepository $repository): void
    {
        $scraper = $this->createMock(ArmoryScraperService::class);
        $scraper->expects(self::never())->method('fetchArmoryHtml');
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('flush');
        $handler = new ScanCharacterClaimMessageHandler(
            $repository,
            $this->createMock(CharacterClaimRepository::class),
            $entityManager,
            $scraper,
            $this->slotPicker(),
            $bus,
        );

        $handler(new ScanCharacterClaimMessage(5, ScanCharacterClaimMessage::VERIFY));
    }

    private function slotPicker(): CharacterClaimSlotPicker
    {
        $itemDatabase = $this->createMock(\App\Service\ItemDatabaseService::class);
        $itemDatabase->method('getItemsBulk')->willReturnCallback(static fn (array $ids): array => array_intersect_key([
            100 => ['type' => ItemTypes::HEAD->value],
            200 => ['type' => ItemTypes::NECK->value],
        ], array_flip($ids)));

        return new CharacterClaimSlotPicker($itemDatabase);
    }
}
