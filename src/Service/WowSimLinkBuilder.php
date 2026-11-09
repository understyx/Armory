<?php

namespace App\Service;

use App\Entity\CharacterSnapshot;

/**
 * Builds Poli's WoWSims import links. The template, overrides and protobuf schema
 * are copied from Ridepad/uwu-logs main at 27e50419b0c5110c0df7f8059d75eec28d88574d.
 * Source: https://github.com/Ridepad/uwu-logs/tree/27e50419b0c5110c0df7f8059d75eec28d88574d/static
 */
final class WowSimLinkBuilder
{
    private const TALENT_TREE_LENGTHS = [
        'Deathknight' => [28, 29, 31], 'Druid' => [28, 30, 27], 'Hunter' => [26, 27, 28],
        'Mage' => [30, 28, 28], 'Paladin' => [26, 26, 26], 'Priest' => [28, 27, 27],
        'Rogue' => [27, 28, 28], 'Shaman' => [25, 29, 26], 'Warlock' => [28, 27, 26],
        'Warrior' => [31, 27, 27],
    ];

    private const CLASS_IDS = [
        'Druid' => 1, 'Hunter' => 2, 'Mage' => 3, 'Paladin' => 4, 'Priest' => 5,
        'Rogue' => 6, 'Shaman' => 7, 'Warlock' => 8, 'Warrior' => 9, 'Deathknight' => 10,
    ];

    private const RACE_IDS = [
        'Blood Elf' => 1, 'Draenei' => 2, 'Dwarf' => 3, 'Gnome' => 4, 'Human' => 5,
        'Night Elf' => 6, 'Orc' => 7, 'Tauren' => 8, 'Troll' => 9, 'Undead' => 10,
    ];

    private const PROFESSION_IDS = [
        'Alchemy' => 1, 'Blacksmithing' => 2, 'Enchanting' => 3, 'Engineering' => 4,
        'Herbalism' => 5, 'Inscription' => 6, 'Jewelcrafting' => 7, 'Leatherworking' => 8,
        'Mining' => 9, 'Skinning' => 10, 'Tailoring' => 11,
    ];

    private const SLOT_ORDER = [
        'head', 'neck', 'shoulder', 'back', 'chest', 'wrist', 'hands', 'waist', 'legs', 'feet',
        'finger1', 'finger2', 'trinket1', 'trinket2', 'mainhand', 'offhand', 'ranged',
    ];

    /** @var array<string, mixed> */
    private ?array $template = null;
    /** @var array<string, mixed> */
    private ?array $specOverrides = null;
    /** @var array<string, mixed> */
    private ?array $schema = null;
    /** @var array<string, int> */
    private array $glyphIdsByName = [];

    public function __construct()
    {
        foreach (GlyphDatabase::GLYPHS as $itemId => $glyph) {
            $this->glyphIdsByName[mb_strtolower((string) $glyph['name'])] = (int) $itemId;
        }
    }

    /** @param list<array<string, mixed>> $parsedSpecs @param array<string, array<string, mixed>> $paperdollSlots @return array<string, string> */
    public function buildLinks(CharacterSnapshot $snapshot, array $parsedSpecs, array $paperdollSlots): array
    {
        $links = [];
        foreach ($parsedSpecs as $parsedSpec) {
            $specId = (string) ($parsedSpec['specId'] ?? '0');
            $specName = (string) ($parsedSpec['specName'] ?? '');
            $result = $this->buildPayload($snapshot, $specId, $specName, $paperdollSlots);
            if ($result !== null) {
                $links[$specId] = sprintf(
                    'https://poli93.github.io/wotlk/%s/?i=gtmc#%s',
                    $result['path'],
                    base64_encode(gzcompress($this->encodeIndividualSettings($result['settings']))),
                );
            }
        }

        return $links;
    }

