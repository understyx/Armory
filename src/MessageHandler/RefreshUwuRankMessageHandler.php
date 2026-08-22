<?php

namespace App\MessageHandler;

use App\Exception\UwuLogsException;
use App\Message\RefreshUwuRankMessage;
use App\Service\UwuRankUpdater;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RefreshUwuRankMessageHandler
{
    public function __construct(private UwuRankUpdater $updater)
    {
    }

    public function __invoke(RefreshUwuRankMessage $message): void
    {
        try {
            $this->updater->getOrRefresh($message->characterName, $message->realmName, $message->spec);
        } catch (UwuLogsException $exception) {
            // Missing rankings and an active 30-minute throttle are expected for guild roster jobs.
            if (!in_array($exception->getHttpStatus(), [404, 429], true)) {
                throw $exception;
            }
        }
    }
}
