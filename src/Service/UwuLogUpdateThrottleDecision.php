<?php

namespace App\Service;

final readonly class UwuLogUpdateThrottleDecision
{
    public function __construct(
        public bool $accepted,
        public \DateTimeImmutable $retryAt,
        public ?string $claimToken = null,
    )
    {
    }
}
