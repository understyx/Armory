<?php

namespace App\Message;

final readonly class RefreshCharacterSnapshotMessage
{
    public function __construct(
        public string $characterName,
        public string $realmName,
    ) {
    }
}
