<?php

namespace App\Tests\Command;

use App\Command\ImportTrinityItemsCommand;
use App\Service\LegacyGearScoreLoader;
use App\Service\MySqlDumpTableReader;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;

class ImportTrinityItemsCommandTest extends TestCase
{
    public function testImportsCanonicalQualityAndOverlaysLegacyGearScore(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE wow_items (
                item_id INTEGER PRIMARY KEY,
                name VARCHAR(255), item_level INTEGER, quality INTEGER, type INTEGER, requires INTEGER,
                class INTEGER, subclass INTEGER, gem_slots INTEGER, gear_score INTEGER, icon VARCHAR(64),
                tooltip_data TEXT, source_build INTEGER, gear_score_source VARCHAR(32), gear_score_version VARCHAR(32)
            )
            SQL);

        $row = array_fill(0, 139, '0');
        $row[0] = '49623';
        $row[1] = '2';
        $row[2] = '1';
        $row[4] = "'Shadowmourne'";
        $row[6] = '5';
        $row[9] = '1';
        $row[11] = '504762';
        $row[12] = '17';
        $row[13] = '-1';
        $row[14] = '-1';
        $row[15] = '284';
        $row[16] = '80';
        $row[24] = '1';
        $row[25] = '1';
        $row[27] = '2';
        $row[28] = '4';
        $row[29] = '223';
        $row[30] = '7';
        $row[31] = '198';
        $row[50] = '954';
        $row[51] = '1592';
        $row[63] = '3700';
        $row[101] = '1';
        $row[102] = "''";
        $row[114] = '145';
        $row[119] = '2';
        $row[121] = '2';
        $row[123] = '2';
        $row[125] = '3312';
        $row[132] = "''";
        $row[138] = '12340';

        $dumpPath = tempnam(sys_get_temp_dir(), 'tdb-items-');
        $gearScorePath = tempnam(sys_get_temp_dir(), 'legacy-gs-');
        self::assertNotFalse($dumpPath);
        self::assertNotFalse($gearScorePath);
        file_put_contents($dumpPath, 'INSERT INTO `item_template` VALUES (' . implode(',', $row) . ');' . "\n");
        file_put_contents($gearScorePath, "(49623,'Shadowmourne',284,4,17,80,2,1,3,1433);\n");

        $kernel = $this->createStub(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn(sys_get_temp_dir());
        $command = new ImportTrinityItemsCommand(
            $connection,
            $kernel,
            new MySqlDumpTableReader(),
            new LegacyGearScoreLoader(),
        );

        try {
            $result = (new CommandTester($command))->execute([
                'file' => $dumpPath,
                '--gear-score-file' => $gearScorePath,
                '--batch-size' => 1,
            ]);
        } finally {
            unlink($dumpPath);
            unlink($gearScorePath);
        }

        $this->assertSame(Command::SUCCESS, $result);
        $item = $connection->fetchAssociative('SELECT * FROM wow_items WHERE item_id = 49623');
        $this->assertIsArray($item);
        $this->assertSame('Shadowmourne', $item['name']);
        $this->assertSame(5, (int) $item['quality']);
        $this->assertSame(1433, (int) $item['gear_score']);
        $this->assertSame('legacy-items.sql', $item['gear_score_source']);

        $tooltip = json_decode($item['tooltip_data'], true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(3, count($tooltip['sockets']));
        $this->assertSame(3312, $tooltip['socket_bonus_id']);
        $this->assertSame(145, $tooltip['max_durability']);
    }
}
