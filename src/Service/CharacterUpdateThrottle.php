<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

class CharacterUpdateThrottle
{
    private const COOLDOWN_SECONDS = 300;

    public function __construct(private readonly Connection $connection)
    {
    }

    public function claim(string $characterName, string $realmName): CharacterUpdateThrottleDecision
    {
        $now = new \DateTimeImmutable();
        $cutoff = $now->modify('-'.self::COOLDOWN_SECONDS.' seconds');
        $token = bin2hex(random_bytes(16));
        $name = strtolower(trim($characterName));
        $realm = strtolower(trim($realmName));

        $this->connection->executeStatement(
            <<<'SQL'
                INSERT INTO character_update_requests (name, realm, requested_at, claim_token)
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
            'SELECT requested_at, claim_token FROM character_update_requests WHERE name = :name AND realm = :realm',
            ['name' => $name, 'realm' => $realm],
        );

        if ($row === false) {
            throw new \RuntimeException('Unable to read the character update throttle.');
        }

        $requestedAt = new \DateTimeImmutable((string) $row['requested_at']);

        return new CharacterUpdateThrottleDecision(
            hash_equals((string) $row['claim_token'], $token),
            $requestedAt->modify('+'.self::COOLDOWN_SECONDS.' seconds'),
        );
    }
}
