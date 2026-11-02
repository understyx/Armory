<?php

namespace App\Service;

use App\Enum\ItemTypes;

final class CharacterHitExpertiseService
{
    private const MELEE_WEAPON_TYPES = [
        ItemTypes::WEAPON_1H->value,
        ItemTypes::WEAPON_2H->value,
        ItemTypes::WEAPON_MAINHAND->value,
        ItemTypes::WEAPON_OFFHAND->value,
    ];

    private const RANGED_WEAPON_TYPES = [
        ItemTypes::BOW->value,
        ItemTypes::THROWN->value,
        ItemTypes::RANGED->value,
    ];

    // TrinityCore truncates expertise rating to whole expertise points.
    // https://wowpedia.fandom.com/wiki/Combat_rating_system
    private const EXPERTISE_RATING_AT_LEVEL_80 = 8.197497;

    /** @var array<int, int> Expertise per allocated point, normalized by talent rank spell ID. */
    private const EXPERTISE_TALENT_POINTS = [
        31858 => 2, // Paladin Combat Expertise: https://wotlkdb.com/?spell=31858
        48409 => 5, // Druid Primal Precision: https://wotlk.evowow.com/?spell=48410
        30919 => 5, // Rogue Weapon Expertise: https://wotlkdb.com/?spell=30920
        46865 => 2, // Warrior Strength of Arms: https://wotlkdb.com/?spell=46866
        29140 => 2, // Warrior Vitality: https://wotlkdb.com/?spell=29144
        // Each rank adds 3 expertise; the area attack-power aura is excluded.
        // https://wotlk.evowow.com/?spell=30809
        30802 => 3,
        // Veteran rank effect spells are 49006/49526/50029. Armory talent links use 49471-49473.
        // https://wotlk.evowow.com/?spell=50029
        49006 => 2,
        49471 => 2,
        // Tundra's Frost Fever requirement applies to its damage bonus only; expertise is a separate aura.
        // https://wotlk.evowow.com/?spell=50130
        49202 => 1,
        // Rage of Rivendare's Blood Plague requirement applies to its damage bonus only.
        // https://wotlk.evowow.com/?spell=50121
        50117 => 1,
    ];

    /** @var array<int, int> */
    private const EXPERTISE_TALENT_RANK_ALIASES = [
        31859 => 31858, 31860 => 31858,
        48410 => 48409,
        30920 => 30919,
        46866 => 46865,
        29141 => 29140, 29144 => 29140,
        30808 => 30802, 30809 => 30802,
        49526 => 49006, 50029 => 49006,
        49472 => 49471, 49473 => 49471,
        50127 => 49202, 50128 => 49202, 50129 => 49202, 50130 => 49202,
        50118 => 50117, 50119 => 50117, 50120 => 50117, 50121 => 50117,
    ];

    public function __construct(private readonly CharacterStatCalculator $statCalculator)
    {
    }

