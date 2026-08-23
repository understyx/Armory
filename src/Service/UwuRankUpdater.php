<?php

namespace App\Service;

use App\Exception\UwuLogsException;
use App\Repository\UwuLogRankRepository;

class UwuRankUpdater
{
    public function __construct(
        private readonly UwuLogsService $uwuLogsService,
        private readonly UwuLogRankRepository $rankRepository,
        private readonly UwuLogUpdateThrottle $updateThrottle,
    ) {
    }

    public function getOrRefresh(string $characterName, string $realmName, string $spec = '1'): array
    {
        $cached = $this->rankRepository->findCached(
            $characterName,
            $realmName,
            $spec,
            new \DateTimeImmutable('-30 seconds'),
        );
        if ($cached !== null && $cached->getPayload() !== []) {
            return $cached->getPayload() + [
                'cached' => true,
                'cachedAt' => $cached->getScrapedAt()?->format(\DateTimeInterface::ATOM),
            ];
        }

        $decision = $this->updateThrottle->claim($characterName, $realmName);
        if (!$decision->accepted) {
            $seconds = max(1, $decision->retryAt->getTimestamp() - time());
            throw new UwuLogsException(sprintf(
                'Uwu-logs can only be updated once per character every 30 seconds. Try again in about %d %s.',
                $seconds,
                $seconds === 1 ? 'second' : 'seconds',
            ), 429);
        }

        try {
            $payload = $this->uwuLogsService->fetchRankings($characterName, $realmName, $spec);
        } catch (UwuLogsException $exception) {
            if ($exception->getHttpStatus() >= 500 && $decision->claimToken !== null) {
                $this->updateThrottle->release($characterName, $realmName, $decision->claimToken);
            }

            throw $exception;
        }
        $stored = $this->rankRepository->upsertResult($characterName, $realmName, $spec, $payload);

        return $payload + [
            'cached' => false,
            'cachedAt' => $stored->getScrapedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }
}
