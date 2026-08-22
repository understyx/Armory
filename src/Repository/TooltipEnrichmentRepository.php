<?php

namespace App\Repository;

use App\Service\ItemTooltipProvider\ItemTooltipHtmlParser;
use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

class TooltipEnrichmentRepository
{
    private const LOCALE = 'enUS';

    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @param int[] $itemIds
     * @param array<int, int> $setIdsByItem
     * @return array<int, array{effects: array<int, array<string, mixed>>, set: array<string, mixed>|null, checked: bool}>
     */
    public function findForItems(array $itemIds, array $setIdsByItem = [], string $locale = self::LOCALE): array
    {
        $itemIds = array_values(array_unique(array_map('intval', $itemIds)));
        $result = [];
        foreach ($itemIds as $itemId) {
            $result[$itemId] = ['effects' => [], 'set' => null, 'checked' => false];
        }
        if ($itemIds === []) {
            return $result;
        }

        $effectRows = $this->connection->executeQuery(
            'SELECT item_id, position, spell_id, trigger_type, description, source
             FROM wow_item_effects
             WHERE item_id IN (?) AND locale = ?
             ORDER BY item_id, position',
            [$itemIds, $locale],
            [ArrayParameterType::INTEGER, \PDO::PARAM_STR]
        )->fetchAllAssociative();
        foreach ($effectRows as $row) {
            $itemId = (int) $row['item_id'];
            $result[$itemId]['effects'][] = [
                'spell_id' => $row['spell_id'] !== null ? (int) $row['spell_id'] : null,
                'type' => (string) $row['trigger_type'],
                'text' => (string) $row['description'],
                'source' => (string) $row['source'],
            ];
        }

        $setIds = array_values(array_unique(array_filter(array_map('intval', $setIdsByItem))));
        $sets = $setIds !== [] ? $this->findSets($setIds, $locale) : [];
        foreach ($setIdsByItem as $itemId => $setId) {
            if (isset($result[$itemId], $sets[$setId])) {
                $result[$itemId]['set'] = $sets[$setId];
            }
        }

        $checkedRows = $this->connection->executeQuery(
            'SELECT DISTINCT item_id FROM wow_external_tooltip_cache
             WHERE item_id IN (?) AND provider = ? AND locale = ? AND status_code = 200 AND parser_version = ?',
            [$itemIds, 'enrichment', $locale, ItemTooltipHtmlParser::VERSION],
            [ArrayParameterType::INTEGER, \PDO::PARAM_STR, \PDO::PARAM_STR, \PDO::PARAM_STR]
        )->fetchFirstColumn();
        foreach ($checkedRows as $itemId) {
            $result[(int) $itemId]['checked'] = true;
        }

        return $result;
    }

