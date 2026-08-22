<?php

namespace App\Tests\Service;

use App\Service\MySqlDumpTableReader;
use PHPUnit\Framework\TestCase;

class MySqlDumpTableReaderTest extends TestCase
{
    public function testParseValuesHandlesQuotedCommasEscapesAndNulls(): void
    {
        $reader = new MySqlDumpTableReader();
        $rows = iterator_to_array($reader->parseValues(
            "(1,'Shadow\\'s, Cloak',NULL,5),(2,'Line\\nTwo',0,1);"
        ));

        $this->assertSame([
            ['1', "Shadow's, Cloak", null, '5'],
            ['2', "Line\nTwo", '0', '1'],
        ], $rows);
    }

    public function testReadRowsSelectsOnlyRequestedTable(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'item-dump-');
        self::assertNotFalse($path);
        file_put_contents($path, implode("\n", [
            'INSERT INTO `other_table` VALUES (9);',
            "INSERT INTO `item_template` VALUES (25,'Worn Shortsword',1);",
            "INSERT INTO `item_template` VALUES (49623,'Shadowmourne',5);",
        ]));

        try {
            $rows = iterator_to_array((new MySqlDumpTableReader())->readRows($path, 'item_template'));
        } finally {
            unlink($path);
        }

        $this->assertSame([
            ['25', 'Worn Shortsword', '1'],
            ['49623', 'Shadowmourne', '5'],
        ], $rows);
    }
}
