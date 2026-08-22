<?php

namespace App\Service;

use Generator;
use RuntimeException;

/**
 * Streams VALUES tuples for one table from a mysqldump without loading the dump
 * or executing source SQL. It understands MySQL quoted-string escapes.
 */
class MySqlDumpTableReader
{
    /**
     * @return Generator<int, array<int, string|null>>
     */
    public function readRows(string $filePath, string $tableName): Generator
    {
        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            throw new RuntimeException(sprintf('Unable to open SQL dump: %s', $filePath));
        }

        $prefix = sprintf('INSERT INTO `%s` VALUES ', $tableName);
        $found = false;

        try {
            while (($line = fgets($handle)) !== false) {
                if (!str_starts_with($line, $prefix)) {
                    continue;
                }

                $found = true;
                foreach ($this->parseValues(substr($line, strlen($prefix))) as $row) {
                    yield $row;
                }
            }
        } finally {
            fclose($handle);
        }

        if (!$found) {
            throw new RuntimeException(sprintf('No INSERT rows found for table `%s` in %s.', $tableName, $filePath));
        }
    }

    /**
     * @return Generator<int, array<int, string|null>>
     */
    public function parseValues(string $values): Generator
    {
        $length = strlen($values);
        $row = [];
        $value = '';
        $inRow = false;
        $inString = false;
        $quoted = false;
        $escaped = false;

        for ($i = 0; $i < $length; $i++) {
            $char = $values[$i];

            if (!$inRow) {
                if ($char === '(') {
                    $inRow = true;
                    $row = [];
                    $value = '';
                    $quoted = false;
                }
                continue;
            }

            if ($inString) {
                if ($escaped) {
                    $value .= $this->decodeEscape($char);
                    $escaped = false;
                } elseif ($char === '\\') {
                    $escaped = true;
                } elseif ($char === "'") {
                    $inString = false;
                } else {
                    $value .= $char;
                }
                continue;
            }

            if ($char === "'" && trim($value) === '') {
                $inString = true;
                $quoted = true;
                $value = '';
                continue;
            }

            if ($char === ',') {
                $row[] = $this->normaliseValue($value, $quoted);
                $value = '';
                $quoted = false;
                continue;
            }

            if ($char === ')') {
                $row[] = $this->normaliseValue($value, $quoted);
                yield $row;
                $row = [];
                $value = '';
                $quoted = false;
                $inRow = false;
                continue;
            }

            $value .= $char;
        }

        if ($inRow || $inString || $escaped) {
            throw new RuntimeException('Malformed or incomplete VALUES tuple in SQL dump.');
        }
    }

    private function normaliseValue(string $value, bool $quoted): ?string
    {
        if ($quoted) {
            return $value;
        }

        $value = trim($value);

        return strcasecmp($value, 'NULL') === 0 ? null : $value;
    }

    private function decodeEscape(string $char): string
    {
        return match ($char) {
            '0' => "\0",
            'b' => "\x08",
            'n' => "\n",
            'r' => "\r",
            't' => "\t",
            'Z' => "\x1a",
            default => $char,
        };
    }
}
