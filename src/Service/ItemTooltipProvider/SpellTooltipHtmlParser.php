<?php

namespace App\Service\ItemTooltipProvider;

use DOMDocument;
use DOMElement;
use DOMXPath;

final class SpellTooltipHtmlParser
{
    /** @return array{name: string, spell_rank: int|null, description: string}|null */
    public function parse(int $spellId, string $html): ?array
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="utf-8" ?><div id="spell-tooltip-parser-root">'.$html.'</div>');
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $xpath = new DOMXPath($document);
        $nodes = $xpath->query(sprintf('//*[@id="t%d-generic"]', $spellId));
        $root = $nodes !== false ? $nodes->item(0) : null;
        if (!$root instanceof DOMElement) {
            if (preg_match('/<\s*(?:html|body)\b/i', $html)) {
                return null;
            }
            $roots = $xpath->query('//*[@id="spell-tooltip-parser-root"]');
            $root = $roots !== false ? $roots->item(0) : null;
        }
        if (!$root instanceof DOMElement) {
            return null;
        }

        $name = '';
        $boldNodes = $xpath->query('.//b', $root);
        if ($boldNodes !== false) {
            foreach ($boldNodes as $boldNode) {
                if (!$boldNode instanceof DOMElement || preg_match('/\bRank\s+\d+/i', $boldNode->textContent)) {
                    continue;
                }
                $name = $this->normalizeText($boldNode->textContent);
                if ($name !== '') {
                    break;
                }
            }
        }
        if ($name === '') {
            return null;
        }

        $rank = null;
        $rankNodes = $xpath->query('.//th//b[contains(concat(" ", normalize-space(@class), " "), " q0 ")]', $root);
        $rankText = $rankNodes !== false ? $rankNodes->item(0)?->textContent : null;
        if (is_string($rankText) && preg_match('/^Rank\s+(\d+)$/i', $this->normalizeText($rankText), $matches)) {
            $rank = (int) $matches[1];
        }

        $description = '';
        $descriptionNodes = $xpath->query('.//span[contains(concat(" ", normalize-space(@class), " "), " q ")]', $root);
        $descriptionNode = $descriptionNodes !== false ? $descriptionNodes->item(0) : null;
        if ($descriptionNode !== null) {
            $description = $this->convertRawDurations($this->normalizeText($descriptionNode->textContent));
        }

        return ['name' => $name, 'spell_rank' => $rank, 'description' => $description];
    }

    /**
     * Cavern of Time prints some spell formulas without dividing their millisecond
     * values, e.g. "100.2 sec" for 0.1 sec and "120000 min" for 2 min.
     */
    private function convertRawDurations(string $text): string
    {
        $seconds = static fn (float $value): string => rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');

        $text = preg_replace_callback(
            '/\b(\d{3,})\.\d (sec|seconds)\b/',
            static fn (array $match): string => $seconds((int) $match[1] / 1000).' '.$match[2],
            $text,
        ) ?? $text;
        $text = preg_replace_callback(
            '/\b(\d{4,}) (sec|seconds)\b/',
            static fn (array $match): string => $seconds((int) $match[1] / 1000).' '.$match[2],
            $text,
        ) ?? $text;

        return preg_replace_callback(
            '/\b(\d{5,}) (min|minutes)\b/',
            static fn (array $match): string => $seconds((int) $match[1] / 60000).' '.$match[2],
            $text,
        ) ?? $text;
    }

    private function normalizeText(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? $text);
    }
}
