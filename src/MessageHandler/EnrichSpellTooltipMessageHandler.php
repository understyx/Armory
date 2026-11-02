<?php

namespace App\MessageHandler;

use App\Message\EnrichSpellTooltipMessage;
use App\Service\SpellTooltipEnrichmentService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class EnrichSpellTooltipMessageHandler
{
    public function __construct(private SpellTooltipEnrichmentService $enrichmentService)
    {
    }

    public function __invoke(EnrichSpellTooltipMessage $message): void
    {
        $this->enrichmentService->enrich($message->spellId);
    }
}