    /** @param array<string, array<string, mixed>> $paperdollSlots @return array{path: string, settings: array<string, mixed>}|null */
    public function buildPayload(CharacterSnapshot $snapshot, string $specId, string $specName, array $paperdollSlots): ?array
    {
        if (in_array(mb_strtolower($specName), ['primary spec', 'secondary spec'], true)
            && empty(($snapshot->getTalentStrings() ?? [])[$specId])) {
            return null;
        }
        $this->loadData();
        $className = $this->normalizeClass((string) $snapshot->getClass());
        if (!isset(self::CLASS_IDS[$className], self::TALENT_TREE_LENGTHS[$className])) {
            return null;
        }

        $orderedGear = $this->buildEquipment($paperdollSlots);
        $spec = $this->resolveSpec($className, $specName, $orderedGear);
        if ($spec === null) {
            return null;
        }
        [$specPath, $overrideKey, $oneof] = $spec;
        $override = $this->specOverrides[$overrideKey] ?? null;
        if (!is_array($override)) {
            return null;
        }

        $professions = $this->professions($snapshot->getProfessions() ?? []);
        if (!in_array('Enchanting', array_column($professions, 0), true)) {
            unset($orderedGear[10]['enchant'], $orderedGear[11]['enchant']);
        }
        $orderedGear = array_values($orderedGear);

        $base = $this->template ?? [];
        $player = &$base['player'];
        $player['name'] = (string) $snapshot->getName();
        $player['class'] = self::CLASS_IDS[$className];
        $player['race'] = self::RACE_IDS[(string) $snapshot->getRace()] ?? 0;
        $player['talentsString'] = $this->talentsString($className, (string) (($snapshot->getTalentStrings() ?? [])[$specId] ?? ''));
        $player['profession1'] = $professions[0][1] ?? 0;
        $player['profession2'] = $professions[1][1] ?? 0;
        $player['equipment']['items'] = $orderedGear;

        $merged = $this->mergeDeep($base, $override);
        $glyphs = $this->glyphsForSpec($snapshot->getGlyphs() ?? [], $specId);
        if ($glyphs !== []) {
            $merged['player']['glyphs'] = $glyphs;
        } else {
            $glyphOverride = $override['player']['glyphsOverride'][$specName] ?? null;
            if (is_array($glyphOverride)) {
                $merged['player']['glyphs'] = $glyphOverride;
            }
        }

        $merged['player'][$oneof] ??= [];

        return ['path' => $specPath, 'settings' => $merged];
    }

    /** @param array<string, array<string, mixed>> $slots @return list<array<string, mixed>> */
    private function buildEquipment(array $slots): array
    {
        $items = [];
        foreach (self::SLOT_ORDER as $slotName) {
            $item = $slots[$slotName]['item'] ?? null;
            if (!is_array($item) || (int) ($item['id'] ?? 0) <= 0) {
                $items[] = [];
                continue;
            }

            $spec = ['id' => (int) $item['id']];
            if ((int) ($item['enchant'] ?? 0) > 0) {
                $spec['enchant'] = (int) $item['enchant'];
            }
            $gems = [];
            foreach ($item['gem_details'] ?? [] as $gem) {
                $gemId = (int) ($gem['id'] ?? 0);
                if ($gemId > 0) {
                    $gems[] = $gemId;
                }
            }
            if ($gems !== []) {
                $spec['gems'] = $gems;
            }
            $items[] = $spec;
        }

        return $items;
    }

