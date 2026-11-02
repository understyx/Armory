<?php

namespace App\Tests\Service;

use App\Service\ItemTooltipProvider\SpellTooltipHtmlParser;
use PHPUnit\Framework\TestCase;

final class SpellTooltipHtmlParserTest extends TestCase
{
    private const SAMPLE = <<<'HTML'
        <div id="t12345-generic"><table><tr><td><table><tr><td><table width="100%"><tr><td><b>Twin Disciplines</b><br /></td><th><b class="q0">Rank 5</b></th></tr></table>Instant</td></tr></table><table><tr><td><span class="q">Increases the damage and healing done by your instant spells by 5%.</span></td></tr></table></td></tr></table></div>
        HTML;

    public function testParsesNameRankAndDescriptionFromWrathTooltipMarkup(): void
    {
        $result = (new SpellTooltipHtmlParser())->parse(12345, self::SAMPLE);

        self::assertSame([
            'name' => 'Twin Disciplines',
            'spell_rank' => 5,
            'description' => 'Increases the damage and healing done by your instant spells by 5%.',
        ], $result);
    }

    public function testRankIsNullableWhenTheTooltipOmitsIt(): void
    {
        $html = str_replace(
            ['<div id="t12345-generic">', '</div>', '<th><b class="q0">Rank 5</b></th>'],
            '',
            self::SAMPLE,
        );

        self::assertSame([
            'name' => 'Twin Disciplines',
            'spell_rank' => null,
            'description' => 'Increases the damage and healing done by your instant spells by 5%.',
        ], (new SpellTooltipHtmlParser())->parse(12345, $html));
    }

    public function testReturnsNullForMissingTooltip(): void
    {
        self::assertNull((new SpellTooltipHtmlParser())->parse(12345, '<html>Not found</html>'));
    }
}
