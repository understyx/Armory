<?php

namespace App\MessageHandler;

use App\Entity\CharacterClaimChallenge;
use App\Message\ScanCharacterClaimMessage;
use App\Repository\CharacterClaimChallengeRepository;
use App\Repository\CharacterClaimRepository;
use App\Service\ArmoryScraperService;
use App\Service\CharacterClaimSlotPicker;
use App\Service\DeduplicatingMessageDispatcher;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Doctrine\ORM\EntityManagerInterface;

#[AsMessageHandler]
final readonly class ScanCharacterClaimMessageHandler
{
    public function __construct(
        private CharacterClaimChallengeRepository $challengeRepository,
        private CharacterClaimRepository $claimRepository,
        private EntityManagerInterface $entityManager,
        private ArmoryScraperService $armoryScraperService,
        private CharacterClaimSlotPicker $slotPicker,
        private MessageBusInterface $messageBus,
    ) {
    }

    public function __invoke(ScanCharacterClaimMessage $message): void
    {
        if (!in_array($message->phase, [ScanCharacterClaimMessage::BASELINE, ScanCharacterClaimMessage::VERIFY], true)) {
            return;
        }

        $now = new \DateTimeImmutable();
        $this->challengeRepository->expireIfNeeded($message->challengeId, $now);
        if (!$this->challengeRepository->tryAcquireScan($message->challengeId, $message->phase, $now)) {
            return;
        }

        $challenge = $this->challengeRepository->find($message->challengeId);
        if ($challenge === null) {
            return;
        }

        $html = $this->armoryScraperService->fetchArmoryHtml(
            (string) $challenge->getName(),
            (string) $challenge->getRealm(),
            'summary',
        );
        if ($challenge->getExpiresAt() !== null && $challenge->getExpiresAt() <= new \DateTimeImmutable()) {
            $challenge->setState(CharacterClaimChallenge::EXPIRED);
            $challenge->setScanLeaseUntil(null);
            $this->entityManager->flush();

            return;
        }

        if ($html === null || trim($html) === '') {
            $challenge->setScanLeaseUntil(null);
            $this->entityManager->flush();
            DeduplicatingMessageDispatcher::dispatch(
                $this->messageBus,
                $message,
                [new DelayStamp(30_000)],
            );

            return;
        }

        if (!$this->armoryScraperService->checkCharacterExists($html)) {
            $challenge->setState(CharacterClaimChallenge::NOT_FOUND);
            $challenge->setScanLeaseUntil(null);
            $this->entityManager->flush();

            return;
        }

        $equipped = $this->armoryScraperService->extractEquippedItemsData($html);
        if ($message->phase === ScanCharacterClaimMessage::BASELINE) {
            $requiredSlots = $this->slotPicker->pick($equipped);
            $challenge->setScanLeaseUntil(null);
            if ($requiredSlots === []) {
                $challenge->setState(CharacterClaimChallenge::NO_ITEMS);
            } else {
                $challenge->setRequiredSlots($requiredSlots);
                $challenge->setState(CharacterClaimChallenge::AWAITING_REMOVAL);
            }
            $this->entityManager->flush();

            return;
        }

        $currentlyEquipped = $this->slotPicker->map($equipped);
        $equippedKeys = [];
        foreach ($currentlyEquipped as $item) {
            $equippedKeys[$item['slot'].':'.$item['id']] = true;
        }
        $remaining = array_values(array_filter(
            $challenge->getRequiredSlots(),
            static fn (array $required): bool => isset($equippedKeys[$required['slot'].':'.$required['id']]),
        ));
        $challenge->setScanLeaseUntil(null);
        if ($remaining === []) {
            $owner = $challenge->getUser() ?? throw new \LogicException('A claim challenge must have an owner.');
            $connection = $this->entityManager->getConnection();
            $connection->beginTransaction();
            try {
                $this->claimRepository->transferOwnership(
                    $owner,
                    (string) $challenge->getName(),
                    (string) $challenge->getRealm(),
                    new \DateTimeImmutable(),
                );
                $challenge->setState(CharacterClaimChallenge::VERIFIED);
                $this->entityManager->flush();
                $connection->commit();
            } catch (\Throwable $exception) {
                if ($connection->isTransactionActive()) {
                    $connection->rollBack();
                }

                throw $exception;
            }

            return;
        } else {
            $challenge->setRequiredSlots($remaining);
            $challenge->setState(CharacterClaimChallenge::AWAITING_REMOVAL);
        }

        $this->entityManager->flush();
    }
}
