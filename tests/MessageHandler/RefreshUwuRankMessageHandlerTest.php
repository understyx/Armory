<?php

namespace App\Tests\MessageHandler;

use App\Exception\UwuLogsException;
use App\Message\RefreshUwuRankMessage;
use App\MessageHandler\RefreshUwuRankMessageHandler;
use App\Service\UwuRankUpdater;
use PHPUnit\Framework\TestCase;

final class RefreshUwuRankMessageHandlerTest extends TestCase
{
    public function testItRefreshesTheQueuedSpec(): void
    {
        $updater = $this->createMock(UwuRankUpdater::class);
        $updater->expects(self::once())->method('getOrRefresh')->with('Imtilted', 'Icecrown', '2')->willReturn([]);

        (new RefreshUwuRankMessageHandler($updater))(new RefreshUwuRankMessage('Imtilted', 'Icecrown', '2'));
    }

    public function testItAllowsTemporaryUpstreamFailuresToRetry(): void
    {
        $updater = $this->createMock(UwuRankUpdater::class);
        $updater->method('getOrRefresh')->willThrowException(new UwuLogsException('Unavailable', 502));

        $this->expectException(UwuLogsException::class);

        (new RefreshUwuRankMessageHandler($updater))(new RefreshUwuRankMessage('Imtilted', 'Icecrown', '1'));
    }
}
