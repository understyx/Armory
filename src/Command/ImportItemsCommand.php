<?php

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\KernelInterface;

#[AsCommand(
    name: 'app:import-items',
    description: 'Import WotLK WoW items from SQL dump into wow_items table.',
)]
class ImportItemsCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
        private readonly KernelInterface $kernel
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'file',
            InputArgument::OPTIONAL,
            'Path to items.sql dump file',
            $this->kernel->getProjectDir() . '/data/items.sql'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $filePath = $input->getArgument('file');

        if (!file_exists($filePath)) {
            $io->error(sprintf('File not found: %s', $filePath));
            return Command::FAILURE;
        }

        $io->title('Importing WoW Items');
        $io->text(sprintf('Reading file: %s', $filePath));

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            $io->error('Failed to read SQL file.');
            return Command::FAILURE;
        }

        $items = [];
        $runUpdateType20 = false;

        foreach ($lines as $line) {
            $line = trim($line);

            if (str_contains($line, 'UPDATE items SET type = 5 WHERE type = 20')) {
                $runUpdateType20 = true;
                continue;
            }

            if (!str_starts_with($line, '(')) {
                continue;
            }

            // Pattern matching for tuples: (itemID,'name',ItemLevel,quality,type,requires,class,subclass,gems,GearScore)
            if (preg_match("/^\((\d+),'((?:''|[^'])*)',(\d+),(\d+),(\d+),(\d+),(\d+),(\d+),(\d+),(-?\d+)\)[;,]?$/", $line, $matches)) {
                $itemId = (int) $matches[1];
                $name = str_replace("''", "'", $matches[2]);
                $itemLevel = (int) $matches[3];
                $quality = (int) $matches[4];
                $type = (int) $matches[5];
                $requires = (int) $matches[6];
                $class = (int) $matches[7];
                $subclass = (int) $matches[8];
                $gemSlots = (int) $matches[9];
                $gearScore = (int) $matches[10];

                $items[] = [
                    'item_id' => $itemId,
                    'name' => $name,
                    'item_level' => $itemLevel,
                    'quality' => $quality,
                    'type' => $type,
                    'requires' => $requires,
                    'class' => $class,
                    'subclass' => $subclass,
                    'gem_slots' => $gemSlots,
                    'gear_score' => $gearScore,
                ];
            }
        }

        $totalFound = count($items);
        $io->text(sprintf('Parsed %d items from dump.', $totalFound));

        if ($totalFound === 0) {
            $io->warning('No items parsed from the specified file.');
            return Command::SUCCESS;
        }

        // Determine DB platform insert syntax
        $platform = strtolower($this->connection->getDatabasePlatform()->getName());
        $insertPrefix = 'INSERT INTO wow_items (item_id, name, item_level, quality, type, requires, class, subclass, gem_slots, gear_score) VALUES ';
        $onConflictSuffix = '';

        if (str_contains($platform, 'sqlite')) {
            $insertPrefix = 'INSERT OR IGNORE INTO wow_items (item_id, name, item_level, quality, type, requires, class, subclass, gem_slots, gear_score) VALUES ';
        } elseif (str_contains($platform, 'postgre')) {
            $onConflictSuffix = ' ON CONFLICT (item_id) DO NOTHING';
        } elseif (str_contains($platform, 'mysql') || str_contains($platform, 'mariadb')) {
            $insertPrefix = 'INSERT IGNORE INTO wow_items (item_id, name, item_level, quality, type, requires, class, subclass, gem_slots, gear_score) VALUES ';
        }

        $batchSize = 500;
        $chunks = array_chunk($items, $batchSize);
        $insertedCount = 0;

        $io->progressStart(count($chunks));

        $this->connection->beginTransaction();
        try {
            foreach ($chunks as $chunk) {
                $valuePlaceholders = [];
                $params = [];

                foreach ($chunk as $idx => $item) {
                    $pId = "id_" . $idx;
                    $pName = "name_" . $idx;
                    $pIlvl = "ilvl_" . $idx;
                    $pQual = "qual_" . $idx;
                    $pType = "type_" . $idx;
                    $pReq = "req_" . $idx;
                    $pClass = "class_" . $idx;
                    $pSubclass = "subclass_" . $idx;
                    $pGems = "gems_" . $idx;
                    $pGs = "gs_" . $idx;

                    $valuePlaceholders[] = sprintf(
                        '(:%s, :%s, :%s, :%s, :%s, :%s, :%s, :%s, :%s, :%s)',
                        $pId, $pName, $pIlvl, $pQual, $pType, $pReq, $pClass, $pSubclass, $pGems, $pGs
                    );

                    $params[$pId] = $item['item_id'];
                    $params[$pName] = $item['name'];
                    $params[$pIlvl] = $item['item_level'];
                    $params[$pQual] = $item['quality'];
                    $params[$pType] = $item['type'];
                    $params[$pReq] = $item['requires'];
                    $params[$pClass] = $item['class'];
                    $params[$pSubclass] = $item['subclass'];
                    $params[$pGems] = $item['gem_slots'];
                    $params[$pGs] = $item['gear_score'];
                }

                $sql = $insertPrefix . implode(', ', $valuePlaceholders) . $onConflictSuffix;
                $insertedCount += $this->connection->executeStatement($sql, $params);
                $io->progressAdvance();
            }

            if ($runUpdateType20) {
                $this->connection->executeStatement('UPDATE wow_items SET type = 5 WHERE type = 20');
            }

            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            $io->error('Failed during bulk insert: ' . $e->getMessage());
            return Command::FAILURE;
        }

        $io->progressFinish();
        $io->success(sprintf('Import completed successfully! Inserted/Processed %d items.', $totalFound));

        return Command::SUCCESS;
    }
}
