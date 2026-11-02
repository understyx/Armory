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

    #[\PHPUnit\Framework\Attributes\DataProvider('rawDurationProvider')]
    public function testConvertsRawMillisecondDurations(string $source, string $expected): void
    {
        $html = '<div id="t1-generic"><b>Talent</b><span class="q">'.$source.'</span></div>';

        self::assertSame($expected, (new SpellTooltipHtmlParser())->parse(1, $html)['description']);
    }

    /** @return iterable<string, array{string, string}> */
    public static function rawDurationProvider(): iterable
    {
        yield 'hundredths' => ['reduces the cast time by 250.2 sec.', 'reduces the cast time by 0.25 sec.'];
        yield 'tenths' => ['Reduces the global cooldown by 500.1 sec.', 'Reduces the global cooldown by 0.5 sec.'];
        yield 'whole seconds' => ['increased by 5000 sec. Following', 'increased by 5 sec. Following'];
        yield 'minutes' => ['is reduced by 120000 min.', 'is reduced by 2 min.'];
        yield 'real decimal stays' => ['cast time by 1.5 sec.', 'cast time by 1.5 sec.'];
        yield 'real duration stays' => ['taken for 15 sec.', 'taken for 15 sec.'];
        yield 'real minutes stay' => ['lasts 30 min.', 'lasts 30 min.'];
    }
}