    /**
     * @param array<int, array<string, mixed>> $parsedSpecs
     * @param array<int, array<string, mixed>> $items Paperdoll-enriched equipment.
     * @param array<int|string, mixed> $talentTreesData
     * @return list<array<string, mixed>>
     */
    public function build(
        string $race,
        string $class,
        int $level,
        array $parsedSpecs,
        array $items,
        array $talentTreesData,
    ): array {
        if ($level < 80 || $parsedSpecs === [] || $talentTreesData === []) {
            return [];
        }

        $rows = [];
        foreach ($parsedSpecs as $spec) {
            $specName = trim((string) ($spec['specName'] ?? ''));
            $role = $this->roleFor($class, $specName);
            if ($role === null) {
                continue;
            }

            $specId = (string) ($spec['specId'] ?? '0');
            $rawSpec = $talentTreesData[$specId] ?? $talentTreesData[(int) $specId] ?? null;
            if (!$this->hasTalentData($rawSpec, $spec)) {
                continue;
            }

            $weaponItems = $this->weaponItems($items, $role);
            if ($weaponItems === []) {
                continue;
            }

            $stats = $this->statCalculator->calculate(
                $race,
                $class,
                $level,
                $items,
                $talentTreesData,
                $specId,
            );
            if (!($stats['available'] ?? false)) {
                continue;
            }

            $hitStat = match ($role) {
                'melee' => 'melee_hit_rating',
                'ranged' => 'ranged_hit_rating',
                default => 'spell_hit_rating',
            };
            $hitTarget = $role === 'caster' ? 17.0 : 8.0;
            $ratingHit = (float) ($stats['ratings'][$hitStat]['ratingPercent'] ?? 0.0);
            $talentHit = $this->relevantTalentHit(
                $class,
                $specName,
                $role,
                $stats['hitBonuses'] ?? [],
                $weaponItems,
            );
            // Draenei Heroic Presence grants personal melee, ranged and spell hit in WotLK.
            // https://www.wowhead.com/wotlk/spell=6562/heroic-presence
            $personalDraeneiHit = strcasecmp(trim($race), 'Draenei') === 0 ? 1.0 : 0.0;
            $hitValue = $ratingHit + $talentHit + $personalDraeneiHit;

            $row = [
                'specName' => $specName,
                'specLabel' => (string) ($spec['label'] ?? ''),
                'role' => $role,
                'hitLabel' => match ($role) {
                    'melee' => 'Melee Hit',
                    'ranged' => 'Ranged Hit',
                    default => 'Spell Hit',
                },
                'hitValue' => round($hitValue, 2),
                'hitTarget' => $hitTarget,
                'hitMet' => $hitValue + 0.000001 >= $hitTarget,
                'hitTooltip' => $this->hitTooltip($class, $specName, $role),
            ];

            if ($role === 'melee') {
                $expertiseRating = (int) ($stats['ratings']['expertise_rating']['rating'] ?? 0);
                $expertise = (int) floor($expertiseRating / self::EXPERTISE_RATING_AT_LEVEL_80)
                    + $this->talentExpertise($rawSpec)
                    + $this->weaponRacialExpertise($race, $weaponItems);
                $row['expertise'] = $expertise;
                $row['expertiseTargets'] = [
                    ['value' => 26, 'label' => 'dodge soft cap', 'met' => $expertise >= 26],
                ];
                if ($this->isTankSpec($class, $specName)) {
                    $row['expertiseTargets'][] = [
                        'value' => 56,
                        'label' => 'parry hard cap',
                        'met' => $expertise >= 56,
                    ];
                }
                $row['expertiseTooltip'] = 'Gear expertise rating is rounded down to whole points. Passive talent expertise is included; damage bonuses with target conditions are excluded. Weapon racials use the highest eligible value among equipped melee weapons.';
            }

            $rows[] = $row;
        }

        return $rows;
    }

    private function roleFor(string $class, string $specName): ?string
    {
        $class = strtolower(trim($class));
        $spec = strtolower(trim($specName));

        return match ($class) {
            'warrior' => in_array($spec, ['arms', 'fury', 'protection'], true) ? 'melee' : null,
            'paladin' => match ($spec) {
                'protection', 'retribution' => 'melee',
                default => null,
            },
            'rogue' => in_array($spec, ['assassination', 'combat', 'subtlety'], true) ? 'melee' : null,
            'death knight' => in_array($spec, ['blood', 'frost', 'unholy'], true) ? 'melee' : null,
            'shaman' => match ($spec) {
                'enhancement' => 'melee',
                'elemental' => 'caster',
                default => null,
            },
            'druid' => match (true) {
                str_starts_with($spec, 'feral') => 'melee',
                $spec === 'balance' => 'caster',
                default => null,
            },
            'hunter' => in_array($spec, ['beast mastery', 'marksmanship', 'survival'], true) ? 'ranged' : null,
            'priest' => $spec === 'shadow' ? 'caster' : null,
            'mage' => in_array($spec, ['arcane', 'fire', 'frost'], true) ? 'caster' : null,
            'warlock' => in_array($spec, ['affliction', 'demonology', 'destruction'], true) ? 'caster' : null,
            default => null,
        };
    }

