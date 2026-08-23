<?php

namespace App\Service;

final class CharacterStatCalculator
{
    private const PRIMARY_STAT_TYPES = [
        3 => 'agility', 4 => 'strength', 5 => 'intellect', 6 => 'spirit', 7 => 'stamina',
    ];

    /** @var array<int, string[]> */
    private const ITEM_STAT_TYPES = [
        12 => ['defense_rating'], 13 => ['dodge_rating'], 14 => ['parry_rating'],
        15 => ['block_rating'], 16 => ['melee_hit_rating'], 17 => ['ranged_hit_rating'],
        18 => ['spell_hit_rating'], 19 => ['melee_crit_rating'], 20 => ['ranged_crit_rating'],
        21 => ['spell_crit_rating'], 28 => ['melee_haste_rating'], 29 => ['ranged_haste_rating'],
        30 => ['spell_haste_rating'],
        31 => ['melee_hit_rating', 'ranged_hit_rating', 'spell_hit_rating'],
        32 => ['melee_crit_rating', 'ranged_crit_rating', 'spell_crit_rating'],
        35 => ['resilience_rating'],
        36 => ['melee_haste_rating', 'ranged_haste_rating', 'spell_haste_rating'],
        37 => ['expertise_rating'], 38 => ['attack_power'], 39 => ['ranged_attack_power'],
        43 => ['mana_per_5'], 44 => ['armor_penetration_rating'], 45 => ['spell_power'],
        47 => ['spell_penetration'], 48 => ['block_value'],
    ];

    private const DISPLAY_NAMES = [
        'strength' => 'Strength', 'agility' => 'Agility', 'stamina' => 'Stamina',
        'intellect' => 'Intellect', 'spirit' => 'Spirit', 'armor' => 'Armor',
        'attack_power' => 'Attack Power', 'ranged_attack_power' => 'Ranged Attack Power',
        'spell_power' => 'Spell Power', 'mana_per_5' => 'Mana per 5',
        'spell_penetration' => 'Spell Penetration', 'block_value' => 'Block Value',
    ];

    /**
     * Permanent passive talent effects that change character-sheet attributes.
     * Values are per allocated point; conditional forms, buffs and procs are intentionally omitted.
     *
     * @var array<int, array{name: string, percent: array<string, float>}>
     */
    private const TALENT_EFFECTS = [
        20262 => ['name' => 'Divine Strength', 'percent' => ['strength' => 0.03]],
        20257 => ['name' => 'Divine Intellect', 'percent' => ['intellect' => 0.02]],
        19583 => ['name' => 'Endurance Training', 'percent' => ['stamina' => 0.01]],
        34475 => ['name' => 'Combat Experience', 'percent' => ['agility' => 0.02, 'intellect' => 0.02]],
        19168 => ['name' => 'Lightning Reflexes', 'percent' => ['agility' => 0.03]],
        31216 => ['name' => 'Sinister Calling', 'percent' => ['agility' => 0.03]],
        18551 => ['name' => 'Mental Strength', 'percent' => ['intellect' => 0.03]],
        14901 => ['name' => 'Enlightenment', 'percent' => ['stamina' => 0.02, 'spirit' => 0.02]],
        29593 => ['name' => 'Vitality', 'percent' => ['strength' => 0.02, 'stamina' => 0.03]],
        48978 => ['name' => 'Ravenous Dead', 'percent' => ['strength' => 0.01]],
        49471 => ['name' => 'Veteran of the Third War', 'percent' => ['strength' => 0.02, 'stamina' => 0.01]],
        49137 => ['name' => 'Endless Winter', 'percent' => ['strength' => 0.02]],
        17485 => ['name' => 'Ancestral Knowledge', 'percent' => ['intellect' => 0.02]],
        30864 => ['name' => 'Toughness', 'percent' => ['stamina' => 0.02]],
        11232 => ['name' => 'Arcane Mind', 'percent' => ['intellect' => 0.03]],
        18697 => ['name' => 'Demonic Embrace', 'percent' => ['stamina' => 0.03, 'spirit' => -0.01]],
        18731 => ['name' => 'Fel Vitality', 'percent' => ['stamina' => 0.01, 'intellect' => 0.01, 'spirit' => 0.01]],
        33851 => ['name' => 'Survival of the Fittest', 'percent' => ['strength' => 0.02, 'agility' => 0.02, 'stamina' => 0.02, 'intellect' => 0.02, 'spirit' => 0.02]],
        34151 => ['name' => 'Living Spirit', 'percent' => ['spirit' => 0.05]],
        17003 => ['name' => 'Heart of the Wild', 'percent' => ['intellect' => 0.04]],
    ];

