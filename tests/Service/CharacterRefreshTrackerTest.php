<?php

namespace App\Tests\Service;

use App\Service\CharacterRefreshTracker;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

class CharacterRefreshTrackerTest extends TestCase
{
    public function testItStoresQueuedRunningAndFinishedTransitionsWithNormalizedKeys(): void
    {
        $calls = [];
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::exactly(3))
            ->method('executeStatement')
            ->willReturnCallback(static function (string $sql, array $parameters) use (&$calls): int {
                $calls[] = [$sql, $parameters];

                return 1;
            });

        $tracker = new CharacterRefreshTracker($connection);
        $tracker->markQueued(' Understyx ', ' Icecrown ');
        $tracker->markRunning(' Understyx ', ' Icecrown ');
        $tracker->markFinished(' Understyx ', ' Icecrown ', 'updated');

        self::assertSame(['understyx', 'understyx', 'understyx'], array_column(array_column($calls, 1), 'name'));
        self::assertSame(['icecrown', 'icecrown', 'icecrown'], array_column(array_column($calls, 1), 'realm'));
        self::assertStringContainsString("state = 'running'", $calls[1][0]);
        self::assertSame('updated', $calls[2][1]['state']);
    }

    public function testItCalculatesPositionAndEtaFromClampedRecentDurations(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('fetchAssociative')->willReturn([
            'state' => 'queued',
            'requested_at' => (new \DateTimeImmutable('-30 seconds'))->format('Y-m-d H:i:s'),
        ]);
        $connection->expects(self::once())->method('fetchOne')->willReturn('2');
        $connection->expects(self::once())
            ->method('fetchAllAssociative')
            ->with(self::stringContains('LIMIT 20'))
            ->willReturn([
                ['started_at' => '2026-01-01 00:00:00', 'finished_at' => '2026-01-01 00:00:00'],
                ['started_at' => '2026-01-01 00:00:00', 'finished_at' => '2026-01-01 00:05:00'],
            ]);

        $status = (new CharacterRefreshTracker($connection))->status('Understyx', 'Icecrown');

        self::assertSame(['state' => 'queued', 'position' => 3, 'etaSeconds' => 188], $status);
    }

    public function testItUsesTwentySecondsWhenThereAreNoFinishedDurations(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn([
            'state' => 'running',
            'requested_at' => (new \DateTimeImmutable('-30 seconds'))->format('Y-m-d H:i:s'),
        ]);
        $connection->method('fetchOne')->willReturn(0);
        $connection->method('fetchAllAssociative')->willReturn([]);

        $status = (new CharacterRefreshTracker($connection))->status('Understyx', 'Icecrown');

        self::assertSame(['state' => 'running', 'position' => 1, 'etaSeconds' => 20], $status);
    }

    public function testItTreatsAnOldActiveRowAsFailedAndReturnsIdleForMissingRows(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::exactly(2))
            ->method('fetchAssociative')
            ->willReturnOnConsecutiveCalls(
                ['state' => 'running', 'requested_at' => (new \DateTimeImmutable('-16 minutes'))->format('Y-m-d H:i:s')],
                false,
            );
        $connection->expects(self::never())->method('fetchOne');
        $connection->expects(self::never())->method('fetchAllAssociative');
        $tracker = new CharacterRefreshTracker($connection);

        self::assertSame(
            ['state' => 'failed', 'position' => null, 'etaSeconds' => null],
            $tracker->status('Understyx', 'Icecrown'),
        );
        self::assertSame(
            ['state' => 'idle', 'position' => null, 'etaSeconds' => null],
            $tracker->status('Missing', 'Icecrown'),
        );
    }
}
