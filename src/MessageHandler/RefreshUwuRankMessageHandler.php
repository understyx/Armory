<?php

namespace App\MessageHandler;

use App\Exception\UwuLogsException;
use App\Exception\UwuLogsThrottleException;
use App\Message\RefreshUwuRankMessage;
use App\Service\DeduplicatingMessageDispatcher;
use App\Service\UwuRankUpdater;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

#[AsMessageHandler]
final class RefreshUwuRankMessageHandler
{
    private const REQUEST_GAP_MICROSECONDS = 5_000_000;

    private readonly \Closure $pause;

    public function __construct(
        private readonly UwuRankUpdater $updater,
        private readonly MessageBusInterface $messageBus,
        ?\Closure $pause = null,
    ) {
        $this->pause = $pause ?? usleep(...);
    }

    public function __invoke(RefreshUwuRankMessage $message): void
    {
        // Queue delays only schedule the earliest run, so overdue jobs would otherwise
        // reach Uwu-logs back to back. Hold this dedicated worker after each real request.
        $contactedUwuLogs = true;

        try {
            $rank = $this->updater->getOrRefresh(
                $message->characterName,
                $message->realmName,
                $message->spec,
                $message->forceRefresh,
            );
            $contactedUwuLogs = !($rank['cached'] ?? false);
        } catch (UwuLogsException $exception) {
            if ($exception->getHttpStatus() === 404) {
                return;
            }
            if ($exception->getHttpStatus() === 429) {
                $isLocalThrottle = $exception instanceof UwuLogsThrottleException;
                $contactedUwuLogs = !$isLocalThrottle;
                DeduplicatingMessageDispatcher::dispatch(
                    $this->messageBus,
                    $message,
                    [new DelayStamp($isLocalThrottle ? 5000 : 35000)],
                );

                return;
            }

            throw $exception;
        } finally {
            if ($contactedUwuLogs) {
                ($this->pause)(self::REQUEST_GAP_MICROSECONDS);
            }
        }
    }
}
