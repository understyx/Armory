<?php

namespace App\Service\ItemTooltipProvider;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

class ItemTooltipHtmlParser
{
    public const VERSION = '1';

    /**
     * @return array{
     *     effects: array<int, array{spell_id: int|null, trigger_type: string, description: string}>,
     *     set: array{name: string, members: array<int, array{item_id: int, name: string}>, bonuses: array<int, array{required_count: int, spell_id: int|null, description: string}>}|null
     * }
     */
    public function parse(string $html): array
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML(
                '<?xml encoding="utf-8" ?><div id="tooltip-parser-root">' . $html . '</div>',
                LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $xpath = new DOMXPath($document);

        return [
            'effects' => $this->parseEffects($xpath),
            'set' => $this->parseSet($xpath),
        ];
    }

    /** @return array<int, array{spell_id: int|null, trigger_type: string, description: string}> */
    private function parseEffects(DOMXPath $xpath): array
    {
        $effects = [];
        $nodes = $xpath->query('//span[contains(concat(" ", normalize-space(@class), " "), " q2 ")]');
        if ($nodes === false) {
            return [];
        }

        foreach ($nodes as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }

            $text = $this->normalizeText($node->textContent);
            if (!preg_match('/^(Equip|Use|Chance on hit)\s*:\s*(.+)$/iu', $text, $matches)) {
                continue;
            }

            $spellId = $this->firstLinkedId($xpath, $node, 'spell');
            // Plain green stat lines are already generated from Trinity item stats.
            // Linked spell lines are the proc/use effects that the world DB cannot describe.
            if ($spellId === null) {
                continue;
            }

            $effects[] = [
                'spell_id' => $spellId,
                'trigger_type' => $this->normalizeTrigger($matches[1]),
                'description' => trim($matches[2]),
            ];
        }

        return $effects;
    }

    /**
     * @return array{name: string, members: array<int, array{item_id: int, name: string}>, bonuses: array<int, array{required_count: int, spell_id: int|null, description: string}>}|null
     */
    private function parseSet(DOMXPath $xpath): ?array
    {
        $setLinks = $xpath->query('//a[contains(@href, "item-set=") or contains(@href, "itemset=")]');
        $setLink = $setLinks !== false ? $setLinks->item(0) : null;
        if (!$setLink instanceof DOMElement) {
            return null;
        }

        $members = [];
        $memberNodes = $xpath->query('//div[contains(concat(" ", normalize-space(@class), " "), " indent ")]//a[contains(@href, "item=")]');
        if ($memberNodes !== false) {
            foreach ($memberNodes as $memberNode) {
                if (!$memberNode instanceof DOMElement || !preg_match('/item=(\d+)/', $memberNode->getAttribute('href'), $matches)) {
                    continue;
                }
                $members[] = [
                    'item_id' => (int) $matches[1],
                    'name' => $this->normalizeText($memberNode->textContent),
                ];
            }
        }

        $bonuses = [];
        $bonusNodes = $xpath->query('//span[contains(normalize-space(.), "Set")]');
        if ($bonusNodes !== false) {
            foreach ($bonusNodes as $bonusNode) {
                if (!$bonusNode instanceof DOMElement || $bonusNode->getElementsByTagName('span')->length > 0) {
                    continue;
                }
                $text = $this->normalizeText($bonusNode->textContent);
                if (!preg_match('/^\((\d+)\)\s*Set\s*:\s*(.+)$/iu', $text, $matches)) {
                    continue;
                }
                $bonuses[] = [
                    'required_count' => (int) $matches[1],
                    'spell_id' => $this->firstLinkedId($xpath, $bonusNode, 'spell'),
                    'description' => trim($matches[2]),
                ];
            }
        }

        return [
            'name' => $this->normalizeText($setLink->textContent),
            'members' => $members,
            'bonuses' => $bonuses,
        ];
    }

    private function firstLinkedId(DOMXPath $xpath, DOMNode $context, string $kind): ?int
    {
        $links = $xpath->query(sprintf('.//a[contains(@href, "%s=")]', $kind), $context);
        $link = $links !== false ? $links->item(0) : null;
        if (!$link instanceof DOMElement || !preg_match(sprintf('/%s=(\d+)/', preg_quote($kind, '/')), $link->getAttribute('href'), $matches)) {
            return null;
        }

        return (int) $matches[1];
    }

    private function normalizeText(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? $text);
    }

    private function normalizeTrigger(string $trigger): string
    {
        return match (strtolower($trigger)) {
            'use' => 'use',
            'chance on hit' => 'chance_on_hit',
            default => 'equip',
        };
    }
}
