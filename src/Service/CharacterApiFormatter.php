<?php

namespace App\Service;

use App\Entity\CharacterSnapshot;
use App\Entity\UwuLogRank;

class CharacterApiFormatter
{
    public function __construct(
        private readonly ?CharacterStatCalculator $statCalculator = null,
        private readonly ?ArmoryScraperService $armoryScraperService = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function format(CharacterSnapshot $snapshot, ?UwuLogRank $uwuRank = null): array
    {
        $payload = [
            'updatedAt' => $snapshot->getScrapedAt()?->format(\DateTimeInterface::ATOM),
            'character' => [
                'name' => $snapshot->getName(),
                'realm' => $snapshot->getRealm(),
                'level' => $snapshot->getLevel(),
                'gender' => $snapshot->getGender(),
                'race' => $snapshot->getRace(),
                'class' => $snapshot->getClass(),
                'guild' => $snapshot->getGuild(),
                'gearScore' => $snapshot->getGearScore(),
                'averageItemLevel' => $snapshot->getAvgIlvl(),
            ],
            'items' => array_map($this->formatItem(...), $snapshot->getEquippedItems()),
            'professions' => array_map($this->formatProfession(...), $snapshot->getProfessions()),
            'talents' => $this->formatTalents(
                $snapshot->getSpecializations(),
                $snapshot->getTalentStrings(),
            ),
            'achievements' => $this->formatAchievementSummary($snapshot),
            'uwuLogs' => $this->formatUwuRank($uwuRank, $snapshot->getClass()),
        ];

        if ($this->statCalculator !== null) {
            $payload['stats'] = $this->formatCompactStats($snapshot);
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function formatDetailedStats(CharacterSnapshot $snapshot): array
    {
        $stats = [];
        foreach ($this->calculateStatsBySpec($snapshot) as $key => $spec) {
            $stats[$key] = [
                'loadout' => $spec['loadout'],
                'name' => $spec['name'],
                ...$spec['details'],
            ];
        }

        return [
            'updatedAt' => $snapshot->getScrapedAt()?->format(\DateTimeInterface::ATOM),
            'character' => $this->formatCharacterReference($snapshot),
            'stats' => $stats,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function formatAchievements(CharacterSnapshot $snapshot): array
    {
        $cached = $snapshot->getRaidAchievements() ?? [];
        $categoryResults = [];

        if ($this->armoryScraperService !== null) {
            foreach ($cached as $category => $earnedDates) {
                if (!is_array($earnedDates) || !ctype_digit((string) $category)) {
                    continue;
                }

                try {
                    $categoryResults[] = $this->armoryScraperService->buildRaidAchievementsFromCache(
                        $earnedDates,
                        (int) $category,
                    );
                } catch (\InvalidArgumentException) {
                    // Ignore obsolete or unsupported cached categories.
                }
            }
        }

        return [
            'updatedAt' => $snapshot->getScrapedAt()?->format(\DateTimeInterface::ATOM),
            'character' => $this->formatCharacterReference($snapshot),
            'available' => $categoryResults !== [],
            'earnedCount' => $this->countEarnedAchievements($cached),
            'cachedCategoryCount' => count($categoryResults),
            'complete' => count($categoryResults) === 8,
            'groups' => $categoryResults === [] || $this->armoryScraperService === null
                ? []
                : $this->armoryScraperService->groupRaidAchievements($categoryResults),
        ];
    }

    /** @return array<string, mixed> */
    private function formatCompactStats(CharacterSnapshot $snapshot): array
    {
        $compact = [];
        foreach ($this->calculateStatsBySpec($snapshot) as $key => $spec) {
            $calculated = $spec['details'];
            $compact[$key] = [
                'loadout' => $spec['loadout'],
                'name' => $spec['name'],
                'available' => (bool) ($calculated['available'] ?? false),
            ];

            if (!($calculated['available'] ?? false)) {
                $compact[$key]['reason'] = $calculated['reason'] ?? 'Stats are unavailable.';
                continue;
            }

            $compact[$key] += [
                'hit' => [
                    'gearPercent' => $this->ratingValues($calculated, [
                        'melee' => 'melee_hit_rating',
                        'ranged' => 'ranged_hit_rating',
                        'spell' => 'spell_hit_rating',
                    ], 'ratingPercent'),
                    'talentPercent' => $calculated['hitBonuses'] ?? [],
                ],
                'expertise' => [
                    'gearPoints' => $this->ratingValue($calculated, 'expertise_rating', 'ratingPercent'),
                    'gearReductionPercent' => round(
                        $this->ratingValue($calculated, 'expertise_rating', 'ratingPercent') * 0.25,
                        2,
                    ),
                    'talentPoints' => $this->ratingValue($calculated, 'expertise_rating', 'flatBonusPercent'),
                    'talentReductionPercent' => round(
                        $this->ratingValue($calculated, 'expertise_rating', 'flatBonusPercent') * 0.25,
                        2,
                    ),
                    'totalPoints' => $this->ratingValue($calculated, 'expertise_rating', 'value'),
                    'totalReductionPercent' => $this->ratingValue($calculated, 'expertise_rating', 'percent'),
                ],
                'criticalStrike' => $this->splitGearAndTalentPercent($calculated, [
                    'melee' => 'melee_crit_rating',
                    'ranged' => 'ranged_crit_rating',
                    'spell' => 'spell_crit_rating',
                ], true),
                'haste' => $this->splitGearAndTalentPercent($calculated, [
                    'melee' => 'melee_haste_rating',
                    'ranged' => 'ranged_haste_rating',
                    'spell' => 'spell_haste_rating',
                ]),
                'armorPenetration' => $this->splitGearAndTalentPercent($calculated, [
                    'physical' => 'armor_penetration_rating',
                ]),
            ];
        }

        return $compact;
    }

    /** @return array<string, array{loadout: int, name: string|null, details: array<string, mixed>}> */
    private function calculateStatsBySpec(CharacterSnapshot $snapshot): array
    {
        if ($this->statCalculator === null) {
            return [];
        }

        $specializations = array_values($snapshot->getSpecializations());
        $talentStrings = array_values($snapshot->getTalentStrings());
        $talentTrees = $snapshot->getTalentTreesData() ?? [];
        $count = max(1, count($specializations), count($talentStrings), count($talentTrees));
        $stats = [];

        for ($index = 0; $index < $count; ++$index) {
            $rawName = $specializations[$index] ?? null;
            $name = $rawName === null
                ? null
                : preg_replace('/\s*\([^)]*\)\s*$/', '', (string) $rawName);
            $specId = (string) $index;
            $stats['spec'.($index + 1)] = [
                'loadout' => $index + 1,
                'name' => $name,
                'details' => $this->statCalculator->calculate(
                    (string) $snapshot->getRace(),
                    (string) $snapshot->getClass(),
                    (int) $snapshot->getLevel(),
                    $snapshot->getEquippedItems(),
                    $talentTrees,
                    $specId,
                ),
            ];
        }

        return $stats;
    }

    /**
     * @param array<string, mixed> $calculated
     * @param array<string, string> $ratings
     * @return array<string, array<string, float>>
     */
    private function splitGearAndTalentPercent(array $calculated, array $ratings, bool $totalIncludesBaseStats = false): array
    {
        $values = [];
        foreach ($ratings as $label => $rating) {
            $gear = $this->ratingValue($calculated, $rating, 'ratingPercent');
            $talent = $this->ratingValue($calculated, $rating, 'flatBonusPercent');
            $total = $this->ratingValue($calculated, $rating, $totalIncludesBaseStats ? 'totalPercent' : 'value');
            $values[$label] = [
                'gearPercent' => $gear,
                'talentPercent' => $talent,
                'totalPercent' => $totalIncludesBaseStats ? $total : round($gear + $talent, 2),
            ];
            if ($totalIncludesBaseStats) {
                $values[$label]['baseAndAttributePercent'] = round($total - $gear - $talent, 2);
            }
        }

        return $values;
    }

    /**
     * @param array<string, mixed> $calculated
     * @param array<string, string> $ratings
     * @return array<string, float>
     */
    private function ratingValues(array $calculated, array $ratings, string $field): array
    {
        $values = [];
        foreach ($ratings as $label => $rating) {
            $values[$label] = $this->ratingValue($calculated, $rating, $field);
        }

        return $values;
    }

    /** @param array<string, mixed> $calculated */
    private function ratingValue(array $calculated, string $rating, string $field): float
    {
        return round((float) ($calculated['ratings'][$rating][$field] ?? 0.0), 2);
    }

    /** @return array{name: string|null, realm: string|null} */
    private function formatCharacterReference(CharacterSnapshot $snapshot): array
    {
        return ['name' => $snapshot->getName(), 'realm' => $snapshot->getRealm()];
    }

    /** @return array<string, mixed> */
    private function formatAchievementSummary(CharacterSnapshot $snapshot): array
    {
        $cached = $snapshot->getRaidAchievements() ?? [];

        return [
            'available' => $cached !== [],
            'earnedCount' => $this->countEarnedAchievements($cached),
            'cachedCategoryCount' => count($cached),
            'endpoint' => sprintf(
                '/api/character/%s/%s/achievements',
                rawurlencode((string) $snapshot->getName()),
                rawurlencode((string) $snapshot->getRealm()),
            ),
        ];
    }

    /** @param array<int|string, mixed> $cached */
    private function countEarnedAchievements(array $cached): int
    {
        return array_sum(array_map(
            static fn(mixed $achievements): int => is_array($achievements) ? count($achievements) : 0,
            $cached,
        ));
    }

    /** @return array<string, mixed>|null */
    private function formatUwuRank(?UwuLogRank $rank, ?string $className): ?array
    {
        if ($rank === null) {
            return null;
        }

        return [
            'overallRank' => $rank->getOverallRank(),
            'overallPoints' => $rank->getOverallPoints(),
            'specId' => $rank->getSpec(),
            'specName' => $rank->getSpecName($className),
            'updatedAt' => $rank->getScrapedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function formatItem(array $item): array
    {
        $gems = array_values($item['gems'] ?? []);

        return [
            'itemId' => isset($item['id']) ? (int) $item['id'] : null,
            'transmogId' => isset($item['transmog']) ? (int) $item['transmog'] : null,
            'enchantId' => isset($item['enchant']) ? (int) $item['enchant'] : null,
            'gemId1' => isset($gems[0]) ? (int) $gems[0] : null,
            'gemId2' => isset($gems[1]) ? (int) $gems[1] : null,
            'gemId3' => isset($gems[2]) ? (int) $gems[2] : null,
        ];
    }

    /**
     * @return array{professionName: string, skill: int|null, maxSkill: int|null}
     */
    private function formatProfession(mixed $profession): array
    {
        $value = (string) $profession;
        if (preg_match('/^(.+?)\s*\((\d+)(?:\s*\/\s*(\d+))?\)$/', $value, $matches) === 1) {
            return [
                'professionName' => trim($matches[1]),
                'skill' => (int) $matches[2],
                'maxSkill' => isset($matches[3]) ? (int) $matches[3] : null,
            ];
        }

        return [
            'professionName' => $value,
            'skill' => null,
            'maxSkill' => null,
        ];
    }

    /**
     * @param array<int|string, mixed> $specializations
     * @param array<int|string, mixed> $talentStrings
     * @return array<string, array{name: string|null, talentString: string|null}>
     */
    private function formatTalents(array $specializations, array $talentStrings): array
    {
        $specializations = array_values($specializations);
        $talentStrings = array_values($talentStrings);
        $count = max(count($specializations), count($talentStrings));
        $talents = [];

        for ($index = 0; $index < $count; ++$index) {
            $specialization = $specializations[$index] ?? null;
            $talentString = $talentStrings[$index] ?? null;
            $name = $specialization === null
                ? null
                : preg_replace('/\s*\([^)]*\)\s*$/', '', (string) $specialization);

            $talents['spec'.($index + 1)] = [
                'name' => $name,
                'talentString' => $talentString === null ? null : (string) $talentString,
            ];
        }

        return $talents;
    }
}
