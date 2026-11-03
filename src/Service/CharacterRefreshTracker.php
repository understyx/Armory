<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

class CharacterRefreshTracker
{
    private const ACTIVE_STATES = ['queued', 'running'];
    private const FINISHED_STATES = ['updated', 'partial', 'unchanged', 'not_found', 'source_unavailable', 'failed'];
    private const STALE_AFTER_SECONDS = 900;

    public function __construct(private readonly Connection $connection)
    {
    }

    public function markQueued(string $name, string $realm): void
    {
        $now = $this->now();
        $this->upsert($name, $realm, 'queued', $now, null, null);
    }

    public function markRunning(string $name, string $realm): void
    {
        $now = $this->now();
        $this->connection->executeStatement(
            <<<'SQL'
                INSERT INTO character_refresh_status (name, realm, state, requested_at, started_at, finished_at)
                VALUES (:name, :realm, 'running', :requestedAt, :startedAt, NULL)
                ON DUPLICATE KEY UPDATE
                    requested_at = IF(state IN ('queued', 'running') AND requested_at >= :cutoff, requested_at, VALUES(requested_at)),
                    started_at = VALUES(started_at),
                    finished_at = NULL,
                    state = 'running'
                SQL,
            [
                'name' => $this->normalize($name),
                'realm' => $this->normalize($realm),
                'requestedAt' => $now,
                'startedAt' => $now,
                'cutoff' => (new \DateTimeImmutable('-'.self::STALE_AFTER_SECONDS.' seconds'))->format('Y-m-d H:i:s'),
            ],
        );
    }

    public function markFinished(string $name, string $realm, string $state): void
    {
        if (!in_array($state, self::FINISHED_STATES, true)) {
            throw new \InvalidArgumentException(sprintf('Invalid finished refresh state "%s".', $state));
        }

        $now = $this->now();
        $this->connection->executeStatement(
            <<<'SQL'
                INSERT INTO character_refresh_status (name, realm, state, requested_at, started_at, finished_at)
                VALUES (:name, :realm, :state, :requestedAt, NULL, :finishedAt)
                ON DUPLICATE KEY UPDATE state = VALUES(state), finished_at = VALUES(finished_at)
                SQL,
            [
                'name' => $this->normalize($name),
                'realm' => $this->normalize($realm),
                'state' => $state,
                'requestedAt' => $now,
                'finishedAt' => $now,
            ],
        );
    }

    /** @return array{state: string, position: ?int, etaSeconds: ?int} */
    public function status(string $name, string $realm): array
    {
        $normalizedName = $this->normalize($name);
        $normalizedRealm = $this->normalize($realm);
        $row = $this->connection->fetchAssociative(
            'SELECT state, requested_at FROM character_refresh_status WHERE name = :name AND realm = :realm',
            ['name' => $normalizedName, 'realm' => $normalizedRealm],
        );

        if ($row === false) {
            return ['state' => 'idle', 'position' => null, 'etaSeconds' => null];
        }

        $state = (string) $row['state'];
        $requestedAt = new \DateTimeImmutable((string) $row['requested_at']);
        if (in_array($state, self::ACTIVE_STATES, true)) {
            $cutoff = new \DateTimeImmutable('-'.self::STALE_AFTER_SECONDS.' seconds');
            if ($requestedAt < $cutoff) {
                return ['state' => 'failed', 'position' => null, 'etaSeconds' => null];
            }

            $position = (int) $this->connection->fetchOne(
                <<<'SQL'
                    SELECT COUNT(*) FROM character_refresh_status
                    WHERE state IN ('queued', 'running')
                      AND requested_at >= :cutoff
                      AND requested_at <= :requestedAt
                      AND NOT (name = :name AND realm = :realm)
                    SQL,
                [
                    'cutoff' => $cutoff->format('Y-m-d H:i:s'),
                    'requestedAt' => $requestedAt->format('Y-m-d H:i:s'),
                    'name' => $normalizedName,
                    'realm' => $normalizedRealm,
                ],
            ) + 1;

            return [
                'state' => $state,
                'position' => $position,
                'etaSeconds' => (int) ceil($position * $this->averageDurationSeconds()),
            ];
        }

        return ['state' => $state, 'position' => null, 'etaSeconds' => null];
    }

    private function averageDurationSeconds(): float
    {
        $rows = $this->connection->fetchAllAssociative(
            <<<'SQL'
                SELECT started_at, finished_at FROM character_refresh_status
                WHERE state IN ('updated', 'partial', 'unchanged', 'not_found', 'source_unavailable', 'failed')
                  AND started_at IS NOT NULL AND finished_at IS NOT NULL
                ORDER BY finished_at DESC
                LIMIT 20
                SQL,
        );

        if ($rows === []) {
            return 20.0;
        }

        $durations = [];
        foreach ($rows as $row) {
            $seconds = (new \DateTimeImmutable((string) $row['finished_at']))->getTimestamp()
                - (new \DateTimeImmutable((string) $row['started_at']))->getTimestamp();
            $durations[] = min(120, max(5, $seconds));
        }

        return array_sum($durations) / count($durations);
    }

    private function upsert(string $name, string $realm, string $state, string $requestedAt, ?string $startedAt, ?string $finishedAt): void
    {
        $this->connection->executeStatement(
            <<<'SQL'
                INSERT INTO character_refresh_status (name, realm, state, requested_at, started_at, finished_at)
                VALUES (:name, :realm, :state, :requestedAt, :startedAt, :finishedAt)
                ON DUPLICATE KEY UPDATE
                    state = VALUES(state),
                    requested_at = VALUES(requested_at),
                    started_at = VALUES(started_at),
                    finished_at = VALUES(finished_at)
                SQL,
            [
                'name' => $this->normalize($name),
                'realm' => $this->normalize($realm),
                'state' => $state,
                'requestedAt' => $requestedAt,
                'startedAt' => $startedAt,
                'finishedAt' => $finishedAt,
            ],
        );
    }

    private function normalize(string $value): string
    {
        return mb_strtolower(trim($value));
    }

    private function now(): string
    {
        return (new \DateTimeImmutable())->format('Y-m-d H:i:s');
    }
}