    /** The armory links the currently learned rank rather than always linking rank one. */
    private const TALENT_RANK_ALIASES = [
        20263 => 20262, 20264 => 20262, 20265 => 20262, 20266 => 20262,
        20258 => 20257, 20259 => 20257, 20260 => 20257, 20261 => 20257,
        19584 => 19583, 19585 => 19583, 19586 => 19583, 19587 => 19583,
        34476 => 34475,
        19180 => 19168, 19181 => 19168, 24296 => 19168, 24297 => 19168,
        31217 => 31216, 31218 => 31216, 31219 => 31216, 31220 => 31216,
        18552 => 18551, 18553 => 18551, 18554 => 18551, 18555 => 18551,
        14909 => 14901, 15017 => 14901,
        29594 => 29593, 29595 => 29593,
        48979 => 48978, 48980 => 48978,
        49472 => 49471, 49473 => 49471,
        49657 => 49137,
        17486 => 17485, 17487 => 17485, 17488 => 17485, 17489 => 17485,
        30865 => 30864, 30866 => 30864, 30867 => 30864, 30868 => 30864,
        12500 => 11232, 12501 => 11232, 12502 => 11232, 12503 => 11232,
        18698 => 18697, 18699 => 18697, 18700 => 18697, 18701 => 18697,
        18732 => 18731, 18733 => 18731,
        33852 => 33851, 33853 => 33851,
        34152 => 34151, 34153 => 34151,
        17004 => 17003, 17005 => 17003, 17006 => 17003, 17007 => 17003,
    ];

    /** Rating required for one percentage point at anchor levels. */
    private const RATING_ANCHORS = [
        'melee_hit_rating' => [60 => 10.0, 70 => 15.769, 80 => 32.789989],
        'ranged_hit_rating' => [60 => 10.0, 70 => 15.769, 80 => 32.789989],
        'spell_hit_rating' => [60 => 8.0, 70 => 12.615, 80 => 26.232],
        'melee_crit_rating' => [60 => 14.0, 70 => 22.077, 80 => 45.905986],
        'ranged_crit_rating' => [60 => 14.0, 70 => 22.077, 80 => 45.905986],
        'spell_crit_rating' => [60 => 14.0, 70 => 22.077, 80 => 45.905986],
        'melee_haste_rating' => [60 => 10.0, 70 => 15.769, 80 => 32.789989],
        'ranged_haste_rating' => [60 => 10.0, 70 => 15.769, 80 => 32.789989],
        'spell_haste_rating' => [60 => 10.0, 70 => 15.769, 80 => 32.789989],
        'defense_rating' => [60 => 37.5, 70 => 59.134, 80 => 122.9625],
        'dodge_rating' => [60 => 12.0, 70 => 18.923, 80 => 39.34799],
        'parry_rating' => [60 => 13.8, 70 => 21.759, 80 => 45.25019],
        'block_rating' => [60 => 5.0, 70 => 7.885, 80 => 16.394995],
        'resilience_rating' => [60 => 25.0, 70 => 39.423, 80 => 82.0],
        'expertise_rating' => [60 => 10.0, 70 => 15.769, 80 => 32.789989],
        'armor_penetration_rating' => [60 => 4.269, 70 => 6.731, 80 => 13.995727],
    ];

    private const RATING_NAMES = [
        'melee_hit_rating' => 'Melee Hit', 'ranged_hit_rating' => 'Ranged Hit',
        'spell_hit_rating' => 'Spell Hit', 'melee_crit_rating' => 'Melee Critical Strike',
        'ranged_crit_rating' => 'Ranged Critical Strike', 'spell_crit_rating' => 'Spell Critical Strike',
        'melee_haste_rating' => 'Melee Haste', 'ranged_haste_rating' => 'Ranged Haste',
        'spell_haste_rating' => 'Spell Haste', 'defense_rating' => 'Defense avoidance (each)',
        'dodge_rating' => 'Dodge', 'parry_rating' => 'Parry', 'block_rating' => 'Block',
        'resilience_rating' => 'Critical damage reduction',
        'expertise_rating' => 'Dodge/parry reduction',
        'armor_penetration_rating' => 'Armor Penetration',
    ];

