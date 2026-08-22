<?php

namespace App\Command;

use App\Service\LegacyGearScoreLoader;
use App\Service\MySqlDumpTableReader;
use Doctrine\DBAL\Connection;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\KernelInterface;

#[AsCommand(
    name: 'app:import-trinity-items',
    description: 'Import canonical WotLK item data from a TrinityCore world dump while retaining precomputed GearScore values.',
)]
class ImportTrinityItemsCommand extends Command
{
    private const EXPECTED_COLUMN_COUNT = 139;
    private const GEAR_SCORE_VERSION = 'legacy-items-sql-v1';

    /** @var string[] */
    private const DATABASE_COLUMNS = [
        'item_id', 'name', 'item_level', 'quality', 'type', 'requires', 'class', 'subclass',
        'gem_slots', 'gear_score', 'tooltip_data', 'source_build', 'gear_score_source', 'gear_score_version',
    ];

    public function __construct(
        private readonly Connection $connection,
        private readonly KernelInterface $kernel,
        private readonly MySqlDumpTableReader $dumpReader,
        private readonly LegacyGearScoreLoader $gearScoreLoader,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'file',
                InputArgument::OPTIONAL,
                'Path to the TrinityCore full world SQL dump',
                $this->kernel->getProjectDir() . '/TDB_full_world_335.25101_2025_10_21.sql'
            )
            ->addOption(
                'gear-score-file',
                null,
                InputOption::VALUE_REQUIRED,
                'Legacy item SQL used only as the precomputed GearScore overlay',
                $this->kernel->getProjectDir() . '/data/items.sql'
            )
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Rows written per transaction batch', '250');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dumpPath = (string) $input->getArgument('file');
        $gearScorePath = (string) $input->getOption('gear-score-file');
        $batchSize = max(1, (int) $input->getOption('batch-size'));

        if (!is_file($dumpPath)) {
            $io->error(sprintf('TrinityCore dump not found: %s', $dumpPath));

            return Command::FAILURE;
        }

        $gearScores = [];
        if ($gearScorePath !== '' && is_file($gearScorePath)) {
            $gearScores = $this->gearScoreLoader->load($gearScorePath);
        } elseif ($gearScorePath !== '') {
            $io->warning(sprintf('GearScore overlay not found; existing scores will be preserved: %s', $gearScorePath));
        }

        $io->title('Importing canonical TrinityCore items');
        $io->text(sprintf('Loaded %d precomputed GearScore values.', count($gearScores)));

        $batch = [];
        $processed = 0;
        $withGearScore = 0;

        try {
            foreach ($this->dumpReader->readRows($dumpPath, 'item_template') as $row) {
                if (count($row) !== self::EXPECTED_COLUMN_COUNT) {
                    throw new RuntimeException(sprintf(
                        'Unexpected item_template column count for item %s: expected %d, got %d.',
                        $row[0] ?? 'unknown',
                        self::EXPECTED_COLUMN_COUNT,
                        count($row)
                    ));
                }

                $itemId = (int) $row[0];
                $gearScore = $gearScores[$itemId] ?? null;
                if ($gearScore !== null) {
                    $withGearScore++;
                }

                $batch[] = $this->mapRow($row, $gearScore);
                $processed++;

                if (count($batch) >= $batchSize) {
                    $this->writeBatch($batch);
                    $batch = [];
                    $io->write(sprintf("\rProcessed %d items", $processed));
                }
            }

            if ($batch !== []) {
                $this->writeBatch($batch);
            }
        } catch (\Throwable $exception) {
            $io->newLine();
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->newLine(2);
        $io->success(sprintf(
            'Imported %d canonical items; %d retained a precomputed GearScore.',
            $processed,
            $withGearScore
        ));

        return Command::SUCCESS;
    }

