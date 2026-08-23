<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

class UwuLogUpdateThrottle
{
    public const COOLDOWN_SECONDS = 30;

    public function __construct(private readonly Connection $connection)
    {
    }

    public function claim(string $characterName, string $realmName): UwuLogUpdateThrottleDecision
    {
        $now = new \DateTimeImmutable();
        $cutoff = $now->modify('-'.self::COOLDOWN_SECONDS.' seconds');
        $token = bin2hex(random_bytes(16));
        $name = strtolower(trim($characterName));
        $realm = strtolower(trim($realmName));

        $this->connection->executeStatement(
            <<<'SQL'
                INSERT INTO uwu_log_update_requests (name, realm, requested_at, claim_token)
                VALUES (:name, :realm, :requestedAt, :claimToken)
                ON DUPLICATE KEY UPDATE
                    claim_token = IF(requested_at <= :cutoff, VALUES(claim_token), claim_token),
                    requested_at = IF(requested_at <= :cutoff, VALUES(requested_at), requested_at)
                SQL,
            [
                'name' => $name,
                'realm' => $realm,
                'requestedAt' => $now->format('Y-m-d H:i:s'),
                'claimToken' => $token,
                'cutoff' => $cutoff->format('Y-m-d H:i:s'),
            ],
        );

        $row = $this->connection->fetchAssociative(
            'SELECT requested_at, claim_token FROM uwu_log_update_requests WHERE name = :name AND realm = :realm',
            ['name' => $name, 'realm' => $realm],
        );
        if ($row === false) {
            throw new \RuntimeException('Unable to read the Uwu-logs update throttle.');
        }

        $requestedAt = new \DateTimeImmutable((string) $row['requested_at']);

        return new UwuLogUpdateThrottleDecision(
            hash_equals((string) $row['claim_token'], $token),
            $requestedAt->modify('+'.self::COOLDOWN_SECONDS.' seconds'),
            $token,
        );
    }

    public function release(string $characterName, string $realmName, string $claimToken): void
    {
        $this->connection->executeStatement(
            'DELETE FROM uwu_log_update_requests WHERE name = :name AND realm = :realm AND claim_token = :claimToken',
            [
                'name' => strtolower(trim($characterName)),
                'realm' => strtolower(trim($realmName)),
                'claimToken' => $claimToken,
            ],
        );
    }
}
