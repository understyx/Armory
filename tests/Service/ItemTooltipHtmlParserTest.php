<?php

namespace App\Tests\Service;

use App\Service\ItemTooltipProvider\ItemTooltipHtmlParser;
use PHPUnit\Framework\TestCase;

class ItemTooltipHtmlParserTest extends TestCase
{
    public function testParsesLinkedSpecialEffectWithoutDuplicatingStaticStats(): void
    {
        $html = <<<'HTML'
            <span class="q2">Equip: Increases your armor penetration rating by 167.</span><br>
            <span class="q2">Equip: <a href="spell=71562" class="q2">Your attacks have a chance to awaken the powers of the races of Northrend for 30 sec.</a></span>
            HTML;

        $result = (new ItemTooltipHtmlParser())->parse($html);

        self::assertSame([[
            'spell_id' => 71562,
            'trigger_type' => 'equip',
            'description' => 'Your attacks have a chance to awaken the powers of the races of Northrend for 30 sec.',
        ]], $result['effects']);
    }

    public function testParsesItemSetMembersAndBonuses(): void
    {
        $html = <<<'HTML'
            <span class="q"><a href="itemset=-228">Sanctified Bloodmage's Regalia</a> (0/5)</span>
            <div class="q0 indent">
                <span><a href="/wotlk/item=51159/gloves">Sanctified Bloodmage Gloves</a></span><br>
                <span><a href="/wotlk/item=51158/hood">Sanctified Bloodmage Hood</a></span>
            </div>
            <span class="q0">
                <span>(2) Set : <a href="/wotlk/spell=70752/bonus">Gain 12% haste for 5 sec.</a></span><br>
                <span>(4) Set : <a href="/wotlk/spell=70748/bonus">Deal 18% additional damage for 30 sec.</a></span>
            </span>
            HTML;

        $set = (new ItemTooltipHtmlParser())->parse($html)['set'];

        self::assertNotNull($set);
        self::assertSame("Sanctified Bloodmage's Regalia", $set['name']);
        self::assertSame([
            ['item_id' => 51159, 'name' => 'Sanctified Bloodmage Gloves'],
            ['item_id' => 51158, 'name' => 'Sanctified Bloodmage Hood'],
        ], $set['members']);
        self::assertSame([
            ['required_count' => 2, 'spell_id' => 70752, 'description' => 'Gain 12% haste for 5 sec.'],
            ['required_count' => 4, 'spell_id' => 70748, 'description' => 'Deal 18% additional damage for 30 sec.'],
        ], $set['bonuses']);
    }
}
