<?php

namespace App\Tests\Controller;

use App\Controller\CharacterApiController;
use App\Entity\CharacterSnapshot;
use App\Entity\UwuLogRank;
use App\Message\RefreshCharacterSnapshotMessage;
use App\Repository\CharacterSnapshotRepository;
use App\Repository\UwuLogRankRepository;
use App\Service\ArmoryScraperService;
use App\Service\CharacterApiFormatter;
use App\Service\CharacterRefreshTracker;
use App\Service\CharacterStatCalculator;
use App\Service\CharacterUpdateThrottle;
use App\Service\CharacterUpdateThrottleDecision;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DeduplicateStamp;

class CharacterApiControllerTest extends TestCase
{
    public function testCharacterReturnsCachedSnapshotWithoutRequestingAnUpdate(): void
    {
        $snapshot = $this->createSnapshot();
        $repository = $this->createMock(CharacterSnapshotRepository::class);
        $repository->expects(self::once())
            ->method('findApiByNameAndRealm')
            ->with('Understyx', 'Icecrown')
            ->willReturn($snapshot);
        $throttle = $this->createMock(CharacterUpdateThrottle::class);
        $throttle->expects(self::never())->method('claim');
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $response = (new CharacterApiController(
            $repository,
            new CharacterApiFormatter(),
            $throttle,
            $bus,
        ))->character('Understyx', 'Icecrown');
        $payload = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('Understyx', $payload['character']['name']);
        self::assertStringContainsString('public', (string) $response->headers->get('Cache-Control'));
        self::assertStringContainsString('max-age=0', (string) $response->headers->get('Cache-Control'));
        self::assertStringContainsString('s-maxage=60', (string) $response->headers->get('Cache-Control'));
        self::assertNotNull($response->headers->get('ETag'));
    }

    public function testCharacterEndpointReturns304AndChangesValidatorWithSnapshotOutput(): void
    {
        $snapshot = $this->createSnapshot();
        $repository = $this->createMock(CharacterSnapshotRepository::class);
        $repository->method('findApiByNameAndRealm')->willReturn($snapshot);
        $controller = $this->createController($repository);

        $first = $controller->character('Understyx', 'Icecrown');
        $etag = $first->headers->get('ETag');
        self::assertNotEmpty($etag);

        $notModifiedRequest = new Request(server: ['HTTP_IF_NONE_MATCH' => $etag]);
        $notModified = $controller->character('Understyx', 'Icecrown', $notModifiedRequest);
        self::assertSame(Response::HTTP_NOT_MODIFIED, $notModified->getStatusCode());
        self::assertSame('', $notModified->getContent());

        $snapshot->setGearScore(6100);
        $changed = $controller->character('Understyx', 'Icecrown', $notModifiedRequest);
        self::assertSame(Response::HTTP_OK, $changed->getStatusCode());
        self::assertNotSame($etag, $changed->headers->get('ETag'));
    }

    public function testCharacterReturnsNotFoundWhenThereIsNoSnapshot(): void
    {
        $repository = $this->createMock(CharacterSnapshotRepository::class);
        $repository->method('findApiByNameAndRealm')->willReturn(null);

        $response = $this->createController($repository)->character('Missing', 'Icecrown');
        $payload = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        self::assertSame('not_found', $payload['status']);
    }

    public function testCharacterIncludesBestCachedUwuRankAndItsSpecialization(): void
    {
        $snapshot = $this->createSnapshot();
        $repository = $this->createMock(CharacterSnapshotRepository::class);
        $repository->method('findApiByNameAndRealm')->willReturn($snapshot);
        $rank = (new UwuLogRank())
            ->setName('Understyx')
            ->setRealm('Icecrown')
            ->setSpec('3')
            ->setOverallRank(42)
            ->setPayload(['overallPoints' => 96.5]);
        $rankRepository = $this->createMock(UwuLogRankRepository::class);
        $rankRepository->expects(self::once())
            ->method('findBest')
            ->with('Understyx', 'Icecrown')
            ->willReturn($rank);

        $response = (new CharacterApiController(
            $repository,
            new CharacterApiFormatter(),
            $this->createMock(CharacterUpdateThrottle::class),
            $this->createMock(MessageBusInterface::class),
            $rankRepository,
        ))->character('Understyx', 'Icecrown');
        $payload = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(42, $payload['uwuLogs']['overallRank']);
        self::assertSame(96.5, $payload['uwuLogs']['overallPoints']);
        self::assertSame('3', $payload['uwuLogs']['specId']);
        self::assertSame('Unholy', $payload['uwuLogs']['specName']);
    }

    public function testDedicatedStatsEndpointReturnsOnlyConsumerFacingValues(): void
    {
        $repository = $this->createMock(CharacterSnapshotRepository::class);
        $repository->method('findStatsByNameAndRealm')->willReturn($this->createSnapshot());
        $controller = new CharacterApiController(
            $repository,
            new CharacterApiFormatter(new CharacterStatCalculator()),
            $this->createMock(CharacterUpdateThrottle::class),
            $this->createMock(MessageBusInterface::class),
        );

        $response = $controller->stats('Understyx', 'Icecrown');
        $payload = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertTrue($payload['stats']['spec1']['available']);
        self::assertSame(175, $payload['stats']['spec1']['primary']['strength']);
        self::assertSame(0, $payload['stats']['spec1']['attackPower']);
        self::assertSame(0, $payload['stats']['spec1']['spellPower']);
        self::assertArrayHasKey('melee_crit_rating', $payload['stats']['spec1']['ratings']);
        self::assertSame(
            ['rating', 'percent'],
            array_keys($payload['stats']['spec1']['ratings']['melee_crit_rating']),
        );
        self::assertArrayNotHasKey('gearBreakdown', $payload['stats']['spec1']);
        self::assertArrayNotHasKey('talentBreakdown', $payload['stats']['spec1']);
    }

