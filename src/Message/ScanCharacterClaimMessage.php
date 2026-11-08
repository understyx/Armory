<?php

namespace App\Message;

final readonly class ScanCharacterClaimMessage
{
    public const BASELINE = 'baseline';
    public const VERIFY = 'verify';

    public function __construct(
        public int $challengeId,
        public string $phase,
    ) {
    }
}