    /** @return array{item_id: int, tooltip: array<string, mixed>, set_id: int}|null */
    public function findItemContext(int $itemId): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT item_id, tooltip_data FROM wow_items WHERE item_id = ?',
            [$itemId]
        );
        if ($row === false) {
            return null;
        }

        $tooltip = $this->decodeJson($row['tooltip_data'] ?? null);

        return [
            'item_id' => (int) $row['item_id'],
            'tooltip' => $tooltip,
            'set_id' => (int) ($tooltip['item_set_id'] ?? 0),
        ];
    }

    /**
     * @return array<int, array{item_id: int, set_id: int}>
     */
    public function findCandidateItems(?int $onlyItemId = null, int $limit = 0): array
    {
        $sql = 'SELECT item_id, tooltip_data FROM wow_items WHERE tooltip_data IS NOT NULL';
        $params = [];
        if ($onlyItemId !== null) {
            $sql .= ' AND item_id = ?';
            $params[] = $onlyItemId;
        }
        $sql .= ' ORDER BY item_id';

        $result = [];
        foreach ($this->connection->fetchAllAssociative($sql, $params) as $row) {
            $tooltip = $this->decodeJson($row['tooltip_data'] ?? null);
            if (($tooltip['spells'] ?? []) === [] && (int) ($tooltip['item_set_id'] ?? 0) <= 0) {
                continue;
            }
            $result[] = [
                'item_id' => (int) $row['item_id'],
                'set_id' => (int) ($tooltip['item_set_id'] ?? 0),
            ];
            if ($limit > 0 && count($result) >= $limit) {
                break;
            }
        }

        return $result;
    }

    public function shouldQueue(int $itemId, string $locale = self::LOCALE): bool
    {
        if ($this->hasSuccessfulEnrichment($itemId, $locale)) {
            return false;
        }

        $recentQueue = (bool) $this->connection->fetchOne(
            'SELECT 1 FROM wow_external_tooltip_cache
             WHERE item_id = ? AND provider = ? AND locale = ? AND status_code = 102 AND fetched_at >= ? LIMIT 1',
            [$itemId, 'queue', $locale, (new DateTimeImmutable('-15 minutes'))->format('Y-m-d H:i:s')]
        );

        return !$recentQueue;
    }

    public function hasSuccessfulEnrichment(int $itemId, string $locale = self::LOCALE): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT 1 FROM wow_external_tooltip_cache
             WHERE item_id = ? AND provider = ? AND locale = ? AND status_code = 200 AND parser_version = ? LIMIT 1',
            [$itemId, 'enrichment', $locale, ItemTooltipHtmlParser::VERSION]
        );
    }

    public function hasItemSet(int $setId, string $locale = self::LOCALE): bool
    {
        return $setId > 0 && (bool) $this->connection->fetchOne(
            'SELECT 1 FROM wow_item_sets WHERE set_id = ? AND locale = ? LIMIT 1',
            [$setId, $locale]
        );
    }

    public function markQueued(int $itemId, string $locale = self::LOCALE): void
    {
        $this->recordAttempt($itemId, 'queue', 102, null, null, $locale);
    }

    public function recordAttempt(
        int $itemId,
        string $provider,
        int $statusCode,
        ?string $rawResponse,
        ?string $error,
        string $locale = self::LOCALE,
    ): void {
        $this->connection->delete('wow_external_tooltip_cache', [
            'item_id' => $itemId,
            'provider' => $provider,
            'locale' => $locale,
        ]);
        $this->connection->insert('wow_external_tooltip_cache', [
            'item_id' => $itemId,
            'provider' => $provider,
            'locale' => $locale,
            'status_code' => $statusCode,
            'raw_response' => $rawResponse,
            'parser_version' => ItemTooltipHtmlParser::VERSION,
            'error' => $error,
            'fetched_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @param array<int, array{spell_id: int|null, trigger_type: string, description: string, source: string, source_url: string}> $effects
     * @param array{name: string, source: string, source_url: string, members: array<int, array{item_id: int, name: string}>, bonuses: array<int, array{required_count: int, spell_id: int|null, description: string}>}|null $set
     */
    public function saveEnrichment(
        int $itemId,
        array $effects,
        ?array $set,
        int $setId,
        string $locale = self::LOCALE,
    ): void {
        $this->connection->transactional(function (Connection $connection) use ($itemId, $effects, $set, $setId, $locale): void {
            $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');
            $connection->delete('wow_item_effects', ['item_id' => $itemId, 'locale' => $locale]);
            foreach ($effects as $position => $effect) {
                $connection->insert('wow_item_effects', [
                    'item_id' => $itemId,
                    'position' => $position,
                    'spell_id' => $effect['spell_id'],
                    'trigger_type' => $effect['trigger_type'],
                    'description' => $effect['description'],
                    'source' => $effect['source'],
                    'locale' => $locale,
                    'fetched_at' => $now,
                ]);
                if ($effect['spell_id'] !== null) {
                    $connection->delete('wow_spells', ['spell_id' => $effect['spell_id'], 'locale' => $locale]);
                    $connection->insert('wow_spells', [
                        'spell_id' => $effect['spell_id'],
                        'locale' => $locale,
                        'name' => null,
                        'description' => $effect['description'],
                        'source' => $effect['source'],
                        'source_url' => $effect['source_url'],
                        'fetched_at' => $now,
                    ]);
                }
            }

            if ($set === null || $setId <= 0) {
                return;
            }

            $connection->delete('wow_item_set_members', ['set_id' => $setId, 'locale' => $locale]);
            $connection->delete('wow_item_set_bonuses', ['set_id' => $setId, 'locale' => $locale]);
            $connection->delete('wow_item_sets', ['set_id' => $setId, 'locale' => $locale]);
            $connection->insert('wow_item_sets', [
                'set_id' => $setId,
                'name' => $set['name'],
                'locale' => $locale,
                'source' => $set['source'],
                'fetched_at' => $now,
            ]);
            foreach ($set['members'] as $position => $member) {
                $connection->insert('wow_item_set_members', [
                    'set_id' => $setId,
                    'item_id' => $member['item_id'],
                    'name' => $member['name'],
                    'position' => $position,
                    'locale' => $locale,
                ]);
            }
            foreach ($set['bonuses'] as $position => $bonus) {
                $connection->insert('wow_item_set_bonuses', [
                    'set_id' => $setId,
                    'required_count' => $bonus['required_count'],
                    'position' => $position,
                    'spell_id' => $bonus['spell_id'],
                    'description' => $bonus['description'],
                    'source' => $set['source'],
                    'locale' => $locale,
                ]);
                if ($bonus['spell_id'] !== null) {
                    $connection->delete('wow_spells', ['spell_id' => $bonus['spell_id'], 'locale' => $locale]);
                    $connection->insert('wow_spells', [
                        'spell_id' => $bonus['spell_id'],
                        'locale' => $locale,
                        'name' => null,
                        'description' => $bonus['description'],
                        'source' => $set['source'],
                        'source_url' => $set['source_url'],
                        'fetched_at' => $now,
                    ]);
                }
            }
        });
    }

    /** @return array<int, array<string, mixed>> */
    private function findSets(array $setIds, string $locale): array
    {
        $sets = [];
        $rows = $this->connection->executeQuery(
            'SELECT set_id, name, source FROM wow_item_sets WHERE set_id IN (?) AND locale = ?',
            [$setIds, $locale],
            [ArrayParameterType::INTEGER, \PDO::PARAM_STR]
        )->fetchAllAssociative();
        foreach ($rows as $row) {
            $sets[(int) $row['set_id']] = [
                'id' => (int) $row['set_id'],
                'name' => (string) $row['name'],
                'source' => (string) $row['source'],
                'members' => [],
                'bonuses' => [],
            ];
        }
        if ($sets === []) {
            return [];
        }

        $memberRows = $this->connection->executeQuery(
            'SELECT set_id, item_id, name FROM wow_item_set_members
             WHERE set_id IN (?) AND locale = ? ORDER BY set_id, position',
            [array_keys($sets), $locale],
            [ArrayParameterType::INTEGER, \PDO::PARAM_STR]
        )->fetchAllAssociative();
        foreach ($memberRows as $row) {
            $sets[(int) $row['set_id']]['members'][] = [
                'item_id' => (int) $row['item_id'],
                'name' => (string) $row['name'],
            ];
        }

        $bonusRows = $this->connection->executeQuery(
            'SELECT set_id, required_count, spell_id, description, source FROM wow_item_set_bonuses
             WHERE set_id IN (?) AND locale = ? ORDER BY set_id, position',
            [array_keys($sets), $locale],
            [ArrayParameterType::INTEGER, \PDO::PARAM_STR]
        )->fetchAllAssociative();
        foreach ($bonusRows as $row) {
            $sets[(int) $row['set_id']]['bonuses'][] = [
                'required_count' => (int) $row['required_count'],
                'spell_id' => $row['spell_id'] !== null ? (int) $row['spell_id'] : null,
                'description' => (string) $row['description'],
                'source' => (string) $row['source'],
            ];
        }

        return $sets;
    }

    /** @return array<string, mixed> */
    private function decodeJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (!is_string($value) || $value === '') {
            return [];
        }
        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
