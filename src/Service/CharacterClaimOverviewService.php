<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\CharacterClaimRepository;
use App\Repository\CharacterSnapshotRepository;
use App\Repository\UwuLogRankRepository;

class CharacterClaimOverviewService
{
    public function __construct(
        private readonly CharacterClaimRepository $claimRepository,
        private readonly CharacterSnapshotRepository $snapshotRepository,
        private readonly UwuLogRankRepository $rankRepository,
    ) {
    }

    /** @param list<User> $users @return array<int, list<array{claim: object, snapshot: ?array, rank: ?object}>> */
    public function forUsers(array $users): array
    {
        $userIds = [];
        $overview = [];
        foreach ($users as $user) {
            $id = $user->getId();
            if ($id === null) {
                continue;
            }

            $userIds[$id] = $id;
            $overview[$id] = [];
        }
        if ($userIds === []) {
            return $overview;
        }

        $claimsByUser = [];
        $claims = $this->claimRepository->findForUserIds(array_values($userIds));
        $characters = [];
        foreach ($claims as $claim) {
            $userId = $claim->getUser()?->getId();
            if ($userId === null) {
                continue;
            }

            $claimsByUser[$userId][] = $claim;
            $characters[] = [
                'name' => (string) $claim->getName(),
                'realm' => (string) $claim->getRealm(),
            ];
        }

        $snapshots = $this->snapshotRepository->findDetailsForCharacters($characters);
        $ranks = $this->rankRepository->findBestForCharactersAndRealms($characters);
        foreach ($claimsByUser as $userId => $userClaims) {
            foreach ($userClaims as $claim) {
                $key = self::characterKey((string) $claim->getName(), (string) $claim->getRealm());
                $overview[$userId][] = [
                    'claim' => $claim,
                    'snapshot' => $snapshots[$key] ?? null,
                    'rank' => $ranks[$key] ?? null,
                ];
            }
        }

        return $overview;
    }

    /** @param list<array{claim: object, snapshot: ?array, rank: ?object}> $characters
     *  @return array{claim: object, snapshot: ?array, rank: object}|null
     */
    public function bestRankedCharacter(array $characters): ?array
    {
        $best = null;
        foreach ($characters as $character) {
            $rank = $character['rank'];
            if ($rank === null) {
                continue;
            }

            if ($best === null || $rank->isBetterThan($best['rank'])) {
                $best = $character;
            }
        }

        return $best;
    }

    /** @param list<array{claim: object, snapshot: ?array, rank: ?object}> $characters */
    public function canComment(array $characters): bool
    {
        foreach ($characters as $character) {
            if ($character['rank'] !== null && $character['rank']->getOverallPoints() >= 50) {
                return true;
            }
        }

        return false;
    }

    public static function characterKey(string $name, string $realm): string
    {
        return strtolower(trim($name))."\0".strtolower(trim($realm));
    }
}
