<?php

namespace App\Tests\Service;

use App\Service\GlyphDatabase;
use PHPUnit\Framework\TestCase;

class GlyphDatabaseTest extends TestCase
{
    public function testGetAllReturns350Glyphs(): void
    {
        $glyphs = GlyphDatabase::getAll();
        self::assertCount(350, $glyphs);

        foreach ($glyphs as $itemId => $glyph) {
            self::assertSame($itemId, $glyph['item_id']);
            self::assertGreaterThan(0, $glyph['spell_id']);
            self::assertGreaterThan(0, $glyph['item_spell_id']);
            self::assertGreaterThan(0, $glyph['glyph_id']);
            self::assertNotEmpty($glyph['name']);
            self::assertContains($glyph['type'], ['major', 'minor']);
            self::assertGreaterThan(0, $glyph['class_id']);
            self::assertNotEmpty($glyph['class_name']);
            self::assertNotEmpty($glyph['icon']);
        }
    }

    public function testFindByItemId(): void
    {
        $glyph = GlyphDatabase::findByItemId(40897);
        self::assertNotNull($glyph);
        self::assertSame('Glyph of Maul', $glyph['name']);
        self::assertSame(54811, $glyph['spell_id']);

        self::assertNull(GlyphDatabase::findByItemId(9999999));
    }

    public function testFindBySpellId(): void
    {
        $glyph = GlyphDatabase::findBySpellId(54811);
        self::assertNotNull($glyph);
        self::assertSame(40897, $glyph['item_id']);
        self::assertSame('Glyph of Maul', $glyph['name']);

        self::assertNull(GlyphDatabase::findBySpellId(9999999));
    }
}
