<?php

namespace App\Service;

use App\Repository\WowSpellTooltipRepository;
use App\Service\ItemTooltipProvider\CavernOfTimeSpellTooltipProvider;

readonly class SpellTooltipEnrichmentService
{
    public function __construct(
        private WowSpellTooltipRepository $repository,
        private CavernOfTimeSpellTooltipProvider $provider,
    ) {
    }

    public function enrich(int $spellId): bool
    {
        $tooltip = $this->provider->fetch($spellId);
        if ($tooltip === null) {
            $this->repository->saveNotFound(
                $spellId,
                sprintf('https://wotlk.cavernoftime.com/spell=%d', $spellId),
            );

            return false;
        }

        $this->repository->saveTooltip($spellId, $tooltip);

        return true;
    }
}
