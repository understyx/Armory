<?php

namespace App\Tests\Service;

use App\Entity\User;
use App\Service\CharacterCommentRateLimiter;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

final class CharacterCommentRateLimiterTest extends TestCase
{
    public function testEnforcesTheThirtySecondPerUserLimit(): void
    {
        $now = new \DateTimeImmutable('2026-11-09 12:00:30');
        $connection = $this->connection(['total' => 1, 'latest' => '2026-11-09 12:00:10', 'oldest' => '2026-11-09 12:00:10']);

        self::assertSame(10, (new CharacterCommentRateLimiter($connection))->secondsUntilAllowed($this->user(), $now));
    }

    public function testEnforcesTheThirtyPerDayLimit(): void
    {
        $now = new \DateTimeImmutable('2026-11-09 12:00:30');
        $connection = $this->connection(['total' => 30, 'latest' => '2026-11-09 12:00:10', 'oldest' => '2026-11-08 13:00:30']);

        self::assertSame(3600, (new CharacterCommentRateLimiter($connection))->secondsUntilAllowed($this->user(), $now));
    }

    public function testAllowsPostingWhenBothLimitsAreClear(): void
    {
        $connection = $this->connection(['total' => 0, 'latest' => null, 'oldest' => null]);

        self::assertSame(0, (new CharacterCommentRateLimiter($connection))->secondsUntilAllowed(
            $this->user(),
            new \DateTimeImmutable('2026-11-09 12:00:30'),
        ));
    }

    /** @param array{total: int, latest: ?string, oldest: ?string} $result */
    private function connection(array $result): Connection
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())
            ->method('fetchOne')
            ->with(self::stringContains('FOR UPDATE'), ['userId' => 5])
            ->willReturn(5);
        $connection->expects(self::once())
            ->method('fetchAssociative')
            ->with(self::stringContains('character_comments'), self::anything())
            ->willReturn($result);

        return $connection;
    }

    private function user(): User
    {
        $user = (new User())->setDiscordId('commenter');
        (new \ReflectionProperty($user, 'id'))->setValue($user, 5);

        return $user;
    }
}
