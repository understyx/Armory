<?php

namespace App\Tests\Service;

use App\Service\CharacterUpdateThrottle;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

class CharacterUpdateThrottleTest extends TestCase
{
    public function testItClaimsACharacterUsingCaseInsensitiveKeys(): void
    {
        $connection = $this->createMock(Connection::class);
        $parameters = [];
        $connection->expects(self::once())
            ->method('executeStatement')
            ->willReturnCallback(static function (string $sql, array $values) use (&$parameters): int {
                $parameters = $values;

                return 1;
            });
        $connection->expects(self::once())
            ->method('fetchAssociative')
            ->with(
                self::stringContains('SELECT requested_at, claim_token'),
                ['name' => 'understyx', 'realm' => 'icecrown'],
            )
            ->willReturnCallback(static function () use (&$parameters): array {
                return [
                    'requested_at' => $parameters['requestedAt'],
                    'claim_token' => $parameters['claimToken'],
                ];
            });

        $decision = (new CharacterUpdateThrottle($connection))->claim('Understyx', 'Icecrown');

        self::assertTrue($decision->accepted);
        self::assertSame('understyx', $parameters['name']);
        self::assertSame('icecrown', $parameters['realm']);
        self::assertGreaterThanOrEqual(299, $decision->retryAfterSeconds());
    }

    public function testItRejectsAClaimHeldByAnotherRequest(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('executeStatement')->willReturn(0);
        $connection->method('fetchAssociative')->willReturn([
            'requested_at' => (new \DateTimeImmutable('-1 minute'))->format('Y-m-d H:i:s'),
            'claim_token' => str_repeat('a', 32),
        ]);

        $decision = (new CharacterUpdateThrottle($connection))->claim('Understyx', 'Icecrown');

        self::assertFalse($decision->accepted);
        self::assertGreaterThanOrEqual(238, $decision->retryAfterSeconds());
        self::assertLessThanOrEqual(240, $decision->retryAfterSeconds());
    }
}
