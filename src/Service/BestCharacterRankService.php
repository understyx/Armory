<?php

namespace App\Service;

use App\Entity\CharacterClaim;
use App\Entity\UwuLogRank;
use App\Entity\User;
use App\Repository\CharacterClaimRepository;
use App\Repository\UwuLogRankRepository;
use Doctrine\ORM\EntityManagerInterface;

readonly class BestCharacterRankService
{
    public function __construct(
        private CharacterClaimRepository $claimRepository,
        private UwuLogRankRepository $rankRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function recompute(User $user): void
    {
        $bestClaim = null;
        $bestRank = null;

        foreach ($this->claimRepository->findForUser($user) as $claim) {
            $rank = $this->rankRepository->findBest((string) $claim->getName(), (string) $claim->getRealm());
            if ($rank !== null && $rank->isBetterThan($bestRank)) {
                $bestClaim = $claim;
                $bestRank = $rank;
            }
        }

        $user->setBestCharacter(
            $bestClaim?->getName(),
            $bestClaim?->getRealm(),
            $bestRank?->getSpecName($this->rankClassName($bestRank)),
            $bestRank?->getOverallPoints(),
            $bestRank?->getOverallRank(),
        );
        $this->entityManager->flush();
    }

    public function refreshForCharacter(string $name, string $realm): void
    {
        $ownerId = $this->claimRepository->findOwnerIdByCharacter($name, $realm);
        if ($ownerId === null) {
            return;
        }

        $owner = $this->entityManager->find(User::class, $ownerId);
        if ($owner instanceof User) {
            $this->recompute($owner);
        }
    }

    public function transferClaim(User $newOwner, string $name, string $realm, \DateTimeImmutable $verifiedAt): void
    {
        $oldOwnerId = $this->claimRepository->findOwnerIdByCharacter($name, $realm);
        $this->claimRepository->transferOwnership($newOwner, $name, $realm, $verifiedAt);

        if ($oldOwnerId !== null && $oldOwnerId !== $newOwner->getId()) {
            $oldOwner = $this->entityManager->find(User::class, $oldOwnerId);
            if ($oldOwner instanceof User) {
                $this->recompute($oldOwner);
            }
        }

        $this->recompute($newOwner);
    }

    public function unlinkClaim(CharacterClaim $claim): void
    {
        $user = $claim->getUser() ?? throw new \LogicException('A character claim must have an owner.');
        $this->entityManager->remove($claim);
        $this->entityManager->flush();
        $this->recompute($user);
    }

    private function rankClassName(?UwuLogRank $rank): ?string
    {
        return match ((int) ($rank?->getPayload()['classId'] ?? 0)) {
            1 => 'Warrior', 2 => 'Paladin', 3 => 'Hunter', 4 => 'Rogue', 5 => 'Priest',
            6 => 'Death Knight', 7 => 'Shaman', 8 => 'Mage', 9 => 'Warlock', 11 => 'Druid',
            default => null,
        };
    }
}
