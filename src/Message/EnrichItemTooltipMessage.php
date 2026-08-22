<?php

namespace App\Message;

final readonly class EnrichItemTooltipMessage
{
    public function __construct(
        public int $itemId,
        public int $setId = 0,
        public string $locale = 'enUS',
    ) {
    }
}
