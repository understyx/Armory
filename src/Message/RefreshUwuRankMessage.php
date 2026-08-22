<?php

namespace App\Message;

final readonly class RefreshUwuRankMessage
{
    public function __construct(
        public string $characterName,
        public string $realmName,
        public string $spec,
    ) {
    }
}
