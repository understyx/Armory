<?php

namespace App\Service;

/**
 * Evaluates TBC and WotLK meta-gem activation requirements against equipped gems.
 */
class MetaGemRequirementChecker
{
    public const RED = 2;
    public const YELLOW = 4;
    public const BLUE = 8;

    /**
     * Gem enchant ID => activation rule.
     *
     * "minimum" rules require at least the listed number of each colour. "more"
     * rules are the old TBC strict comparisons (for example, red > blue).
     *
     * @var array<int, array{name: string, label: string, minimum?: array<string, int>, more?: array{0: string, 1: string}}>
     */
    private const REQUIREMENTS = [
        // The Burning Crusade
        2827 => ['name' => 'Destructive Skyfire Diamond', 'label' => '2 red, 2 yellow, 2 blue', 'minimum' => ['red' => 2, 'yellow' => 2, 'blue' => 2]],
        2828 => ['name' => 'Mystical Skyfire Diamond', 'label' => 'more blue than yellow', 'more' => ['blue', 'yellow']],
        2829 => ['name' => 'Swift Skyfire Diamond', 'label' => '1 red, 2 yellow', 'minimum' => ['red' => 1, 'yellow' => 2]],
        2830 => ['name' => 'Enigmatic Skyfire Diamond', 'label' => 'more red than yellow', 'more' => ['red', 'yellow']],
        2831 => ['name' => 'Powerful Earthstorm Diamond', 'label' => '3 blue', 'minimum' => ['blue' => 3]],
        2832 => ['name' => 'Bracing Earthstorm Diamond', 'label' => 'more red than blue', 'more' => ['red', 'blue']],
        2833 => ['name' => 'Tenacious Earthstorm Diamond', 'label' => '5 blue', 'minimum' => ['blue' => 5]],
        2834 => ['name' => 'Brutal Earthstorm Diamond', 'label' => '2 red, 2 yellow, 2 blue', 'minimum' => ['red' => 2, 'yellow' => 2, 'blue' => 2]],
        2835 => ['name' => 'Insightful Earthstorm Diamond', 'label' => '2 red, 2 yellow, 2 blue', 'minimum' => ['red' => 2, 'yellow' => 2, 'blue' => 2]],
        2969 => ['name' => 'Swift Windfire Diamond', 'label' => '1 red, 2 yellow', 'minimum' => ['red' => 1, 'yellow' => 2]],
        2970 => ['name' => 'Swift Starfire Diamond', 'label' => '1 red, 2 yellow', 'minimum' => ['red' => 1, 'yellow' => 2]],
        3154 => ['name' => 'Relentless Earthstorm Diamond', 'label' => '2 red, 2 yellow, 2 blue', 'minimum' => ['red' => 2, 'yellow' => 2, 'blue' => 2]],
        3155 => ['name' => 'Thundering Skyfire Diamond', 'label' => '2 red, 2 yellow, 2 blue', 'minimum' => ['red' => 2, 'yellow' => 2, 'blue' => 2]],
        3162 => ['name' => 'Potent Unstable Diamond', 'label' => 'more blue than yellow', 'more' => ['blue', 'yellow']],
        3163 => ['name' => 'Imbued Unstable Diamond', 'label' => '3 yellow', 'minimum' => ['yellow' => 3]],
        3261 => ['name' => 'Chaotic Skyfire Diamond', 'label' => '2 blue', 'minimum' => ['blue' => 2]],
        3274 => ['name' => 'Eternal Earthstorm Diamond', 'label' => '1 yellow, 2 blue', 'minimum' => ['yellow' => 1, 'blue' => 2]],
        3275 => ['name' => 'Ember Skyfire Diamond', 'label' => '3 red', 'minimum' => ['red' => 3]],

        // Wrath of the Lich King
        3621 => ['name' => 'Chaotic Skyflare Diamond', 'label' => '3 red', 'minimum' => ['red' => 3]],
        3622 => ['name' => 'Destructive Skyflare Diamond', 'label' => '1 red, 1 yellow, 1 blue', 'minimum' => ['red' => 1, 'yellow' => 1, 'blue' => 1]],
        3623 => ['name' => 'Ember Skyflare Diamond', 'label' => '3 red', 'minimum' => ['red' => 3]],
        3624 => ['name' => 'Enigmatic Skyflare Diamond', 'label' => '2 red, 1 yellow', 'minimum' => ['red' => 2, 'yellow' => 1]],
        3625 => ['name' => 'Swift Skyflare Diamond', 'label' => '1 red, 2 yellow', 'minimum' => ['red' => 1, 'yellow' => 2]],
        3626 => ['name' => 'Bracing Earthsiege Diamond', 'label' => '2 red, 1 blue', 'minimum' => ['red' => 2, 'blue' => 1]],
        3627 => ['name' => 'Insightful Earthsiege Diamond', 'label' => '1 red, 1 yellow, 1 blue', 'minimum' => ['red' => 1, 'yellow' => 1, 'blue' => 1]],
        3628 => ['name' => 'Relentless Earthsiege Diamond', 'label' => '1 red, 1 yellow, 1 blue', 'minimum' => ['red' => 1, 'yellow' => 1, 'blue' => 1]],
        // Some WotLK data sets use 3629 for Enigmatic instead of 3624.
        3629 => ['name' => 'Enigmatic Skyflare Diamond', 'label' => '2 red, 1 yellow', 'minimum' => ['red' => 2, 'yellow' => 1]],
        3631 => ['name' => 'Eternal Earthsiege Diamond', 'label' => '2 red, 1 blue', 'minimum' => ['red' => 2, 'blue' => 1]],
        3632 => ['name' => 'Tireless Skyflare Diamond', 'label' => '1 red, 1 yellow, 1 blue', 'minimum' => ['red' => 1, 'yellow' => 1, 'blue' => 1]],
        3633 => ['name' => 'Revitalizing Skyflare Diamond', 'label' => '2 red', 'minimum' => ['red' => 2]],
        3634 => ['name' => 'Effulgent Skyflare Diamond', 'label' => '1 red, 2 blue', 'minimum' => ['red' => 1, 'blue' => 2]],
        3635 => ['name' => 'Forlorn Skyflare Diamond', 'label' => '2 yellow, 1 blue', 'minimum' => ['yellow' => 2, 'blue' => 1]],
        3636 => ['name' => 'Impassive Skyflare Diamond', 'label' => '2 red, 1 blue', 'minimum' => ['red' => 2, 'blue' => 1]],
        3637 => ['name' => 'Austere Earthsiege Diamond', 'label' => '1 red, 2 blue', 'minimum' => ['red' => 1, 'blue' => 2]],
        3638 => ['name' => 'Persistent Earthsiege Diamond', 'label' => '2 yellow, 1 blue', 'minimum' => ['yellow' => 2, 'blue' => 1]],
        3639 => ['name' => 'Trenchant Earthsiege Diamond', 'label' => '1 red, 1 yellow, 1 blue', 'minimum' => ['red' => 1, 'yellow' => 1, 'blue' => 1]],
        3640 => ['name' => 'Invigorating Earthsiege Diamond', 'label' => '1 red, 2 blue', 'minimum' => ['red' => 1, 'blue' => 2]],
        3641 => ['name' => 'Beaming Earthsiege Diamond', 'label' => '2 red, 1 yellow', 'minimum' => ['red' => 2, 'yellow' => 1]],
        3642 => ['name' => 'Powerful Earthsiege Diamond', 'label' => '3 blue', 'minimum' => ['blue' => 3]],
        3643 => ['name' => 'Thundering Skyflare Diamond', 'label' => '1 red, 1 yellow, 1 blue', 'minimum' => ['red' => 1, 'yellow' => 1, 'blue' => 1]],
        3798 => ['name' => 'Swift Starflare Diamond', 'label' => '1 red, 2 yellow', 'minimum' => ['red' => 1, 'yellow' => 2]],
        3799 => ['name' => 'Tireless Starflare Diamond', 'label' => '1 red, 1 yellow, 1 blue', 'minimum' => ['red' => 1, 'yellow' => 1, 'blue' => 1]],
        3800 => ['name' => 'Impassive Starflare Diamond', 'label' => '1 red, 2 blue', 'minimum' => ['red' => 1, 'blue' => 2]],
        3801 => ['name' => 'Enigmatic Starflare Diamond', 'label' => '2 red, 1 blue', 'minimum' => ['red' => 2, 'blue' => 1]],
        3802 => ['name' => 'Forlorn Starflare Diamond', 'label' => '2 yellow, 1 blue', 'minimum' => ['yellow' => 2, 'blue' => 1]],
        3803 => ['name' => 'Persistent Earthshatter Diamond', 'label' => '3 blue', 'minimum' => ['blue' => 3]],
        3804 => ['name' => 'Powerful Earthshatter Diamond', 'label' => '1 yellow, 2 blue', 'minimum' => ['yellow' => 1, 'blue' => 2]],
        3805 => ['name' => 'Trenchant Earthshatter Diamond', 'label' => '1 red, 1 yellow, 1 blue', 'minimum' => ['red' => 1, 'yellow' => 1, 'blue' => 1]],
    ];

