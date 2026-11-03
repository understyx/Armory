<?php

namespace App\MessageHandler;

use App\Message\RefreshCharacterSnapshotMessage;
use App\Service\CharacterRefreshTracker;
use App\Service\CharacterSnapshotUpdater;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RefreshCharacterSnapshotMessageHandler
{
    public function __construct(
        private CharacterSnapshotUpdater $snapshotUpdater,
        private ?CharacterRefreshTracker $refreshTracker = null,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(RefreshCharacterSnapshotMessage $message): void
    {
        $this->track('running', fn () => $this->refreshTracker?->markRunning($message->characterName, $message->realmName));

        try {
            $result = $this->snapshotUpdater->refresh($message->characterName, $message->realmName);
        } catch (\Throwable $exception) {
            $this->track('failed', fn () => $this->refreshTracker?->markFinished(
                $message->characterName,
                $message->realmName,
                'failed',
            ));

            throw $exception;
        }

        $this->track('finished', fn () => $this->refreshTracker?->markFinished(
            $message->characterName,
            $message->realmName,
            $result->status,
        ));
    }

    private function track(string $action, callable $callback): void
    {
        try {
            $callback();
        } catch (\Throwable $exception) {
            $this->logger?->warning('Unable to track character refresh status.', [
                'action' => $action,
                'exception' => $exception,
            ]);
        }
    }
}
