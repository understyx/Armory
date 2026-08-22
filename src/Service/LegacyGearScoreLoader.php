<?php

namespace App\Service;

use RuntimeException;

class LegacyGearScoreLoader
{
    /**
     * @return array<int, int>
     */
    public function load(string $filePath): array
    {
        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            throw new RuntimeException(sprintf('Unable to open legacy item database: %s', $filePath));
        }

        $scores = [];

        try {
            while (($line = fgets($handle)) !== false) {
                if (!preg_match(
                    "/^\\((\\d+),'(?:''|[^'])*',-?\\d+(?:\\.\\d+)?,-?\\d+,-?\\d+,-?\\d+,-?\\d+,-?\\d+,-?\\d+,(-?\\d+)\\)[;,]?$/",
                    trim($line),
                    $matches
                )) {
                    continue;
                }

                $scores[(int) $matches[1]] = (int) $matches[2];
            }
        } finally {
            fclose($handle);
        }

        return $scores;
    }
}
