<?php

namespace App\Service\ItemTooltipProvider;

interface ItemTooltipProviderInterface
{
    public function getName(): string;

    public function getPriority(): int;

    /**
     * @return array{
     *     provider: string,
     *     source_url: string,
     *     raw_response: string,
     *     effects: array<int, array{spell_id: int|null, trigger_type: string, description: string}>,
     *     set: array{name: string, members: array<int, array{item_id: int, name: string}>, bonuses: array<int, array{required_count: int, spell_id: int|null, description: string}>}|null
     * }
     */
    public function fetch(int $itemId, string $locale = 'enUS'): array;
}
