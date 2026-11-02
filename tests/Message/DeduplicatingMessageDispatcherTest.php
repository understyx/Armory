<?php

namespace App\Tests\Message;

use App\Exception\UwuLogsException;
use App\Message\RefreshUwuRankMessage;
use App\MessageHandler\RefreshUwuRankMessageHandler;
use App\Service\DeduplicatingMessageDispatcher;
use App\Service\UwuRankUpdater;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\DeduplicateMiddleware;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;
use Symfony\Component\Messenger\Stamp\DeduplicateStamp;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Transport\Sender\SendersLocator;
use Symfony\Component\Messenger\Handler\HandlersLocator;

final class DeduplicatingMessageDispatcherTest extends TestCase
{
    public function test429RedeliveryGetsAFreshLockAndIsNotDropped(): void
    {
        $attempts = 0;
        $updater = $this->createMock(UwuRankUpdater::class);
        $updater->expects(self::exactly(2))
            ->method('getOrRefresh')
            ->willReturnCallback(static function () use (&$attempts): array {
                if (++$attempts === 1) {
                    throw new UwuLogsException('Upstream rate limited', 429);
                }

                return [];
            });

        $queue = new InMemoryTransport();
        $handler = null;
        $bus = null;
        $handler = static function (RefreshUwuRankMessage $message) use ($updater, &$bus): void {
            (new RefreshUwuRankMessageHandler($updater, $bus, static function (int $microseconds): void {}))($message);
        };
        $bus = $this->createRankBus($queue, $handler);
        $message = new RefreshUwuRankMessage('Imtilted', 'Icecrown', '3');

        DeduplicatingMessageDispatcher::dispatch($bus, $message, [new DelayStamp(15000)]);
        DeduplicatingMessageDispatcher::dispatch(
            $bus,
            new RefreshUwuRankMessage(' imtilted ', 'icecrown', '3'),
            [new DelayStamp(15000)],
        );

        self::assertCount(1, $queue->getSent());
        $initial = $queue->getSent()[0];
        $initialDedupe = $initial->last(DeduplicateStamp::class);
        self::assertInstanceOf(DeduplicateStamp::class, $initialDedupe);
        self::assertSame(3615.0, $initialDedupe->getTtl());

        // Receiving the first job releases its queue lock before the handler runs.
        // The handler's 429 re-dispatch can therefore acquire a fresh lock.
        $bus->dispatch($initial->with(new ReceivedStamp('uwu')));

        self::assertCount(2, $queue->getSent());
        $redispatched = $queue->getSent()[1];
        self::assertSame(35000, $redispatched->last(DelayStamp::class)?->getDelay());
        $redispatchDedupe = $redispatched->last(DeduplicateStamp::class);
        self::assertInstanceOf(DeduplicateStamp::class, $redispatchDedupe);
        self::assertSame(3635.0, $redispatchDedupe->getTtl());
        self::assertTrue($redispatchDedupe->onlyDeduplicateInQueue());

        // A transport-delivered retry carries the stamp, but ReceivedStamp bypasses
        // duplicate acquisition and still invokes the handler.
        $bus->dispatch($redispatched->with(new ReceivedStamp('uwu')));

        self::assertSame(2, $attempts);
        self::assertCount(2, $queue->getSent());

        // The queue lock was released at handling start, so another pending job can queue.
        DeduplicatingMessageDispatcher::dispatch($bus, $message);
        self::assertCount(3, $queue->getSent());
    }

    /** @param callable(RefreshUwuRankMessage): void $handler */
    private function createRankBus(InMemoryTransport $queue, callable $handler): MessageBus
    {
        $senders = new class($queue) implements ContainerInterface {
            public function __construct(private readonly InMemoryTransport $queue)
            {
            }

            public function get(string $id): mixed
            {
                if ($id !== 'uwu') {
                    throw new \InvalidArgumentException(sprintf('Unknown sender "%s".', $id));
                }

                return $this->queue;
            }

            public function has(string $id): bool
            {
                return $id === 'uwu';
            }
        };
        $sendersLocator = new SendersLocator(
            [RefreshUwuRankMessage::class => ['uwu']],
            $senders,
        );

        return new MessageBus([
            new DeduplicateMiddleware(new LockFactory(new InMemoryStore())),
            new SendMessageMiddleware($sendersLocator),
            new HandleMessageMiddleware(new HandlersLocator([
                RefreshUwuRankMessage::class => [$handler],
            ])),
        ]);
    }
}