    /**
     * @param array<int, string|null> $row
     * @return array<string, int|string|null>
     */
    private function mapRow(array $row, ?int $gearScore): array
    {
        $stats = [];
        $statsCount = min(10, (int) $row[27]);
        for ($index = 0; $index < $statsCount; $index++) {
            $typeIndex = 28 + ($index * 2);
            $type = (int) $row[$typeIndex];
            $value = (int) $row[$typeIndex + 1];
            if ($type !== 0 && $value !== 0) {
                $stats[] = ['type' => $type, 'value' => $value];
            }
        }

        $spells = [];
        for ($index = 0; $index < 5; $index++) {
            $offset = 66 + ($index * 7);
            $spellId = (int) $row[$offset];
            // Trinity uses -1 in unused spell slots on some items.
            if ($spellId > 0) {
                $spells[] = [
                    'id' => $spellId,
                    'trigger' => (int) $row[$offset + 1],
                    'charges' => (int) $row[$offset + 2],
                    'ppm' => (float) $row[$offset + 3],
                    'cooldown' => (int) $row[$offset + 4],
                ];
            }
        }

        $sockets = [];
        foreach ([[119, 120], [121, 122], [123, 124]] as [$colorIndex, $contentIndex]) {
            $color = (int) $row[$colorIndex];
            if ($color !== 0) {
                $sockets[] = ['color' => $color, 'content' => (int) $row[$contentIndex]];
            }
        }

        $tooltip = [
            'flags' => (int) $row[7],
            'flags_extra' => (int) $row[8],
            'buy_count' => (int) $row[9],
            'buy_price' => (int) $row[10],
            'sell_price' => (int) $row[11],
            'allowable_class' => (int) $row[13],
            'allowable_race' => (int) $row[14],
            'required_skill' => (int) $row[17],
            'required_skill_rank' => (int) $row[18],
            'required_spell' => (int) $row[19],
            'required_reputation_faction' => (int) $row[22],
            'required_reputation_rank' => (int) $row[23],
            'max_count' => (int) $row[24],
            'stats' => $stats,
            'scaling_stat_distribution' => (int) $row[48],
            'scaling_stat_value' => (int) $row[49],
            'damage' => [
                ['min' => (float) $row[50], 'max' => (float) $row[51], 'type' => (int) $row[52]],
                ['min' => (float) $row[53], 'max' => (float) $row[54], 'type' => (int) $row[55]],
            ],
            'armor' => (int) $row[56],
            'resistances' => [
                'holy' => (int) $row[57],
                'fire' => (int) $row[58],
                'nature' => (int) $row[59],
                'frost' => (int) $row[60],
                'shadow' => (int) $row[61],
                'arcane' => (int) $row[62],
            ],
            'delay' => (int) $row[63],
            'spells' => $spells,
            'bonding' => (int) $row[101],
            'description' => (string) ($row[102] ?? ''),
            'random_property' => (int) $row[110],
            'random_suffix' => (int) $row[111],
            'block' => (int) $row[112],
            'item_set_id' => (int) $row[113],
            'max_durability' => (int) $row[114],
            'sockets' => $sockets,
            'socket_bonus_id' => (int) $row[125],
            'gem_properties_id' => (int) $row[126],
            'item_limit_category' => (int) $row[130],
        ];

        return [
            'item_id' => (int) $row[0],
            'name' => (string) $row[4],
            'item_level' => (int) $row[15],
            'quality' => (int) $row[6],
            'type' => (int) $row[12],
            'requires' => (int) $row[16],
            'class' => (int) $row[1],
            'subclass' => (int) $row[2],
            'gem_slots' => count($sockets),
            'gear_score' => $gearScore,
            'tooltip_data' => json_encode($tooltip, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'source_build' => $row[138] !== null ? (int) $row[138] : null,
            'gear_score_source' => $gearScore !== null ? 'legacy-items.sql' : null,
            'gear_score_version' => $gearScore !== null ? self::GEAR_SCORE_VERSION : null,
        ];
    }

    /**
     * @param array<int, array<string, int|string|null>> $batch
     */
    private function writeBatch(array $batch): void
    {
        $platform = strtolower($this->connection->getDatabasePlatform()->getName());
        $quotedColumns = implode(', ', self::DATABASE_COLUMNS);
        $valueGroups = [];
        $parameters = [];

        foreach ($batch as $rowIndex => $row) {
            $placeholders = [];
            foreach (self::DATABASE_COLUMNS as $column) {
                $parameter = sprintf('%s_%d', $column, $rowIndex);
                $placeholders[] = ':' . $parameter;
                $parameters[$parameter] = $row[$column];
            }
            $valueGroups[] = '(' . implode(', ', $placeholders) . ')';
        }

        $updates = [];
        foreach (self::DATABASE_COLUMNS as $column) {
            if ($column === 'item_id') {
                continue;
            }

            if (in_array($column, ['gear_score', 'gear_score_source', 'gear_score_version'], true)) {
                $updates[] = $this->coalescingUpdate($platform, $column);
            } else {
                $updates[] = $this->standardUpdate($platform, $column);
            }
        }

        $sql = sprintf(
            'INSERT INTO wow_items (%s) VALUES %s %s',
            $quotedColumns,
            implode(', ', $valueGroups),
            $this->conflictClause($platform, $updates)
        );

        $this->connection->executeStatement($sql, $parameters);
    }

    /** @param string[] $updates */
    private function conflictClause(string $platform, array $updates): string
    {
        if (str_contains($platform, 'mysql') || str_contains($platform, 'mariadb')) {
            return 'ON DUPLICATE KEY UPDATE ' . implode(', ', $updates);
        }

        if (str_contains($platform, 'postgre') || str_contains($platform, 'sqlite')) {
            return 'ON CONFLICT (item_id) DO UPDATE SET ' . implode(', ', $updates);
        }

        throw new RuntimeException(sprintf('Unsupported database platform: %s', $platform));
    }

    private function standardUpdate(string $platform, string $column): string
    {
        if (str_contains($platform, 'mysql') || str_contains($platform, 'mariadb')) {
            return sprintf('%s = VALUES(%s)', $column, $column);
        }

        return sprintf('%s = excluded.%s', $column, $column);
    }

    private function coalescingUpdate(string $platform, string $column): string
    {
        if (str_contains($platform, 'mysql') || str_contains($platform, 'mariadb')) {
            return sprintf('%s = COALESCE(VALUES(%s), %s)', $column, $column, $column);
        }

        return sprintf('%s = COALESCE(excluded.%s, wow_items.%s)', $column, $column, $column);
    }
}
