<?php

namespace App\Repository;

use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

class WowSpellTooltipRepository
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /** @param int[] $spellIds @return array<int, array<string, mixed>> */
    public function findForSpellIds(array $spellIds): array
    {
        $spellIds = array_values(array_unique(array_filter(array_map('intval', $spellIds), static fn (int $id): bool => $id > 0)));
        if ($spellIds === []) {
            return [];
        }

        $result = [];
        $rows = $this->connection->executeQuery(
            'SELECT spell_id, name, spell_rank, description, source, source_url, fetched_at
             FROM wow_spell_tooltips WHERE spell_id IN (?)',
            [$spellIds],
            [ArrayParameterType::INTEGER],
        )->fetchAllAssociative();
        foreach ($rows as $row) {
            $spellId = (int) $row['spell_id'];
            $result[$spellId] = [
                'spell_id' => $spellId,
                'name' => (string) $row['name'],
                'spell_rank' => $row['spell_rank'] !== null ? (int) $row['spell_rank'] : null,
                'description' => (string) $row['description'],
                'source' => (string) $row['source'],
                'source_url' => (string) $row['source_url'],
                'fetched_at' => $row['fetched_at'],
            ];
        }

        return $result;
    }

    /** @return int[] distinct spell IDs referenced by stored talent tree snapshots */
    public function findTalentSpellIdsFromSnapshots(): array
    {
        $spellIds = [];
        $lastId = 0;
        // Snapshots are large, so read them in batches instead of loading the whole table.
        do {
            $rows = $this->connection->fetchAllAssociative(
                'SELECT id, talent_trees_data FROM character_snapshots WHERE id > ? ORDER BY id LIMIT 200',
                [$lastId],
            );
            foreach ($rows as $row) {
                $lastId = (int) $row['id'];
                $value = $row['talent_trees_data'];
                $data = is_array($value) ? $value : (is_string($value) ? json_decode($value, true) : null);
                if (is_array($data)) {
                    $this->collectTalentSpellIds($data, $spellIds);
                }
            }
        } while ($rows !== []);

        $ids = array_keys($spellIds);
        sort($ids, SORT_NUMERIC);

        return $ids;
    }

    public function shouldQueue(int $spellId): bool
    {
        return $this->findQueueableSpellIds([$spellId]) !== [];
    }

    /** @param int[] $spellIds @return int[] */
    public function findQueueableSpellIds(array $spellIds): array
    {
        $spellIds = array_values(array_unique(array_filter(array_map('intval', $spellIds), static fn (int $id): bool => $id > 0)));
        if ($spellIds === []) {
            return [];
        }

        $existing = [];
        $rows = $this->connection->executeQuery(
            'SELECT spell_id, source, fetched_at FROM wow_spell_tooltips WHERE spell_id IN (?)',
            [$spellIds],
            [ArrayParameterType::INTEGER],
        )->fetchAllAssociative();
        foreach ($rows as $row) {
            $existing[(int) $row['spell_id']] = $row;
        }

        $oldQueueThreshold = (new DateTimeImmutable('-15 minutes'))->format('Y-m-d H:i:s');
        return array_values(array_filter($spellIds, static function (int $spellId) use ($existing, $oldQueueThreshold): bool {
            $row = $existing[$spellId] ?? null;
            return $row === null
                || ((string) $row['source'] === 'queued' && (string) $row['fetched_at'] < $oldQueueThreshold);
        }));
    }

    public function markQueued(int $spellId): void
    {
        $url = sprintf('https://wotlk.cavernoftime.com/spell=%d', $spellId);
        $this->save($spellId, '', null, '', 'queued', $url);
    }

    /** @param array{name: string, spell_rank: int|null, description: string, source_url: string} $tooltip */
    public function saveTooltip(int $spellId, array $tooltip, string $source = 'cavern_of_time'): void
    {
        $this->save(
            $spellId,
            $tooltip['name'],
            $tooltip['spell_rank'],
            $tooltip['description'],
            $source,
            $tooltip['source_url'],
        );
    }

    public function saveNotFound(int $spellId, string $sourceUrl): void
    {
        $this->save($spellId, '', null, '', 'not_found', $sourceUrl);
    }

    private function save(
        int $spellId,
        string $name,
        ?int $rank,
        string $description,
        string $source,
        string $sourceUrl,
    ): void {
        $this->connection->delete('wow_spell_tooltips', ['spell_id' => $spellId]);
        $this->connection->insert('wow_spell_tooltips', [
            'spell_id' => $spellId,
            'name' => $name,
            'spell_rank' => $rank,
            'description' => $description,
            'source' => $source,
            'source_url' => $sourceUrl,
            'fetched_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }

    /** @param array<mixed> $value @param array<int, true> $spellIds */
    private function collectTalentSpellIds(array $value, array &$spellIds): void
    {
        if (isset($value['spellId']) && is_scalar($value['spellId'])) {
            $spellId = (int) $value['spellId'];
            if ($spellId > 0) {
                $spellIds[$spellId] = true;
            }
        }
        foreach ($value as $child) {
            if (is_array($child)) {
                $this->collectTalentSpellIds($child, $spellIds);
            }
        }
    }
}
