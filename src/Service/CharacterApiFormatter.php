<?php

namespace App\Service;

use App\Entity\CharacterSnapshot;

class CharacterApiFormatter
{
    public function __construct(private readonly ?CharacterStatCalculator $statCalculator = null)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function format(CharacterSnapshot $snapshot): array
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
        ];

        if ($this->statCalculator !== null) {
            $payload['stats'] = $this->statCalculator->calculate(
                (string) $snapshot->getRace(),
                (string) $snapshot->getClass(),
                (int) $snapshot->getLevel(),
                $snapshot->getEquippedItems(),
                $snapshot->getTalentTreesData() ?? [],
            );
        }

        return $payload;
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