    /**
     * @param list<int|string> $gemEnchantIds
     * @return list<array{enchant_id: int, name: string, requirement: string, active: bool, colors: array{red: int, yellow: int, blue: int}}>
     */
    public function check(array $gemEnchantIds): array
    {
        $colors = ['red' => 0, 'yellow' => 0, 'blue' => 0];

        foreach ($gemEnchantIds as $gemEnchantId) {
            $gemEnchantId = (int) $gemEnchantId;
            if ($gemEnchantId <= 0 || isset(self::REQUIREMENTS[$gemEnchantId])) {
                continue;
            }

            $mask = $this->colorMask($gemEnchantId);
            $colors['red'] += (int) (($mask & self::RED) !== 0);
            $colors['yellow'] += (int) (($mask & self::YELLOW) !== 0);
            $colors['blue'] += (int) (($mask & self::BLUE) !== 0);
        }

        $results = [];
        foreach ($gemEnchantIds as $gemEnchantId) {
            $gemEnchantId = (int) $gemEnchantId;
            $rule = self::REQUIREMENTS[$gemEnchantId] ?? null;
            if ($rule === null) {
                continue;
            }

            $active = true;
            foreach ($rule['minimum'] ?? [] as $color => $minimum) {
                $active = $active && $colors[$color] >= $minimum;
            }
            if (isset($rule['more'])) {
                [$left, $right] = $rule['more'];
                $active = $colors[$left] > $colors[$right];
            }

            $results[] = [
                'enchant_id' => $gemEnchantId,
                'name' => $rule['name'],
                'requirement' => $rule['label'],
                'active' => $active,
                'colors' => $colors,
            ];
        }

        return $results;
    }

