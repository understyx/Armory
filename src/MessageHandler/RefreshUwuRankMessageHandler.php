<?php

namespace App\MessageHandler;

use App\Exception\UwuLogsException;
use App\Message\RefreshUwuRankMessage;
use App\Service\UwuRankUpdater;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

#[AsMessageHandler]
final readonly class RefreshUwuRankMessageHandler
{
    public function __construct(
        private UwuRankUpdater $updater,
        private MessageBusInterface $messageBus,
    ) {
    }

    public function __invoke(RefreshUwuRankMessage $message): void
    {
        try {
            $this->updater->getOrRefresh($message->characterName, $message->realmName, $message->spec);
        } catch (UwuLogsException $exception) {
            if ($exception->getHttpStatus() === 404) {
                return;
            }
            if ($exception->getHttpStatus() === 429) {
                $this->messageBus->dispatch($message, [new DelayStamp(35000)]);

                return;
            }

            throw $exception;
        }
    }
}
