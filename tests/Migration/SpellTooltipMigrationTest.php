<?php

namespace App\Tests\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\Query\Query;
use DoctrineMigrations\Version20261103120000;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class SpellTooltipMigrationTest extends TestCase
{
    private ?Connection $connection = null;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite is required for the in-memory migration test.');
        }
        require_once dirname(__DIR__, 2).'/migrations/Version20261103120000.php';
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    }

    protected function tearDown(): void
    {
        $this->connection?->close();
    }

    public function testMigrationCreatesAndDropsSpellTooltipTable(): void
    {
        $up = new Version20261103120000($this->connection, new NullLogger());
        $up->up(new Schema());
        $this->execute($up->getSql());
        self::assertTrue($this->connection->createSchemaManager()->tablesExist(['wow_spell_tooltips']));

        $down = new Version20261103120000($this->connection, new NullLogger());
        $down->down(new Schema());
        $this->execute($down->getSql());
        self::assertFalse($this->connection->createSchemaManager()->tablesExist(['wow_spell_tooltips']));
    }

    /** @param Query[] $queries */
    private function execute(array $queries): void
    {
        foreach ($queries as $query) {
            $this->connection->executeStatement($query->getStatement(), $query->getParameters(), $query->getTypes());
        }
    }
}
