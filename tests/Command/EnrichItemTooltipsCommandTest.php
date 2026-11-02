<?php

namespace App\Tests\Command;

use App\Command\EnrichItemTooltipsCommand;
use App\Repository\TooltipEnrichmentRepository;
use App\Service\ItemTooltipProvider\ItemTooltipProviderInterface;
use App\Service\TooltipEnrichmentService;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class EnrichItemTooltipsCommandTest extends TestCase
{
    public function testDoesNotBulkEnrichWithoutExplicitAllOption(): void
    {
        $repository = $this->createMock(TooltipEnrichmentRepository::class);
        $repository->expects(self::never())->method('findCandidateItems');
        $service = $this->createMock(TooltipEnrichmentService::class);
        $service->expects(self::never())->method('enrichItem');

        $tester = new CommandTester(new EnrichItemTooltipsCommand($repository, $service));
        $result = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $result);
        self::assertStringContainsString('No items were changed', $tester->getDisplay());
    }

    public function testGlyphsOptionLimitsBackfillToGlyphItemCandidates(): void
    {
        $repository = $this->createMock(TooltipEnrichmentRepository::class);
        $repository->expects(self::once())
            ->method('findCandidateItems')
            ->with(null, 0, true)
            ->willReturn([['item_id' => 43673, 'set_id' => 0]]);
        $service = $this->createMock(TooltipEnrichmentService::class);
        $service->expects(self::once())->method('enrichItem')->with(43673, 0, 'enUS', false)->willReturn(true);

        $tester = new CommandTester(new EnrichItemTooltipsCommand($repository, $service));
        $result = $tester->execute(['--glyphs' => true]);

        self::assertSame(Command::SUCCESS, $result);
        self::assertStringContainsString('Enriched or retained 1 of 1', $tester->getDisplay());
    }

    public function testGlyphsForceRefreshReplacesEffectRowsWithoutDuplicates(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $connection->executeStatement('CREATE TABLE wow_items (item_id INTEGER PRIMARY KEY, class INTEGER NOT NULL, tooltip_data TEXT)');
        $connection->executeStatement('CREATE TABLE wow_item_effects (
            id INTEGER PRIMARY KEY AUTOINCREMENT, item_id INTEGER NOT NULL, position INTEGER NOT NULL,
            spell_id INTEGER, trigger_type TEXT NOT NULL, description TEXT NOT NULL, source TEXT NOT NULL,
            locale TEXT NOT NULL, fetched_at TEXT NOT NULL, UNIQUE(item_id, position, locale)
        )');
        $connection->executeStatement('CREATE TABLE wow_spells (
            spell_id INTEGER NOT NULL, locale TEXT NOT NULL, name TEXT, description TEXT NOT NULL,
            source TEXT NOT NULL, source_url TEXT, fetched_at TEXT NOT NULL, PRIMARY KEY(spell_id, locale)
        )');
        $connection->executeStatement('CREATE TABLE wow_external_tooltip_cache (
            item_id INTEGER NOT NULL, provider TEXT NOT NULL, locale TEXT NOT NULL, status_code INTEGER NOT NULL,
            raw_response TEXT, parser_version TEXT NOT NULL, error TEXT, fetched_at TEXT NOT NULL,
            PRIMARY KEY(item_id, provider, locale)
        )');
        $connection->insert('wow_items', [
            'item_id' => 45756,
            'class' => 16,
            'tooltip_data' => json_encode(['spells' => [['id' => 56175, 'trigger' => 0]], 'item_set_id' => 0], JSON_THROW_ON_ERROR),
        ]);

        $repository = new TooltipEnrichmentRepository($connection);
        $provider = new class implements ItemTooltipProviderInterface {
            private int $calls = 0;
            public function getName(): string { return 'evowow'; }
            public function getPriority(): int { return 150; }
            public function fetch(int $itemId, string $locale = 'enUS'): array
            {
                $description = ++$this->calls === 1 ? 'Old effect.' : 'Refreshed effect.';
                return [
                    'provider' => 'evowow',
                    'source_url' => 'https://wotlk.evowow.com/?item=45756&power',
                    'raw_response' => '$WowheadPower.registerItem(...);',
                    'effects' => [['spell_id' => null, 'trigger_type' => 'use', 'description' => $description]],
                    'set' => null,
                ];
            }
        };
        $service = new TooltipEnrichmentService($repository, [$provider]);
        $pauseCalls = [];
        $command = new EnrichItemTooltipsCommand($repository, $service, static function (int $microseconds) use (&$pauseCalls): void {
            $pauseCalls[] = $microseconds;
        });
        $tester = new CommandTester($command);

        self::assertSame(Command::SUCCESS, $tester->execute(['--glyphs' => true]));
        self::assertSame(Command::SUCCESS, $tester->execute(['--glyphs' => true, '--force' => true]));
        $rows = $connection->fetchAllAssociative('SELECT spell_id, description, source FROM wow_item_effects WHERE item_id = 45756');

        self::assertCount(1, $rows);
        self::assertSame(56175, (int) $rows[0]['spell_id']);
        self::assertSame('Refreshed effect.', $rows[0]['description']);
        self::assertSame('evowow', $rows[0]['source']);
        self::assertSame([], $pauseCalls, 'A single item has no gap between candidates.');
    }

    public function testGlyphsOptionPauses300MillisecondsBetweenItems(): void
    {
        $repository = $this->createMock(TooltipEnrichmentRepository::class);
        $repository->method('findCandidateItems')->willReturn([
            ['item_id' => 43673, 'set_id' => 0],
            ['item_id' => 45756, 'set_id' => 0],
        ]);
        $service = $this->createMock(TooltipEnrichmentService::class);
        $service->expects(self::exactly(2))->method('enrichItem')->willReturn(true);
        $pauseCalls = [];
        $tester = new CommandTester(new EnrichItemTooltipsCommand(
            $repository,
            $service,
            static function (int $microseconds) use (&$pauseCalls): void { $pauseCalls[] = $microseconds; }
        ));

        self::assertSame(Command::SUCCESS, $tester->execute(['--glyphs' => true]));
        self::assertSame([300_000], $pauseCalls);
    }
}
