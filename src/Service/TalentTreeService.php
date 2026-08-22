<?php

namespace App\Service;

class TalentTreeService
{
    private const CLASS_TREES = [
        'Death Knight' => ['Blood', 'Frost', 'Unholy'],
        'Druid'        => ['Balance', 'Feral Combat', 'Restoration'],
        'Hunter'       => ['Beast Mastery', 'Marksmanship', 'Survival'],
        'Mage'         => ['Arcane', 'Fire', 'Frost'],
        'Paladin'      => ['Holy', 'Protection', 'Retribution'],
        'Priest'       => ['Discipline', 'Holy', 'Shadow'],
        'Rogue'        => ['Assassination', 'Combat', 'Subtlety'],
        'Shaman'       => ['Elemental', 'Enhancement', 'Restoration'],
        'Warlock'      => ['Affliction', 'Demonology', 'Destruction'],
        'Warrior'      => ['Arms', 'Fury', 'Protection'],
    ];

    private const WOTLK_REALMS = [
        'icecrown',
        'lordaeron',
        'frostmourne',
        'blackrock',
    ];

    /**
     * Determines if the given realm/character state represents a WotLK server.
     */
    public function isWotlkServer(string $realmName, array $glyphs = []): bool
    {
        $normalizedRealm = strtolower(trim($realmName));
        if (in_array($normalizedRealm, self::WOTLK_REALMS, true)) {
            return true;
        }

        // Check if glyphs array has non-empty spec data
        foreach ($glyphs as $specGlyphs) {
            if (!empty($specGlyphs['Major Glyphs']) || !empty($specGlyphs['Minor Glyphs'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Parses character specializations and talent strings into structured tree data.
     *
     * @param string|null $className Character class (e.g. "Paladin")
     * @param array $specializations List of specialization strings (e.g. ["Protection (51/5/15)"])
     * @param array $talentStrings Map of spec ID => digit string (e.g. ['0' => '5050...'])
     * @return array List of specs with tree breakdowns
     */
    public function parseTalentTrees(?string $className, array $specializations = [], array $talentStrings = [], array $talentTreesData = []): array
    {
        $treeNames = self::CLASS_TREES[$className ?? ''] ?? ['Tree 1', 'Tree 2', 'Tree 3'];
        $specs = [];

        // Build spec entries based on specializations list or default specs 0 and 1
        $specCount = max(count($specializations), count($talentStrings), count($talentTreesData), 1);

        for ($i = 0; $i < $specCount; $i++) {
            $specId = (string)$i;
            $rawSpecStr = $specializations[$i] ?? null;
            $talentDigitStr = $talentStrings[$specId] ?? null;
            $rawTrees = $talentTreesData[$specId] ?? null;

            $parsedInfo = $this->parseSpecString($rawSpecStr);
            $specName = $parsedInfo['name'] ?? null;
            $pointsBreakdown = $parsedInfo['pointsBreakdown'] ?? [0, 0, 0];

            $trees = [];
            if (!empty($rawTrees) && is_array($rawTrees)) {
                foreach ($rawTrees as $idx => $treeData) {
                    $tiers = $treeData['tiers'] ?? [];
                    foreach ($tiers as &$tier) {
                        foreach ($tier as &$talent) {
                            if (is_array($talent)) {
                                $talent['iconFallbackUrl'] = $this->localFallbackUrl($talent['iconUrl'] ?? null);
                            }
                        }
                        unset($talent);
                    }
                    unset($tier);

                    $trees[] = [
                        'name' => $treeData['name'] ?? ($treeNames[$idx] ?? 'Tree ' . ($idx + 1)),
                        'iconUrl' => $treeData['iconUrl'] ?? null,
                        'iconFallbackUrl' => $this->localFallbackUrl($treeData['iconUrl'] ?? null),
                        'points' => (int)($treeData['points'] ?? 0),
                        'tiers' => $tiers,
                    ];
                }
            } else {
                // Fallback if full tree grid data is not present
                if (array_sum($pointsBreakdown) === 0 && !empty($talentDigitStr)) {
                    $pointsBreakdown = $this->derivePointsFromDigitString($talentDigitStr);
                }

                foreach ($treeNames as $idx => $treeName) {
                    $allocatedPoints = $pointsBreakdown[$idx] ?? 0;
                    $trees[] = [
                        'name' => $treeName,
                        'iconUrl' => null,
                        'iconFallbackUrl' => null,
                        'points' => $allocatedPoints,
                        'tiers' => [],
                    ];
                }
            }

            // Determine spec name if not explicitly parsed
            if (empty($specName)) {
                $maxPtsIndex = 0;
                $maxPts = -1;
                foreach ($trees as $tIdx => $t) {
                    if (($t['points'] ?? 0) > $maxPts) {
                        $maxPts = $t['points'] ?? 0;
                        $maxPtsIndex = $tIdx;
                    }
                }
                $specName = $maxPts > 0 ? ($trees[$maxPtsIndex]['name'] ?? 'Spec') : ($i === 0 ? 'Primary Spec' : 'Secondary Spec');
            }

            $p0 = $trees[0]['points'] ?? 0;
            $p1 = $trees[1]['points'] ?? 0;
            $p2 = $trees[2]['points'] ?? 0;

            $specs[] = [
                'specId' => $specId,
                'label' => $i === 0 ? 'Primary Spec' : 'Secondary Spec',
                'specName' => $specName,
                'pointsSummary' => sprintf('%d / %d / %d', $p0, $p1, $p2),
                'totalPoints' => $p0 + $p1 + $p2,
                'trees' => $trees,
                'digitString' => $talentDigitStr,
            ];
        }

        return $specs;
    }

    private function localFallbackUrl(?string $sourceUrl): ?string
    {
        if ($sourceUrl === null || trim($sourceUrl) === '') {
            return null;
        }

        $path = parse_url($sourceUrl, PHP_URL_PATH);
        if (!is_string($path)) {
            return null;
        }

        $iconName = strtolower(pathinfo($path, PATHINFO_FILENAME));
        if (!preg_match('/\A[a-z0-9][a-z0-9_-]{0,127}\z/', $iconName)) {
            return null;
        }

        return '/wow-icons/large/'.rawurlencode($iconName).'.jpg';
    }

    /**
     * Parses a string like "Protection (51/5/15)" into name and point array.
     */
    private function parseSpecString(?string $rawSpecStr): array
    {
        if (empty($rawSpecStr)) {
            return ['name' => null, 'pointsBreakdown' => [0, 0, 0]];
        }

        $specName = null;
        $pointsBreakdown = [0, 0, 0];

        if (preg_match('/^([^\(]+)\s*\((?:(\d+)\/(\d+)\/(\d+))\)/', trim($rawSpecStr), $matches)) {
            $specName = trim($matches[1]);
            $pointsBreakdown = [(int)$matches[2], (int)$matches[3], (int)$matches[4]];
        } elseif (preg_match('/(?:(\d+)\/(\d+)\/(\d+))/', $rawSpecStr, $matches)) {
            $pointsBreakdown = [(int)$matches[1], (int)$matches[2], (int)$matches[3]];
        }

        return [
            'name' => $specName,
            'pointsBreakdown' => $pointsBreakdown,
        ];
    }

    /**
     * Fallback helper to estimate tree distribution if only digit string is given.
     */
    private function derivePointsFromDigitString(string $digitString): array
    {
        // Simple division into 3 segments based on digit length
        $len = strlen($digitString);
        if ($len < 3) {
            return [0, 0, 0];
        }

        $segLen = (int)ceil($len / 3);
        $seg1 = substr($digitString, 0, $segLen);
        $seg2 = substr($digitString, $segLen, $segLen);
        $seg3 = substr($digitString, $segLen * 2);

        $sumDigits = function (string $str): int {
            $sum = 0;
            for ($i = 0; $i < strlen($str); $i++) {
                $sum += (int)$str[$i];
            }
            return $sum;
        };

        return [
            $sumDigits($seg1),
            $sumDigits($seg2),
            $sumDigits($seg3),
        ];
    }
}