    /** @param list<array<string, mixed>> $gear @return array{string, string, string}|null */
    private function resolveSpec(string $className, string $specName, array $gear): ?array
    {
        $name = mb_strtolower(trim($specName));
        if ($className === 'Priest' && str_contains($name, 'smite')) {
            return null;
        }
        $headGems = $gear[0]['gems'] ?? [];
        $tank = $this->hasGem($headGems, [41380, 44088, 41397, 41396]);
        $apDps = $this->hasGem($headGems, [41398, 41400, 41385, 44087, 41381, 44076, 41339]);
        $healer = $this->hasGem($headGems, [41333, 41401, 41376]);

        $spec = match ($className) {
            'Druid' => $name === 'balance' ? 'balance'
                : (in_array($name, ['restoration', 'resto'], true) ? null
                    : ($tank ? 'feral_tank' : (($apDps || in_array($name, ['feral combat', 'feral'], true)) ? 'feral' : 'balance'))),
            'Shaman' => in_array($name, ['restoration', 'resto'], true) ? 'restoration'
                : ($name === 'enhancement' ? 'enhancement' : ($name === 'elemental' ? 'elemental' : ($apDps ? 'enhancement' : 'elemental'))),
            'Paladin' => in_array($name, ['holy', 'holy paladin'], true) ? null
                : ($name === 'protection' ? 'protection' : ($name === 'retribution' ? 'retribution' : ($tank ? 'protection' : 'retribution'))),
            'Priest' => $name === 'shadow' ? 'shadow' : ($healer ? 'healing' : 'shadow'),
            'Warrior' => ($name === 'protection' || $tank) ? 'protection' : '',
            'Deathknight' => $tank ? 'tank' : '',
            default => '',
        };

        if ($spec === null) {
            return null;
        }

        $path = $spec === '' ? '' : $spec.'_';
        $path .= strtolower($className);
        $overrideKey = $spec === '' ? strtolower($className) : $spec.$className;
        $oneof = match ($overrideKey) {
            'balanceDruid' => 'balanceDruid', 'feralDruid' => 'feralDruid', 'feral_tankDruid' => 'feralTankDruid',
            'hunter' => 'hunter', 'mage' => 'mage', 'retributionPaladin' => 'retributionPaladin',
            'protectionPaladin' => 'protectionPaladin', 'healingPriest' => 'healingPriest', 'shadowPriest' => 'shadowPriest',
            'rogue' => 'rogue', 'elementalShaman' => 'elementalShaman', 'enhancementShaman' => 'enhancementShaman',
            'restorationShaman' => 'restorationShaman', 'warlock' => 'warlock', 'warrior' => 'warrior',
            'protectionWarrior' => 'protectionWarrior', 'deathknight' => 'deathknight', 'tankDeathknight' => 'tankDeathknight',
            default => null,
        };
        if ($oneof === null) {
            return null;
        }

        return [$path, $overrideKey, $oneof];
    }

    private function hasGem(array $gems, array $search): bool
    {
        return array_intersect($gems, $search) !== [];
    }

    private function normalizeClass(string $class): string
    {
        $class = trim($class);
        if (strcasecmp($class, 'Death Knight') === 0 || strcasecmp($class, 'Deathknight') === 0) {
            return 'Deathknight';
        }

        return ucfirst(strtolower($class));
    }

    /** @param list<string> $rawProfessions @return list<array{string, int}> */
    private function professions(array $rawProfessions): array
    {
        $professions = [];
        foreach (array_slice($rawProfessions, 0, 2) as $raw) {
            if (!preg_match('/^\s*([^\s(]+(?:\s+[^\s(]+)*)/u', (string) $raw, $match)) {
                $professions[] = ['', 0];
                continue;
            }
            $name = trim($match[1]);
            $professions[] = [$name, self::PROFESSION_IDS[$name] ?? 0];
        }

        return $professions;
    }

    private function talentsString(string $className, string $raw): string
    {
        $lengths = self::TALENT_TREE_LENGTHS[$className];
        $raw = preg_replace('/[^0-5]/', '', $raw) ?? '';
        $raw = str_pad(substr($raw, 0, array_sum($lengths)), array_sum($lengths), '0');
        $offset = 0;
        $trees = [];
        foreach ($lengths as $length) {
            $trees[] = rtrim(substr($raw, $offset, $length), '0');
            $offset += $length;
        }

        return implode('-', $trees);
    }

    private function glyphsForSpec(array $allGlyphs, string $specId): array
    {
        $specGlyphs = $allGlyphs[$specId] ?? [];
        $glyphs = [];
        foreach ([['Major Glyphs', 'major'], ['Minor Glyphs', 'minor']] as [$sourceKey, $target]) {
            $position = 1;
            foreach ($specGlyphs[$sourceKey] ?? [] as $name) {
                $id = $this->glyphIdsByName[mb_strtolower(trim((string) $name))] ?? null;
                if ($id !== null && $position <= 3) {
                    $glyphs[$target.$position] = $id;
                    $position++;
                }
            }
        }

        return $glyphs;
    }

    private function mergeDeep(array $target, array $source): array
    {
        foreach ($source as $key => $value) {
            if (is_array($value) && !array_is_list($value) && isset($target[$key]) && is_array($target[$key])) {
                $target[$key] = $this->mergeDeep($target[$key], $value);
            } else {
                $target[$key] = $value;
            }
        }

        return $target;
    }