    public function testDedicatedAchievementsEndpointReturnsCachedProgression(): void
    {
        $snapshot = $this->createSnapshot()->setRaidAchievements([
            '15041' => ['4583' => '11/02/2020'],
        ]);
        $repository = $this->createMock(CharacterSnapshotRepository::class);
        $repository->method('findAchievementsByNameAndRealm')->willReturn($snapshot);
        $controller = new CharacterApiController(
            $repository,
            new CharacterApiFormatter(null, new ArmoryScraperService()),
            $this->createMock(CharacterUpdateThrottle::class),
            $this->createMock(MessageBusInterface::class),
        );

        $response = $controller->achievements('Understyx', 'Icecrown');
        $payload = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertTrue($payload['available']);
        self::assertSame(1, $payload['earnedCount']);
        self::assertSame('ICC + RS', $payload['groups'][0]['title']);
    }

    public function testRequestUpdateQueuesARefresh(): void
    {
        $nextAllowedAt = new \DateTimeImmutable('+5 minutes');
        $throttle = $this->createMock(CharacterUpdateThrottle::class);
        $throttle->expects(self::once())
            ->method('claim')
            ->with('Understyx', 'Icecrown')
            ->willReturn(new CharacterUpdateThrottleDecision(true, $nextAllowedAt));
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static fn(object $message): bool => $message instanceof RefreshCharacterSnapshotMessage
                && $message->characterName === 'Understyx'
                && $message->realmName === 'Icecrown'), self::callback(static fn (array $stamps): bool => $stamps[0] instanceof DeduplicateStamp))
            ->willReturnCallback(static fn(object $message, array $stamps): Envelope => new Envelope($message, $stamps));

        $response = $this->createController(throttle: $throttle, bus: $bus)
            ->requestUpdate('Understyx', 'Icecrown');
        $payload = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(Response::HTTP_ACCEPTED, $response->getStatusCode());
        self::assertSame('queued', $payload['status']);
        self::assertSame($nextAllowedAt->format(\DateTimeInterface::ATOM), $payload['nextUpdateAllowedAt']);
    }

    public function testRequestUpdateRecordsQueuedBeforeDispatch(): void
    {
        $queued = false;
        $tracker = $this->createMock(CharacterRefreshTracker::class);
        $tracker->expects(self::once())->method('markQueued')->willReturnCallback(static function () use (&$queued): void {
            $queued = true;
        });
        $throttle = $this->createMock(CharacterUpdateThrottle::class);
        $throttle->method('claim')->willReturn(new CharacterUpdateThrottleDecision(true, new \DateTimeImmutable('+5 minutes')));
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')->willReturnCallback(
            static function (object $message, array $stamps) use (&$queued): Envelope {
                self::assertTrue($queued);

                return new Envelope($message, $stamps);
            },
        );
        $controller = new CharacterApiController(
            $this->createMock(CharacterSnapshotRepository::class),
            new CharacterApiFormatter(),
            $throttle,
            $bus,
            refreshTracker: $tracker,
        );

        $response = $controller->requestUpdate('Understyx', 'Icecrown');

        self::assertSame(Response::HTTP_ACCEPTED, $response->getStatusCode());
    }

    public function testRequestUpdateReturns429DuringCooldown(): void
    {
        $nextAllowedAt = new \DateTimeImmutable('+2 minutes');
        $throttle = $this->createMock(CharacterUpdateThrottle::class);
        $throttle->method('claim')
            ->willReturn(new CharacterUpdateThrottleDecision(false, $nextAllowedAt));
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $response = $this->createController(throttle: $throttle, bus: $bus)
            ->requestUpdate('Understyx', 'Icecrown');
        $payload = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(Response::HTTP_TOO_MANY_REQUESTS, $response->getStatusCode());
        self::assertSame('rate_limited', $payload['status']);
        self::assertGreaterThanOrEqual(118, (int) $response->headers->get('Retry-After'));
        self::assertLessThanOrEqual(120, (int) $response->headers->get('Retry-After'));
    }

    public function testInvalidIdentifiersAreRejectedBeforeDatabaseAccess(): void
    {
        $repository = $this->createMock(CharacterSnapshotRepository::class);
        $repository->expects(self::never())->method('findApiByNameAndRealm');

        $response = $this->createController($repository)->character('bad name', 'Icecrown');

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    private function createController(
        ?CharacterSnapshotRepository $repository = null,
        ?CharacterUpdateThrottle $throttle = null,
        ?MessageBusInterface $bus = null,
    ): CharacterApiController {
        return new CharacterApiController(
            $repository ?? $this->createMock(CharacterSnapshotRepository::class),
            new CharacterApiFormatter(),
            $throttle ?? $this->createMock(CharacterUpdateThrottle::class),
            $bus ?? $this->createMock(MessageBusInterface::class),
        );
    }

    private function createSnapshot(): CharacterSnapshot
    {
        return (new CharacterSnapshot())
            ->setName('Understyx')
            ->setRealm('Icecrown')
            ->setLevel(80)
            ->setRace('Human')
            ->setClass('Death Knight')
            ->setGearScore(6000)
            ->setAvgIlvl(264.5)
            ->setProfessions([])
            ->setSpecializations([])
            ->setEquippedItems([])
            ->setTalentStrings([])
            ->setScrapedAt(new \DateTimeImmutable('2026-08-23T10:00:00+00:00'));
    }
}
