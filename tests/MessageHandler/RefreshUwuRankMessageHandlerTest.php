<?php

namespace App\Tests\MessageHandler;

use App\Exception\UwuLogsException;
use App\Message\RefreshUwuRankMessage;
use App\MessageHandler\RefreshUwuRankMessageHandler;
use App\Service\UwuRankUpdater;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

final class RefreshUwuRankMessageHandlerTest extends TestCase
{
    public function testItRefreshesTheQueuedSpec(): void
    {
        $updater = $this->createMock(UwuRankUpdater::class);
        $updater->expects(self::once())->method('getOrRefresh')->with('Imtilted', 'Icecrown', '2')->willReturn([]);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        (new RefreshUwuRankMessageHandler($updater, $bus))(new RefreshUwuRankMessage('Imtilted', 'Icecrown', '2'));
    }

    public function testItAllowsTemporaryUpstreamFailuresToRetry(): void
    {
        $updater = $this->createMock(UwuRankUpdater::class);
        $updater->method('getOrRefresh')->willThrowException(new UwuLogsException('Unavailable', 502));

        $this->expectException(UwuLogsException::class);

        (new RefreshUwuRankMessageHandler($updater, $this->createMock(MessageBusInterface::class)))(
            new RefreshUwuRankMessage('Imtilted', 'Icecrown', '1'),
        );
    }

    public function testItRequeuesAThrottledSpecInsteadOfDiscardingIt(): void
    {
        $message = new RefreshUwuRankMessage('Imtilted', 'Icecrown', '3');
        $updater = $this->createMock(UwuRankUpdater::class);
        $updater->method('getOrRefresh')->willThrowException(new UwuLogsException('Throttled', 429));
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')->willReturnCallback(
            static function (object $redispatched, array $stamps) use ($message): Envelope {
                self::assertSame($message, $redispatched);
                self::assertInstanceOf(DelayStamp::class, $stamps[0]);
                self::assertSame(35000, $stamps[0]->getDelay());

                return new Envelope($redispatched, $stamps);
            },
        );

        (new RefreshUwuRankMessageHandler($updater, $bus))($message);
    }
}
