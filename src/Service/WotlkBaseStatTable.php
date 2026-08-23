<?php

namespace App\Service;

/**
 * Wrath 3.3.5 base attributes, split into racial offsets and class growth.
 *
 * The packed class rows contain Strength, Agility, Stamina, Intellect and
 * Spirit for levels 1-80. They are derived from player_levelstats data; the
 * race offset is constant across levels, which lets the UI explain both parts
 * without losing the exact combined value used by the game.
 */
final class WotlkBaseStatTable
{
    public const STAT_KEYS = ['strength', 'agility', 'stamina', 'intellect', 'spirit'];

    private const RACE_OFFSETS = [
        'human' => [0, 0, 0, 0, 0],
        'orc' => [3, -3, 1, -3, 2],
        'dwarf' => [5, -4, 1, -1, -1],
        'night elf' => [-4, 4, 0, 0, 0],
        'undead' => [-1, -2, 0, -2, 5],
        'tauren' => [5, -4, 1, -4, 2],
        'gnome' => [-5, 2, 0, 3, 0],
        'troll' => [1, 2, 0, -4, 1],
        'blood elf' => [-3, 2, 0, 3, -2],
        'draenei' => [1, -3, 0, 0, 2],
    ];

    /** @var array<string, string> Five unsigned bytes per level. */
    private const CLASS_ROWS = [
        'warrior' => 'FxQWFBQYFRcUFBkVGBQVGhYZFBUcFxoUFR0YGxUVHhgcFRYfGR0VFiAaHhUWIRofFRcjGyEVFyQcIhUXJR0jFRgnHiQWGCgeJRYYKR8mFhkqICgWGSwhKRYZLSIqFhovIysWGjAjLRcaMSQuFxszJS8XGzQmMRccNicyFxw3KDMXHDkpNRcdOio2GB08KzgYHj4sORgePy06GB5BLjwYH0IvPRgfRDA/GSBGMUAZIEgyQhkhSTNEGSFLNEUZIU01RxoiTzZIGiJQOEoaI1I5TBojVDpNGiRWO08aJFg8URslWj1TGyVcP1QbJl5AVhsmYEFYHCdiQlocJ2REXBwoZkVeHChoRmAcKWpIYh0qbUlkHSpvSmYdK3FMaB0rc01qHix2T2weLHhQbh4telFwHi59U3IeLn9UdR8vglZ3Hy+EWHkfMIdZeyAxiVt+IDGMXIAgMo5egiAzkWCFITOUYYchNJZjiiE1mWWMITacZo8iNp9okSI3omqUIjilbJcjOahtmSM5q2+cIzqucZ8kOw==',
        'paladin' => 'FhQWFBUXFRcVFhgVGBUWGRYZFhcaFhoWGBsXGxcYHBccGBkdGBwYGR4YHRkaHxkeGRsgGR8aHCEaIBscIhshGx0jGyIcHiQcJB0eJhwlHR8nHSYeICgeJx8hKR4oHyEqHykgIisgKiEjLSArIiQuISwiJS8iLiMlMCIvJCYyIzAlJzMkMSUoNCQyJik2JTQnKjcmNSgqOCc2KSs6JzgqLDsoOSotPSk6Ky4+KjwsL0ArPS0wQSs+LjFDLEAvMkQtQTAzRi5DMTRHL0QyNUkvRjM2SjBHNDdMMUk0OE4ySjU5TzNMNjpRNE04O1M1Tzk8VDZROj5WN1I7P1g4VDxAWjlWPUFcOlc+Ql07WT9DXzxbQEVhPV1BRmM+XkJHZT9gREhnQGJFSmlBZEZLa0JmR0xtQ2hITm9EakpPcUVsS1BzR25MUnZIcE5TeElyT1V6SnRQVnxLdlJXfk14U1mBTnpUWoNPfVZchVB/V16IUoFZX4pTg1phjVSGXGKPVohdZJJXil9mlFiNYGeXWo9iaQ==',
        'hunter' => 'FBcVFBUUGBYVFhUZFxUWFRsXFhcWHBgWFxYdGRcYFh4aFxgXHxsYGRchHBgaGCIcGRoYIx0ZGxklHhocGSYfGxwaJyAbHRopIRwdGyoiHB4bKyMdHxwtJB4gHC4lHiAdMCYfIR0xJyAiHjMoICIeNCkhIx82KiIkHzcrIiUgOSwjJSA7LSQmITwuJCchPi8lKCJAMCYoIkEyJykjQzMnKiRFNCgrJEY1KSwlSDYqLSZKOCsuJkw5Ky4nTjosLydQOy0wKFE9LjEpUz4vMilVPy8zKldAMDQrWUIxNStbQzI2LF1FMzctX0Y0OC5iRzU5LmRJNjovZko3OzBoTDg8MWpNOT0xbE86PjJvUDs/M3FSPEA0c1M9QTV2VT5DNXhXP0Q2e1hARTd9WkFGOH9cQkc5gl1DSDqFX0RKOodhRUs7imNHTDyMZEhNPY9mSU8+kmhKUD+VaktRQJdsTVNBmm5OVEKdcE9VQ6ByUFdEo3RSWEWmdlNaRql4VFtHrHpWXUivfFdeSbJ+WGBKtYBaYQ==',
        'rogue' => 'FRcVFBQWGBYUFBYZFhQVFxsXFRUYHBgVFRgdGBUWGR8ZFRYaIBkVFhshGhUXGyMbFhccJBwWGB0lHBYYHicdFhgeKB4WGR8qHhcZICsfFxohLCAXGiIuIRcaIzAhFxsjMSIYGyQzIxgcJTQkGBwmNiUYHSc3JRkdKDkmGR4pOycZHio8KBkeKz4pGR8rQCoaHyxCKhogLUMrGiAuRSwaIS9HLRshMEkuGyIxSy8bIjNNMBwjNE4xHCQ1UDIcJDZSMxwlN1Q0HSU4VjUdJjlYNh0mOlo3HSc7XTgeJz1fOR4oPmE6Hik/YzsfKUBlPB8qQWc+HytDaj8gK0RsQCAsRW5BICxGcUIhLUhzQyEuSXVFIS5KeEYiL0x6RyIwTX1IIjFPf0ojMVCCSyMyUYVMIzNTh04kM1SKTyQ0Vo1QJDVXj1IlNlmSUyU3WpVVJjdcmFYmOF6bVyY5X55ZJzphoVonO2OkXCg7ZKdeKDxmql8pPWitYSk+abBiKT9rs2QqQG23ZipBb7pnK0JxvWkrQw==',
        'priest' => 'FBQUFhcUFBQXGBQUFRgZFRUVGRsVFRUbHBUVFhwdFRUWHR4VFhYeHxUWFx8hFhYXISIWFhgiIxYXGCMlFhcYJCYWFxkmJxcXGScpFxgaKCoXGBoqKxcYGistFxkbLC4YGRsuMBgZHC8xGBkcMTMYGh0yNBkaHTQ2GRoeNTcZGx43ORkbHjg7GRsfOjwaHB87PhocID1AGhwgP0EaHSFAQxsdIUJFGx0iREYbHiJFSBweI0dKHB4kSUwcHyRLThwfJUxQHR8lTlEdICZQUx0gJlJVHSEnVFceISdWWR4hKFhbHiIpWl0fIilcXx8jKl5iHyMrYGQgIytiZiAkLGRoICQsZmohJS1obCElLmpvISYubXEiJi9vcyInMHF2Iicxc3gjKDF2eyMoMnh9Iygzen8kKTN9giQpNH+FJCo1goclKzaEiiUrN4eMJiw3iY8mLDiMkiYtOY+VJy06kZcnLjuUmiguO5edKC88mqApLz2coykwPp+mKTE/oqkqMUClrCoyQaivKzJCq7IrM0OutQ==',
        'death knight' => 'FxQWFBQYFRcUFBkVGBQVGhYZFBUcFxoUFR0YGxUVHhgcFRYfGR0VFiAaHhUWIRofFRcjGyEVFyQcIhUXJR0jFRgnHiQWGCgeJRYYKR8mFhkqICgWGSwhKRYZLSIqFhovIysWGjAjLRcaMSQuFxszJS8XGzQmMRccNicyFxw3KDMXHDkpNRcdOio2GB08KzgYHj4sORgePy06GB5BLjwYH0IvPRgfRDA/GSBGMUAZIEgyQhkhSTNEGSFLNEUZIU01RxoiTzZIGiJQOEoaI1I5TBojVDpNGiRWO08aJFg8URslWj1TGyVcP1QbJl5AVhsmYEFYHCdiQlocJ2REXBwoZkVeHChoRmAcKWpIYh0qbEljHSpvS2YdK3FMaB0rc01qHix2T2weLHhQbh4telFwHi59U3IeLn9UdR8vglZ3Hy+FV3kfMIdZfB8xilp+IDGMXIAgMo9dgyAzkl+FIDOVYYghNJdiiiE1mmSNITadZo8hNqBnkiI3o2mVIjima5ciOalsmiI5rG6dIzqvcKAjOw==',
        'shaman' => 'FRQVFRYWFBYWFxYVFxcYFxUYFxkYFhgYGhkWGRkbGhYaGhwaFxsbHBsXHBwdHBgdHB4dGB4dHx4ZHx4gHhkgHyEfGiEgIiAaIiEkIRsjIiUiGyQjJiMcJSQnJBwmJSglHScmKSYdKCcqJh4pKCsnHiopLCgfKyouKR8tKy8qIC4sMCsgLy0xLCEwLjItITEvNC4iMjA1MCI0MjYxIzUzODIkNjQ5MyQ3NTo0JTk2PDUmOjg9NiY7OT43Jz06QDgnPjtBOig/PUM7KUE+RDwpQj9GPSpEQEc/K0VCSUArR0NKQSxIRUxCLUpGTUQuS0dPRS5NSVFGL05KUkgwUExUSTFRTVZLMVNPV0wyVVBZTTNWUltPNFhTXVA1WlVeUjVbV2BTNl1YYlU3X1pkVzhhXGZYOWNdaFo6ZF9qWzpmYWxdO2hjbl88amRwYD1sZnJiPm5odGQ/cGp2ZkBybHhnQXRuemlCdnB9a0N4cn9tRHt0gW9FfXaDb0Z/eIZyR4F6iHRIg3yKdkmGfo14SoiAjw==',
        'mage' => 'FBQUFxYUFBQYFxQUFRkYFBUVGxkUFRUcGxUVFR0cFRUWHh0VFRYfHhUVFiEfFRYXIiEVFhcjIhUWFyUjFRYYJiQWFhgnJhYXGCknFhcZKigWFxkrKhYXGS0rFhcaLiwWGBowLhcYGjEvFxgbMzEXGBs0MhcZHDY0FxkcNzUXGRw5NxcZHTs4GBkdPDoYGh4+OxgaHkA9GBoeQT8YGh9DQBgbH0VCGRsgRkQZGyBIRRkcIUpHGRwhTEkZHCFOSxocIlBMGh0iUU4aHSNTUBodI1VSGh0kV1QaHiRZVhseJVtYGx4lXVobHyZfXBsfJmJeHB8nZGAcICdmYhwgKGhkHCAoamYcISlsaB0hKm9qHSEqcW0dIitzbx0iK3ZxHiIseHMeIyx7dh4jLX14HiMuf3oeJC6CfR8kL4V/HyQvh4IfJTCKhCAlMYyHICYxj4kgJjKSjCAmM5WPISczl5EhJzSalCEoNZ2XISg2oJoiKTajnCIpN6afIik4qaIjKjmspSMqOa+oIys6sqskKzu1rg==',
        'warlock' => 'FBQVFhYUFBYXFxUVFhgYFRUXGRkVFRcaGhUWGBsbFhYYHB0WFhkdHhYXGR4fFxcaHyAXGBohIRcYGyIiGBgbIyQYGRwkJRgZHSUmGRodJicZGh4oKRkaHikqGhsfKisaGyArLRocIC0uGxwhLi8bHSIvMRwdIjEyHB4jMjQcHiQzNR0eJDU2HR8lNjgeHyY4OR4gJjk7HiAnOj0fISg8Ph8hKT1AICIpP0EgIipAQyEjK0JFISQsREYhJC1FSCIlLUdKIiUuSEsjJi9KTSMmMExPJCcxTVEkJzJPUiUoMlFUJSkzU1YmKTRUWCYqNVZaJys2WFwnKzdaXigsOFxgKCw5XmIpLTpgZCouO2JmKi48ZGgrLz1maiswPmhsLDE/am8sMUBscS0yQW5zLjNCcHUuM0NyeC80RHV6LzVFd3wwNkd5fjE3SHuBMTdJfoMyOEqAhjM5S4KIMzpMhYs0O06HjjU7T4qQNjxQjJM2PVGPljc+U5GYOD9UlJs5QFWXnjlBV5mhOkJYnKQ7Q1mfpg==',
        'druid' => 'FRQUFhYWFBUXFxYVFRgYFxUWGRkXFhYaGhgWFxsbGBcYHBwZFxgcHRkYGR0eGhgZHh8aGRofIRsZGyAiGxobISMcGhwiJB0bHSQlHRsdJSYeHB4mKB4dHycpHx0fKCogHiApKyAeISotIR8iKy4iICIsLyIgIy4xIyEkLzIjISQwMyQiJTE1JSMmMjYmIyc0OCYkKDU5JyUpNjooJSo4PCkmKjk9KScrOj8qJyw8QCsoLT1CLCkuPkQtKS9ARS0qMEFHLisxQ0gvLDJESjAsM0ZMMS00R00yLjRJTzIvNUpRMzA2TFM0MDhNVDUxOU9WNjI6UVg3MztSWjg0PFRcOTU9Vl46Nj5XYDs2P1liPDdAW2Q9OEFdZj45Ql5oPzpEYGpAO0VibEE8RmRuQj1HZnBDPkhockQ/Smp1RUBLbHdHQUxueUhCTnB7SUNPcn5KRFB0gEtFUnaCTEZTeIVOR1R6h09JVn2KUEpXf4xRS1mBj1NMWoORVE1chpRVTl2Il1dQX4qZWFFgjZxZUmKPnw==',
    ];

