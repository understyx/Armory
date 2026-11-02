<?php

namespace App\Message;

final readonly class EnrichSpellTooltipMessage
{
    public function __construct(public int $spellId)
    {
    }
}
