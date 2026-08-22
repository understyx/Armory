<?php

namespace App\MessageHandler;

use App\Message\RefreshCharacterSnapshotMessage;
use App\Service\CharacterSnapshotUpdater;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RefreshCharacterSnapshotMessageHandler
{
    public function __construct(private CharacterSnapshotUpdater $snapshotUpdater)
    {
    }

    public function __invoke(RefreshCharacterSnapshotMessage $message): void
    {
        $this->snapshotUpdater->refresh($message->characterName, $message->realmName);
    }
}