    /**
     * @return array{race: array<string, int>, class: array<string, int>, level: array<string, int>, total: array<string, int>}|null
     */
    public function getBreakdown(string $race, string $class, int $level): ?array
    {
        $raceKey = strtolower(trim($race));
        $classKey = strtolower(trim($class));
        if (!isset(self::RACE_OFFSETS[$raceKey], self::CLASS_ROWS[$classKey])) {
            return null;
        }

        $level = max(1, min(80, $level));
        $bytes = base64_decode(self::CLASS_ROWS[$classKey], true);
        if ($bytes === false || strlen($bytes) !== 400) {
            return null;
        }

        $classBase = array_values(unpack('C5', substr($bytes, 0, 5)));
        $atLevel = array_values(unpack('C5', substr($bytes, ($level - 1) * 5, 5)));
        $raceOffset = self::RACE_OFFSETS[$raceKey];
        $result = ['race' => [], 'class' => [], 'level' => [], 'total' => []];

        foreach (self::STAT_KEYS as $index => $stat) {
            $result['race'][$stat] = $raceOffset[$index];
            $result['class'][$stat] = $classBase[$index];
            $result['level'][$stat] = $atLevel[$index] - $classBase[$index];
            $result['total'][$stat] = $raceOffset[$index] + $atLevel[$index];
        }

        return $result;
    }
}
