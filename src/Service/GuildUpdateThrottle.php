<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

class GuildUpdateThrottle
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function claim(string $guildName, string $realmName): GuildUpdateThrottleDecision
    {
        $now = new \DateTimeImmutable();
        $tomorrow = $now->modify('tomorrow midnight');
        $inserted = $this->connection->executeStatement(
            'INSERT IGNORE INTO guild_update_requests (name, realm, requested_on, requested_at) VALUES (:name, :realm, :day, :requestedAt)',
            [
                'name' => strtolower(trim($guildName)),
                'realm' => strtolower(trim($realmName)),
                'day' => $now->format('Y-m-d'),
                'requestedAt' => $now->format('Y-m-d H:i:s'),
            ],
        );

        return new GuildUpdateThrottleDecision($inserted === 1, $tomorrow);
    }
}
