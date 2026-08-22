<?php

namespace App\Service;

final readonly class CharacterUpdateThrottleDecision
{
    public function __construct(
        public bool $accepted,
        public \DateTimeImmutable $nextAllowedAt,
    ) {
    }

    public function retryAfterSeconds(?\DateTimeImmutable $now = null): int
    {
        $now ??= new \DateTimeImmutable();

        return max(0, $this->nextAllowedAt->getTimestamp() - $now->getTimestamp());
    }
}
