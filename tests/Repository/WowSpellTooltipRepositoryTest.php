<?php

namespace App\Tests\Repository;

use App\Repository\WowSpellTooltipRepository;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

final class WowSpellTooltipRepositoryTest extends TestCase
{
    private ?Connection $connection = null;
    private ?WowSpellTooltipRepository $repository = null;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite is required for the in-memory repository integration test.');
        }

        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->connection->executeStatement('CREATE TABLE character_snapshots (id INTEGER PRIMARY KEY, talent_trees_data TEXT)');
        $this->connection->executeStatement('CREATE TABLE wow_spell_tooltips (
            spell_id INTEGER PRIMARY KEY NOT NULL,
            name VARCHAR(255) NOT NULL,
            spell_rank INTEGER DEFAULT NULL,
            description TEXT NOT NULL,
            source VARCHAR(64) NOT NULL,
            source_url VARCHAR(512) NOT NULL,
            fetched_at DATETIME NOT NULL
        )');
        $this->repository = new WowSpellTooltipRepository($this->connection);
    }

    protected function tearDown(): void
    {
        $this->connection?->close();
    }

    public function testCollectsDistinctSnapshotIdsAndKeepsNotFoundSpellsTerminal(): void
    {
        $data = ['0' => [['tiers' => [[
            ['spellId' => 123],
            ['spellId' => '123'],
            ['spellId' => 456],
        ]]]]];
        $this->connection->insert('character_snapshots', ['talent_trees_data' => json_encode($data, JSON_THROW_ON_ERROR)]);

        self::assertSame([123, 456], $this->repository->findTalentSpellIdsFromSnapshots());
        self::assertTrue($this->repository->shouldQueue(456));
        $this->repository->markQueued(456);
        self::assertFalse($this->repository->shouldQueue(456));

        $this->repository->saveNotFound(456, 'https://wotlk.cavernoftime.com/spell=456');
        self::assertFalse($this->repository->shouldQueue(456));
        self::assertSame('not_found', $this->repository->findForSpellIds([456])[456]['source']);
        self::assertTrue($this->repository->shouldQueue(123));
    }
}
