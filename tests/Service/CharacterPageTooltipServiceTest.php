<?php

namespace App\Tests\Service;

use App\Repository\WowItemRepository;
use App\Repository\WowSpellTooltipRepository;
use App\Service\CharacterPageTooltipService;
use App\Service\ItemDatabaseService;
use App\Service\ItemTooltipService;
use PHPUnit\Framework\TestCase;

final class CharacterPageTooltipServiceTest extends TestCase
{
    public function testBuildsGlyphAndTalentKeysAndUsesRankSpecificTooltipData(): void
    {
        $itemRepository = $this->createMock(WowItemRepository::class);
        $itemRepository->expects(self::once())
            ->method('findGlyphItemIdsByNames')
            ->with(['Glyph of Raise Dead', 'Missing Glyph'])
            ->willReturn(['Glyph of Raise Dead' => 43673]);
        $itemDatabase = $this->createMock(ItemDatabaseService::class);
        $itemDatabase->expects(self::once())
            ->method('getItemsBulk')
            ->with([43673])
            ->willReturn([
                43673 => [
                    'name' => 'Glyph of Raise Dead',
                    'quality' => 2,
                    'class' => 16,
                    'icon' => 'inv_inscription_majorglyph01',
                    'tooltip' => ['stats' => [], 'spells' => []],
                ],
            ]);
        $spellRepository = $this->createMock(WowSpellTooltipRepository::class);
        $spellRepository->expects(self::once())
            ->method('findForSpellIds')
            ->with([2222, 7777])
            ->willReturn([
                2222 => ['name' => 'Twin Disciplines', 'description' => 'Increases damage by 5%.'],
            ]);

        $talent = ['spellId' => 2222, 'pointsText' => '2/3', 'iconUrl' => 'https://icons.example/talent.jpg'];
        $unknown = ['spellId' => 7777, 'pointsText' => '0/1', 'iconUrl' => null];
        $specs = [
            ['trees' => [['tiers' => [[$talent, $unknown]]]]],
            ['trees' => [['tiers' => [[$talent]]]]],
        ];
        $tooltips = [];

        $glyphKeys = (new CharacterPageTooltipService(
            $itemRepository,
            $itemDatabase,
            new ItemTooltipService(),
            $spellRepository,
        ))->addCustomTooltips(
            $tooltips,
            $specs,
            ['0' => ['Major Glyphs' => ['Glyph of Raise Dead', 'Missing Glyph']]],
        );

        self::assertSame(['Glyph of Raise Dead' => 'item-43673'], $glyphKeys);
        self::assertArrayHasKey('item-43673', $tooltips);
        self::assertArrayHasKey('spell-2222-2-3', $tooltips);
        self::assertArrayHasKey('spell-7777-0-1', $tooltips);
        self::assertSame('Twin Disciplines', $tooltips['spell-2222-2-3']['name']);
        self::assertSame('Rank 2/3', $tooltips['spell-2222-2-3']['spell_rank']);
        self::assertSame('Increases damage by 5%.', $tooltips['spell-2222-2-3']['spell_description']);
        self::assertSame('Talent', $tooltips['spell-7777-0-1']['name']);
        self::assertSame(1, count(array_filter(array_keys($tooltips), static fn (string $key): bool => $key === 'spell-2222-2-3')));
        self::assertSame('Twin Disciplines', $specs[1]['trees'][0]['tiers'][0][0]['name']);
    }
}
