<?php

namespace App\Tests\MessageHandler;

use App\Exception\UwuLogsException;
use App\Exception\UwuLogsThrottleException;
use App\Message\RefreshUwuRankMessage;
use App\MessageHandler\RefreshUwuRankMessageHandler;
use App\Service\UwuRankUpdater;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DeduplicateStamp;
use Symfony\Component\Messenger\Stamp\DelayStamp;

final class RefreshUwuRankMessageHandlerTest extends TestCase
{
    public function testItRefreshesTheQueuedSpec(): void
    {
        $updater = $this->createMock(UwuRankUpdater::class);
        $updater->expects(self::once())->method('getOrRefresh')->with('Imtilted', 'Icecrown', '2', false)->willReturn([]);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        (new RefreshUwuRankMessageHandler($updater, $bus, self::noPause()))(new RefreshUwuRankMessage('Imtilted', 'Icecrown', '2'));
    }

    public function testItAllowsTemporaryUpstreamFailuresToRetry(): void
    {
        $updater = $this->createMock(UwuRankUpdater::class);
        $updater->method('getOrRefresh')->willThrowException(new UwuLogsException('Unavailable', 502));

        $this->expectException(UwuLogsException::class);

        (new RefreshUwuRankMessageHandler($updater, $this->createMock(MessageBusInterface::class), self::noPause()))(
            new RefreshUwuRankMessage('Imtilted', 'Icecrown', '1'),
        );
    }

    public function testItRequeuesAnUpstreamRateLimitAfterTheProviderBackoff(): void
    {
        $message = new RefreshUwuRankMessage('Imtilted', 'Icecrown', '3');
        $updater = $this->createMock(UwuRankUpdater::class);
        $updater->method('getOrRefresh')->willThrowException(new UwuLogsException('Upstream rate limited', 429));
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')->willReturnCallback(
            static function (object $redispatched, array $stamps) use ($message): Envelope {
                self::assertSame($message, $redispatched);
                self::assertInstanceOf(DelayStamp::class, $stamps[0]);
                self::assertSame(35000, $stamps[0]->getDelay());
                self::assertInstanceOf(DeduplicateStamp::class, $stamps[1]);
                self::assertSame(3635.0, $stamps[1]->getTtl());

                return new Envelope($redispatched, $stamps);
            },
        );

        (new RefreshUwuRankMessageHandler($updater, $bus, self::noPause()))($message);
    }

    public function testItRequeuesALocalThrottleRejectionAfterOneQueueInterval(): void
    {
        $message = new RefreshUwuRankMessage('Imtilted', 'Icecrown', '2');
        $updater = $this->createMock(UwuRankUpdater::class);
        $updater->method('getOrRefresh')->willThrowException(new UwuLogsThrottleException('Local throttle', 429));
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())->method('dispatch')->willReturnCallback(
            static function (object $redispatched, array $stamps) use ($message): Envelope {
                self::assertSame($message, $redispatched);
                self::assertInstanceOf(DelayStamp::class, $stamps[0]);
                self::assertSame(5000, $stamps[0]->getDelay());
                self::assertInstanceOf(DeduplicateStamp::class, $stamps[1]);
                self::assertSame(3605.0, $stamps[1]->getTtl());

                return new Envelope($redispatched, $stamps);
            },
        );

        (new RefreshUwuRankMessageHandler($updater, $bus, self::noPause()))($message);
    }

    public function testItPausesForFiveSecondsAfterContactingUwuLogs(): void
    {
        $updater = $this->createMock(UwuRankUpdater::class);
        $updater->method('getOrRefresh')->willReturn(['cached' => false]);
        $pauses = [];

        (new RefreshUwuRankMessageHandler(
            $updater,
            $this->createMock(MessageBusInterface::class),
            static function (int $microseconds) use (&$pauses): void { $pauses[] = $microseconds; },
        ))(new RefreshUwuRankMessage('Imtilted', 'Icecrown', '1'));

        self::assertSame([5_000_000], $pauses);
    }

    public function testItPausesAfterAFailedUwuLogsRequest(): void
    {
        $updater = $this->createMock(UwuRankUpdater::class);
        $updater->method('getOrRefresh')->willThrowException(new UwuLogsException('Unavailable', 502));
        $pauses = [];

        try {
            (new RefreshUwuRankMessageHandler(
                $updater,
                $this->createMock(MessageBusInterface::class),
                static function (int $microseconds) use (&$pauses): void { $pauses[] = $microseconds; },
            ))(new RefreshUwuRankMessage('Imtilted', 'Icecrown', '1'));
            self::fail('The upstream failure should be rethrown for retry.');
        } catch (UwuLogsException) {
            self::assertSame([5_000_000], $pauses);
        }
    }

    public function testItDoesNotPauseWhenUwuLogsWasNotContacted(): void
    {
        $cachedUpdater = $this->createMock(UwuRankUpdater::class);
        $cachedUpdater->method('getOrRefresh')->willReturn(['cached' => true]);
        $throttledUpdater = $this->createMock(UwuRankUpdater::class);
        $throttledUpdater->method('getOrRefresh')->willThrowException(new UwuLogsThrottleException('Local throttle', 429));
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(
            static fn (object $message, array $stamps): Envelope => new Envelope($message, $stamps),
        );
        $pause = static fn (int $microseconds) => self::fail('No Uwu-logs request was made.');

        foreach ([$cachedUpdater, $throttledUpdater] as $updater) {
            (new RefreshUwuRankMessageHandler($updater, $bus, $pause))(
                new RefreshUwuRankMessage('Imtilted', 'Icecrown', '1'),
            );
        }

        self::addToAssertionCount(1);
    }

    private static function noPause(): \Closure
    {
        return static function (int $microseconds): void {
        };
    }
}