    /** @param mixed $rawSpec @param array<string, mixed> $spec */
    private function hasTalentData(mixed $rawSpec, array $spec): bool
    {
        if (!is_array($rawSpec) || $rawSpec === [] || (int) ($spec['totalPoints'] ?? 0) < 1) {
            return false;
        }

        foreach ($rawSpec as $tree) {
            if (is_array($tree) && is_array($tree['tiers'] ?? null) && $tree['tiers'] !== []) {
                return true;
            }
        }

        return false;
    }

    /** @param array<int, array<string, mixed>> $items @return array<int, array<string, mixed>> */
    private function weaponItems(array $items, string $role): array
    {
        $weaponTypes = match ($role) {
            'ranged' => self::RANGED_WEAPON_TYPES,
            'caster' => [...self::MELEE_WEAPON_TYPES, ...self::RANGED_WEAPON_TYPES],
            default => self::MELEE_WEAPON_TYPES,
        };

        return array_values(array_filter(
            $items,
            static fn (array $item): bool => (int) ($item['class'] ?? 0) === 2
                && in_array((int) ($item['type'] ?? 0), $weaponTypes, true),
        ));
    }

    /** @param array<string, float> $hitBonuses @param array<int, array<string, mixed>> $weapons */
    private function relevantTalentHit(
        string $class,
        string $specName,
        string $role,
        array $hitBonuses,
        array $weapons,
    ): float {
        $classKey = strtolower(trim($class));
        $specKey = strtolower(trim($specName));
        $oneHandedWeapon = count(array_filter(
            $weapons,
            static fn (array $item): bool => in_array((int) ($item['type'] ?? 0), [
                ItemTypes::WEAPON_1H->value,
                ItemTypes::WEAPON_MAINHAND->value,
                ItemTypes::WEAPON_OFFHAND->value,
            ], true),
        )) > 0;
        $dualWielding = count(array_filter(
            $weapons,
            static fn (array $item): bool => in_array((int) ($item['type'] ?? 0), [
                ItemTypes::WEAPON_1H->value,
                ItemTypes::WEAPON_MAINHAND->value,
                ItemTypes::WEAPON_OFFHAND->value,
            ], true),
        )) >= 2;
        $bonus = 0.0;

        foreach ($hitBonuses as $scope => $amount) {
            $applies = match ((string) $scope) {
                // Warrior Precision rank spells 29590-29592: +1% melee hit per point.
                // https://wotlk.evowow.com/?spell=29592
                'Melee weapons' => $role === 'melee' && $classKey === 'warrior',
                'Weapon and poison attacks' => $role === 'melee' && $classKey === 'rogue',
                'Melee and ranged attacks' => $role === 'ranged' && $classKey === 'hunter',
                'Attacks while dual wielding' => $role === 'melee'
                    && $classKey === 'shaman'
                    && $specKey === 'enhancement'
                    && $dualWielding,
                'One-handed melee weapons' => $role === 'melee' && $classKey === 'death knight' && $oneHandedWeapon,
                'Shadow spells' => $role === 'caster' && $classKey === 'priest' && $specKey === 'shadow',
                'Arcane spells' => $role === 'caster' && $classKey === 'mage' && $specKey === 'arcane',
                'Fire, Frost and Nature spells' => $role === 'caster' && $classKey === 'shaman' && $specKey === 'elemental',
                'Spells' => match ($classKey) {
                    'mage' => $role === 'caster', // Mage Precision applies to all spell schools.
                    // In 3.1, Suppression changed from Affliction-only to all spells.
                    // https://warcraft.wiki.gg/wiki/Suppression_(warlock_talent)
                    'warlock' => $role === 'caster',
                    'druid' => $role === 'caster' && $specKey === 'balance',
                    default => false,
                },
                default => false,
            };
            if ($applies) {
                $bonus += (float) $amount;
            }
        }

        return $bonus;
    }

