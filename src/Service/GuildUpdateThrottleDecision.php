<?php

namespace App\Service;

final readonly class GuildUpdateThrottleDecision
{
    public function __construct(public bool $accepted, public \DateTimeImmutable $retryAt)
    {
    }
}