    public function isMetaGem(int $gemEnchantId): bool
    {
        return isset(self::REQUIREMENTS[$gemEnchantId]);
    }

    private function colorMask(int $gemEnchantId): int
    {
        $gem = GemDatabase::GEMS[$gemEnchantId] ?? null;
        $value = strtolower((string) ($gem['name'] ?? '').' '.(string) ($gem['icon'] ?? ''));

        if (str_contains($value, 'nightmare tear') || str_contains($value, 'prismatic')) {
            return self::RED | self::YELLOW | self::BLUE;
        }
        if (preg_match('/(dreadstone|twilight opal|gem_40|shadow crystal)/', $value)) {
            return self::RED | self::BLUE;
        }
        if (preg_match('/(ametrine|monarch topaz|gem_39|huge citrine)/', $value)) {
            return self::RED | self::YELLOW;
        }
        if (preg_match('/(eye of zul|forest emerald|gem_41|dark jade)/', $value)) {
            return self::YELLOW | self::BLUE;
        }
        if (preg_match('/(dragonseye03|king.s amber|autumn|gem_38|sun crystal)/', $value)) {
            return self::YELLOW;
        }
        if (preg_match('/(dragonseye04|zircon|sapphire|gem_42|chalcedony)/', $value)) {
            return self::BLUE;
        }
        if (preg_match('/(dragonseye05|ruby|gem_37|gem_28|gem_22|bloodstone)/', $value)) {
            return self::RED;
        }

        // Older gems not present in GemDatabase can still be classified from
        // their enchant text. This mirrors the paperdoll's fallback resolver.
        $effect = strtolower(EnchantDatabase::ENCHANTS[$gemEnchantId] ?? '');
        if (str_contains($effect, 'all stats') || str_contains($effect, 'all resist')) {
            return self::RED | self::YELLOW | self::BLUE;
        }

        $red = preg_match('/\b(strength|agility|ap|sp|attack power|spell power|armor pen|expertise|parry|dodge)\b/i', $effect) === 1;
        $blue = preg_match('/\b(stamina|mp5|spirit|spell pen)\b/i', $effect) === 1;
        $yellow = preg_match('/\b(crit|hit|haste|intellect|defense|resilience)\b/i', $effect) === 1;

        return ($red ? self::RED : 0) | ($yellow ? self::YELLOW : 0) | ($blue ? self::BLUE : 0);
    }
}