    private function talentExpertise(mixed $rawSpec): int
    {
        if (!is_array($rawSpec)) {
            return 0;
        }

        $expertise = 0;
        foreach ($rawSpec as $tree) {
            foreach (($tree['tiers'] ?? []) as $tier) {
                foreach ($tier as $talent) {
                    if (!is_array($talent)) {
                        continue;
                    }

                    $linkedSpellId = (int) ($talent['spellId'] ?? 0);
                    $spellId = self::EXPERTISE_TALENT_RANK_ALIASES[$linkedSpellId] ?? $linkedSpellId;
                    $points = preg_match('/^(\d+)\s*\//', (string) ($talent['pointsText'] ?? ''), $match)
                        ? (int) $match[1]
                        : (int) ($talent['allocated'] ?? 0);
                    $expertise += (self::EXPERTISE_TALENT_POINTS[$spellId] ?? 0) * $points;
                }
            }
        }

        return $expertise;
    }

    /** @param array<int, array<string, mixed>> $weapons */
    private function weaponRacialExpertise(string $race, array $weapons): int
    {
        // WotLK weapon racials are conditional on the equipped weapon subclass.
        // https://www.wowhead.com/wotlk/spell=20597/sword-specialization
        // https://www.wowhead.com/wotlk/spell=20864/mace-specialization
        // https://www.wowhead.com/wotlk/spell=59224/mace-specialization
        // https://www.wowhead.com/wotlk/spell=20574/axe-specialization
        $raceKey = strtolower(trim($race));
        $expertise = 0;
        foreach ($weapons as $weapon) {
            $subclass = (int) ($weapon['subclass'] ?? -1);
            $weaponExpertise = match (true) {
                $raceKey === 'human' && in_array($subclass, [4, 5, 7, 8], true) => 3,
                $raceKey === 'dwarf' && in_array($subclass, [4, 5], true) => 5,
                $raceKey === 'orc' && in_array($subclass, [0, 1], true) => 5,
                default => 0,
            };
            $expertise = max($expertise, $weaponExpertise);
        }

        return $expertise;
    }

    private function isTankSpec(string $class, string $specName): bool
    {
        $classKey = strtolower(trim($class));
        $specKey = strtolower(trim($specName));

        return ($classKey === 'warrior' && $specKey === 'protection')
            || ($classKey === 'paladin' && $specKey === 'protection')
            || ($classKey === 'death knight' && $specKey === 'blood')
            || ($classKey === 'druid' && str_starts_with($specKey, 'feral'));
    }

    private function hitTooltip(string $class, string $specName, string $role): string
    {
        if ($role !== 'caster') {
            return 'Includes hit rating from gear, gems, enchants, applicable talents, and your personal Heroic Presence if Draenei. Party buffs and target debuffs are excluded.';
        }

        $classKey = strtolower(trim($class));
        $specKey = strtolower(trim($specName));
        $school = match (true) {
            $classKey === 'mage' => ucfirst($specKey),
            $classKey === 'warlock' && $specKey === 'affliction' => 'Shadow',
            $classKey === 'warlock' && $specKey === 'demonology' => 'Fire and Shadow',
            $classKey === 'warlock' => 'Fire',
            $classKey === 'priest' => 'Shadow',
            $classKey === 'shaman' => 'Fire, Frost, and Nature',
            default => 'Nature',
        };

        return sprintf(
            '%s specialization spells. Includes hit rating from gear, gems, enchants, applicable talents, and your personal Heroic Presence if Draenei. Party Heroic Presence and Misery/Improved Faerie Fire target debuffs are excluded.',
            $school,
        );
    }
}
