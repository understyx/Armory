<?php

namespace App\Tests\MessageHandler;

use App\Message\RefreshCharacterSnapshotMessage;
use App\MessageHandler\RefreshCharacterSnapshotMessageHandler;
use App\Service\CharacterRefreshResult;
use App\Service\CharacterRefreshTracker;
use App\Service\CharacterSnapshotUpdater;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class RefreshCharacterSnapshotMessageHandlerTest extends TestCase
{
    public function testItRefreshesTheRequestedCharacter(): void
    {
        $updater = $this->createMock(CharacterSnapshotUpdater::class);
        $updater->expects(self::once())
            ->method('refresh')
            ->with('Understyx', 'Icecrown')
            ->willReturn(new CharacterRefreshResult(CharacterRefreshResult::UPDATED));

        $tracker = $this->createMock(CharacterRefreshTracker::class);
        $tracker->expects(self::once())->method('markRunning')->with('Understyx', 'Icecrown');
        $tracker->expects(self::once())->method('markFinished')->with('Understyx', 'Icecrown', 'updated');

        (new RefreshCharacterSnapshotMessageHandler($updater, $tracker))(
            new RefreshCharacterSnapshotMessage('Understyx', 'Icecrown'),
        );
    }

    public function testItMarksARefreshFailedAndRethrowsUpdaterExceptions(): void
    {
        $updater = $this->createMock(CharacterSnapshotUpdater::class);
        $updater->method('refresh')->willThrowException(new \RuntimeException('refresh failed'));
        $tracker = $this->createMock(CharacterRefreshTracker::class);
        $tracker->expects(self::once())->method('markRunning');
        $tracker->expects(self::once())->method('markFinished')->with('Understyx', 'Icecrown', 'failed');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('refresh failed');
        (new RefreshCharacterSnapshotMessageHandler($updater, $tracker))(
            new RefreshCharacterSnapshotMessage('Understyx', 'Icecrown'),
        );
    }

    public function testItCanTrackAHandlerRunWhenNoQueuedRowExists(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::exactly(2))
            ->method('executeStatement')
            ->willReturnCallback(static function (string $sql): int {
                self::assertStringContainsString('INSERT INTO character_refresh_status', $sql);

                return 1;
            });
        $updater = $this->createMock(CharacterSnapshotUpdater::class);
        $updater->method('refresh')->willReturn(new CharacterRefreshResult(CharacterRefreshResult::SOURCE_UNAVAILABLE));

        (new RefreshCharacterSnapshotMessageHandler($updater, new CharacterRefreshTracker($connection)))(
            new RefreshCharacterSnapshotMessage('Understyx', 'Icecrown'),
        );
    }

    public function testTrackingErrorsAreLoggedWithoutBreakingTheRefresh(): void
    {
        $updater = $this->createMock(CharacterSnapshotUpdater::class);
        $updater->expects(self::once())->method('refresh')->willReturn(new CharacterRefreshResult(CharacterRefreshResult::UPDATED));
        $tracker = $this->createMock(CharacterRefreshTracker::class);
        $tracker->method('markRunning')->willThrowException(new \RuntimeException('tracking down'));
        $tracker->method('markFinished')->willThrowException(new \RuntimeException('tracking down'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::exactly(2))->method('warning');

        (new RefreshCharacterSnapshotMessageHandler($updater, $tracker, $logger))(
            new RefreshCharacterSnapshotMessage('Understyx', 'Icecrown'),
        );
    }
}