    private function encodeIndividualSettings(array $settings): string
    {
        $definitions = $this->schema['nested']['proto']['nested'] ?? [];

        return $this->encodeMessage('IndividualSimSettings', $settings, $definitions);
    }

    private function encodeMessage(string $typeName, array $values, array $parent): string
    {
        $definition = $this->resolveDefinition($typeName, $parent);
        if (!is_array($definition) || !isset($definition['fields'])) {
            throw new \LogicException(sprintf('No protobuf message definition for %s.', $typeName));
        }
        $encoded = '';
        foreach ($definition['fields'] as $fieldName => $field) {
            if (!array_key_exists($fieldName, $values) || !is_array($field)) {
                continue;
            }
            $value = $values[$fieldName];
            $fieldType = (string) ($field['type'] ?? '');
            $fieldId = (int) ($field['id'] ?? 0);
            if (($field['rule'] ?? null) === 'repeated') {
                if (!is_array($value) || $value === []) {
                    continue;
                }
                $nested = $this->resolveDefinition($fieldType, $definition);
                if (isset($nested['fields'])) {
                    foreach ($value as $entry) {
                        $bytes = $this->encodeMessage($fieldType, (array) $entry, $definition);
                        $encoded .= $this->encodeVarint(($fieldId << 3) | 2).$this->encodeVarint(strlen($bytes)).$bytes;
                    }
                    continue;
                }

                $packed = '';
                foreach ($value as $entry) {
                    [, $bytes] = $this->encodeScalar($fieldType, $entry);
                    $packed .= $bytes;
                }
                $encoded .= $this->encodeVarint(($fieldId << 3) | 2).$this->encodeVarint(strlen($packed)).$packed;
                continue;
            }

            $nested = $this->resolveDefinition($fieldType, $definition);
            if (isset($nested['fields'])) {
                $bytes = $this->encodeMessage($fieldType, (array) $value, $definition);
                $encoded .= $this->encodeVarint(($fieldId << 3) | 2).$this->encodeVarint(strlen($bytes)).$bytes;
                continue;
            }

            [$wire, $bytes] = $this->encodeScalar($fieldType, $value);
            $encoded .= $this->encodeVarint(($fieldId << 3) | $wire).$bytes;
        }

        return $encoded;
    }

    private function resolveDefinition(string $typeName, array $parent): array
    {
        return $parent['nested'][$typeName]
            ?? $this->schema['nested']['proto']['nested'][$typeName]
            ?? [];
    }

    /** @return array{int, string} */
    private function encodeScalar(string $type, mixed $value): array
    {
        return match ($type) {
            'string', 'bytes' => [2, $this->encodeVarint(strlen((string) $value)).(string) $value],
            'bool' => [0, $this->encodeVarint($value ? 1 : 0)],
            'double' => [1, pack('e', (float) $value)],
            'float' => [5, pack('g', (float) $value)],
            'fixed64', 'sfixed64' => [1, pack('P', (int) $value)],
            'fixed32', 'sfixed32' => [5, pack('V', (int) $value)],
            'sint32' => [0, $this->encodeVarint(((int) $value << 1) ^ ((int) $value >> 31))],
            'int64', 'uint64', 'uint32', 'int32' => [0, $this->encodeVarint((int) $value)],
            default => $this->encodeEnumScalar($type, (int) $value),
        };
    }

    /** @return array{int, string} */
    private function encodeEnumScalar(string $type, int $value): array
    {
        return [0, $this->encodeVarint($value)];
    }

    private function encodeVarint(int $value): string
    {
        $bytes = '';
        while ($value > 0x7f) {
            $bytes .= chr(($value & 0x7f) | 0x80);
            $value >>= 7;
        }

        return $bytes.chr($value & 0x7f);
    }

    private function loadData(): void
    {
        if ($this->template !== null) {
            return;
        }

        $this->template = $this->readJson('template.json');
        $this->specOverrides = $this->readJson('spec_overrides.json');
        $this->schema = $this->readJson('wowsim_pb.json');
    }

    /** @return array<string, mixed> */
    private function readJson(string $file): array
    {
        $path = dirname(__DIR__, 2).'/data/wowsims/'.$file;
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new \RuntimeException(sprintf('Unable to read WoWSims data file %s.', $file));
        }

        return json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    }
}