    public function __construct(
        private readonly WotlkBaseStatTable $baseStatTable = new WotlkBaseStatTable(),
        private readonly ?ItemDatabaseService $itemDatabaseService = null,
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $items Raw or paperdoll-enriched equipped items.
     * @param array<int|string, mixed> $talentTreesData
     * @return array<string, mixed>
     */
    public function calculate(
        string $race,
        string $class,
        int $level,
        array $items,
        array $talentTreesData = [],
        int|string $specId = 0,
    ): array {
        $level = max(1, min(80, $level));
        $base = $this->baseStatTable->getBreakdown($race, $class, $level);
        if ($base === null) {
            return [
                'available' => false,
                'reason' => 'Base stat data is not available for this race or class.',
                'level' => $level,
            ];
        }

        $items = $this->enrichItems($items);
        $gear = $this->emptyStatTotals();
        $gearSources = ['items' => [], 'enchants' => [], 'gems' => [], 'socketBonuses' => []];

        foreach ($items as $item) {
            $itemStats = $this->statsFromItem($item);
            $this->addTotals($gear, $itemStats);
            if (array_sum(array_map('abs', $itemStats)) > 0) {
                $gearSources['items'][] = ['name' => (string) ($item['name'] ?? 'Unknown item'), 'stats' => $this->nonZero($itemStats)];
            }

            $enchantText = (string) ($item['enchant_name'] ?? '');
            if ($enchantText === '' && (int) ($item['enchant'] ?? 0) > 0) {
                $enchantText = EnchantDatabase::ENCHANTS[(int) $item['enchant']] ?? '';
            }
            $enchantStats = $this->statsFromText($enchantText);
            $this->addTotals($gear, $enchantStats);
            if ($enchantStats !== []) {
                $gearSources['enchants'][] = ['name' => $enchantText, 'stats' => $enchantStats];
            }

            $gemDetails = $item['gem_details'] ?? [];
            if ($gemDetails === []) {
                foreach ($item['gems'] ?? [] as $gemEnchantId) {
                    $gemDetails[] = ['effect' => EnchantDatabase::ENCHANTS[(int) $gemEnchantId] ?? ''];
                }
            }
            foreach ($gemDetails as $gem) {
                if (!is_array($gem)) {
                    continue;
                }
                $gemText = (string) ($gem['effect'] ?? $gem['name'] ?? '');
                $gemStats = $this->statsFromText($gemText);
                $this->addTotals($gear, $gemStats);
                if ($gemStats !== []) {
                    $gearSources['gems'][] = ['name' => (string) ($gem['name'] ?? $gemText), 'stats' => $gemStats];
                }
            }

            $socketBonus = $item['display_tooltip']['socket_bonus'] ?? $item['socket_bonus'] ?? null;
            if (is_array($socketBonus) && ($socketBonus['active'] ?? false)) {
                $bonusText = (string) ($socketBonus['text'] ?? '');
                $bonusStats = $this->statsFromText($bonusText);
                $this->addTotals($gear, $bonusStats);
                if ($bonusStats !== []) {
                    $gearSources['socketBonuses'][] = ['name' => $bonusText, 'stats' => $bonusStats];
                }
            }
        }

        $talents = $this->talentModifiers($talentTreesData[(string) $specId] ?? $talentTreesData[$specId] ?? []);
        $primary = [];
        foreach (WotlkBaseStatTable::STAT_KEYS as $stat) {
            $beforeTalents = $base['total'][$stat] + ($gear[$stat] ?? 0);
            $talentValue = (int) floor($beforeTalents * ($talents['percent'][$stat] ?? 0.0));
            $primary[$stat] = [
                'name' => self::DISPLAY_NAMES[$stat],
                'race' => $base['race'][$stat],
                'class' => $base['class'][$stat],
                'level' => $base['level'][$stat],
                'gear' => $gear[$stat] ?? 0,
                'talents' => $talentValue,
                'total' => $beforeTalents + $talentValue,
            ];
        }

        $ratings = [];
        foreach (self::RATING_ANCHORS as $stat => $anchors) {
            $rating = (int) ($gear[$stat] ?? 0);
            if ($rating === 0) {
                continue;
            }
            $perPercent = $this->ratingPerPercent($anchors, $level);
            $ratings[$stat] = [
                'name' => self::RATING_NAMES[$stat],
                'rating' => $rating,
                'ratingPerPercent' => round($perPercent, 3),
                'percent' => round($rating / $perPercent, 2),
            ];
        }

        $secondary = [];
        foreach (self::DISPLAY_NAMES as $stat => $name) {
            if (in_array($stat, WotlkBaseStatTable::STAT_KEYS, true) || (int) ($gear[$stat] ?? 0) === 0) {
                continue;
            }
            $secondary[$stat] = ['name' => $name, 'value' => (int) $gear[$stat]];
        }

        return [
            'available' => true,
            'level' => $level,
            'primary' => $primary,
            'secondary' => $secondary,
            'ratings' => $ratings,
            'talentModifiers' => $talents['effects'],
            'gearBreakdown' => $gearSources,
            'notes' => [
                'Permanent passive talent modifiers are included for the selected specialization.',
                'Temporary buffs, stances/forms, procs, consumables and conditional set effects are excluded.',
            ],
        ];
    }

    /** @param array<int, array<string, mixed>> $items */
    private function enrichItems(array $items): array
    {
        if ($this->itemDatabaseService === null) {
            return $items;
        }

        $ids = array_values(array_filter(array_map(static fn (array $item): int => (int) ($item['id'] ?? 0), $items)));
        $databaseItems = $this->itemDatabaseService->getItemsBulk($ids);
        foreach ($items as &$item) {
            $databaseItem = $databaseItems[(int) ($item['id'] ?? 0)] ?? [];
            $item = array_replace($databaseItem, $item);
        }
        unset($item);

        return $items;
    }

    /** @param array<string, mixed> $item @return array<string, int> */
    private function statsFromItem(array $item): array
    {
        $totals = [];
        $raw = $item['tooltip'] ?? [];
        if (!is_array($raw)) {
            return $totals;
        }

        foreach ($raw['stats'] ?? [] as $stat) {
            $type = (int) ($stat['type'] ?? 0);
            $value = (int) ($stat['value'] ?? 0);
            if (isset(self::PRIMARY_STAT_TYPES[$type])) {
                $totals[self::PRIMARY_STAT_TYPES[$type]] = ($totals[self::PRIMARY_STAT_TYPES[$type]] ?? 0) + $value;
            }
            foreach (self::ITEM_STAT_TYPES[$type] ?? [] as $key) {
                $totals[$key] = ($totals[$key] ?? 0) + $value;
            }
        }
        if ((int) ($raw['armor'] ?? 0) !== 0) {
            $totals['armor'] = (int) $raw['armor'];
        }
        if ((int) ($raw['block'] ?? 0) !== 0) {
            $totals['block_value'] = ($totals['block_value'] ?? 0) + (int) $raw['block'];
        }

        return $totals;
    }

    /** @return array<string, int> */
    private function statsFromText(string $text): array
    {
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5);
        if (trim($text) === '') {
            return [];
        }

        $totals = [];
        if (preg_match_all('/([+-]?\d+)\s+(?:to\s+)?(?:all\s+)?stats?\b/i', $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                foreach (WotlkBaseStatTable::STAT_KEYS as $stat) {
                    $totals[$stat] = ($totals[$stat] ?? 0) + (int) $match[1];
                }
            }
        }

        $aliases = [
            'strength' => 'strength', 'agility' => 'agility', 'stamina' => 'stamina',
            'intellect' => 'intellect', 'spirit' => 'spirit', 'armor' => 'armor',
            'attack power' => 'attack_power', 'ap' => 'attack_power',
            'ranged attack power' => 'ranged_attack_power', 'rap' => 'ranged_attack_power',
            'spell power' => 'spell_power', 'sp' => 'spell_power', 'mana per 5' => 'mana_per_5',
            'mp5' => 'mana_per_5', 'spell penetration' => 'spell_penetration',
            'block value' => 'block_value', 'defense rating' => 'defense_rating',
            'dodge rating' => 'dodge_rating', 'parry rating' => 'parry_rating',
            'block rating' => 'block_rating', 'hit rating' => 'all_hit_rating',
            'critical strike rating' => 'all_crit_rating', 'crit rating' => 'all_crit_rating',
            'haste rating' => 'all_haste_rating', 'resilience rating' => 'resilience_rating',
            'expertise rating' => 'expertise_rating', 'armor penetration rating' => 'armor_penetration_rating',
        ];
        uksort($aliases, static fn (string $left, string $right): int => strlen($right) <=> strlen($left));
        $pattern = '/([+-]?\d+)\s+(?:to\s+)?('.implode('|', array_map(static fn (string $name): string => preg_quote($name, '/'), array_keys($aliases))).')\b/i';
        if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $key = $aliases[strtolower($match[2])];
                $value = (int) $match[1];
                $targets = match ($key) {
                    'all_hit_rating' => ['melee_hit_rating', 'ranged_hit_rating', 'spell_hit_rating'],
                    'all_crit_rating' => ['melee_crit_rating', 'ranged_crit_rating', 'spell_crit_rating'],
                    'all_haste_rating' => ['melee_haste_rating', 'ranged_haste_rating', 'spell_haste_rating'],
                    default => [$key],
                };
                foreach ($targets as $target) {
                    $totals[$target] = ($totals[$target] ?? 0) + $value;
                }
            }
        }

        return $this->nonZero($totals);
    }

    /** @param mixed $rawSpec @return array{percent: array<string, float>, effects: array<int, array<string, mixed>>} */
    private function talentModifiers(mixed $rawSpec): array
    {
        $percent = [];
        $effects = [];
        if (!is_array($rawSpec)) {
            return ['percent' => [], 'effects' => []];
        }

        foreach ($rawSpec as $tree) {
            foreach (($tree['tiers'] ?? []) as $tier) {
                foreach ($tier as $talent) {
                    if (!is_array($talent)) {
                        continue;
                    }
                    $spellId = (int) ($talent['spellId'] ?? 0);
                    $spellId = self::TALENT_RANK_ALIASES[$spellId] ?? $spellId;
                    $definition = self::TALENT_EFFECTS[$spellId] ?? null;
                    $points = preg_match('/^(\d+)\s*\//', (string) ($talent['pointsText'] ?? ''), $match) ? (int) $match[1] : 0;
                    if ($definition === null || $points === 0) {
                        continue;
                    }
                    $applied = [];
                    foreach ($definition['percent'] as $stat => $perPoint) {
                        $amount = $perPoint * $points;
                        $percent[$stat] = ($percent[$stat] ?? 0.0) + $amount;
                        $applied[$stat] = round($amount * 100, 2);
                    }
                    $effects[] = ['name' => $definition['name'], 'points' => $points, 'percent' => $applied];
                }
            }
        }

        return ['percent' => $percent, 'effects' => $effects];
    }

    /** @param array<int, float> $anchors */
    private function ratingPerPercent(array $anchors, int $level): float
    {
        if ($level <= 60) {
            return max(0.1, $anchors[60] * ($level / 60));
        }
        if ($level <= 70) {
            return $anchors[60] + (($anchors[70] - $anchors[60]) * (($level - 60) / 10));
        }

        return $anchors[70] + (($anchors[80] - $anchors[70]) * (($level - 70) / 10));
    }

    /** @return array<string, int> */
    private function emptyStatTotals(): array
    {
        return array_fill_keys([...WotlkBaseStatTable::STAT_KEYS, ...array_keys(self::DISPLAY_NAMES), ...array_keys(self::RATING_ANCHORS)], 0);
    }

    /** @param array<string, int> $target @param array<string, int> $addition */
    private function addTotals(array &$target, array $addition): void
    {
        foreach ($addition as $stat => $value) {
            $target[$stat] = ($target[$stat] ?? 0) + $value;
        }
    }

    /** @param array<string, int> $totals @return array<string, int> */
    private function nonZero(array $totals): array
    {
        return array_filter($totals, static fn (int $value): bool => $value !== 0);
    }
}
