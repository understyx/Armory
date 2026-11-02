<?php

namespace App\Tests\Service;

use App\Repository\TooltipEnrichmentRepository;
use App\Service\ItemTooltipProvider\ItemTooltipProviderInterface;
use App\Service\TooltipEnrichmentService;
use PHPUnit\Framework\TestCase;

class TooltipEnrichmentServiceTest extends TestCase
{
    public function testRetainsGlyphUseEffectWhenTrinityContainsItsSpellId(): void
    {
        $provider = new class implements ItemTooltipProviderInterface {
            public function getName(): string { return 'cavern_of_time'; }
            public function getPriority(): int { return 100; }
            public function fetch(int $itemId, string $locale = 'enUS'): array
            {
                return [
                    'provider' => $this->getName(),
                    'source_url' => 'https://wotlk.cavernoftime.com/item=43673',
                    'raw_response' => '<tooltip />',
                    'effects' => [[
                        'spell_id' => 56175,
                        'trigger_type' => 'use',
                        'description' => 'Your Power Word: Shield also heals the target for 20%.',
                    ]],
                    'set' => null,
                ];
            }
        };
        $repository = $this->createMock(TooltipEnrichmentRepository::class);
        $repository->method('hasSuccessfulEnrichment')->willReturn(false);
        $repository->method('findItemContext')->willReturn([
            'item_id' => 43673,
            'set_id' => 0,
            'tooltip' => ['spells' => [['id' => 56175, 'trigger' => 0]]],
        ]);
        $repository->expects(self::once())
            ->method('saveEnrichment')
            ->with(43673, self::callback(static fn (array $effects): bool => $effects === [[
                'spell_id' => 56175,
                'trigger_type' => 'use',
                'description' => 'Your Power Word: Shield also heals the target for 20%.',
                'source' => 'cavern_of_time',
                'source_url' => 'https://wotlk.cavernoftime.com/item=43673',
            ]]), null, 0, 'enUS');

        self::assertTrue((new TooltipEnrichmentService($repository, [$provider]))->enrichItem(43673));
    }

    public function testPrefersCavernAndUsesTrinityTriggerType(): void
    {
        $cavern = new class implements ItemTooltipProviderInterface {
            public int $calls = 0;
            public function getName(): string { return 'cavern_of_time'; }
            public function getPriority(): int { return 100; }
            public function fetch(int $itemId, string $locale = 'enUS'): array
            {
                $this->calls++;
                return [
                    'provider' => $this->getName(),
                    'source_url' => 'https://example.test/cavern',
                    'raw_response' => '<tooltip />',
                    'effects' => [[
                        'spell_id' => 71562,
                        'trigger_type' => 'use', // Trinity's trigger below must win.
                        'description' => 'Original 3.3.5 effect.',
                    ]],
                    'set' => null,
                ];
            }
        };
        $wowhead = new class implements ItemTooltipProviderInterface {
            public int $calls = 0;
            public function getName(): string { return 'wowhead'; }
            public function getPriority(): int { return 50; }
            public function fetch(int $itemId, string $locale = 'enUS'): array
            {
                $this->calls++;
                throw new \RuntimeException('Wowhead should not be reached.');
            }
        };

        $repository = $this->createMock(TooltipEnrichmentRepository::class);
        $repository->method('hasSuccessfulEnrichment')->willReturn(false);
        $repository->method('findItemContext')->willReturn([
            'item_id' => 50363,
            'set_id' => 0,
            'tooltip' => ['spells' => [['id' => 71562, 'trigger' => 1]]],
        ]);
        $repository->expects(self::once())
            ->method('saveEnrichment')
            ->with(
                50363,
                self::callback(static function (array $effects): bool {
                    return $effects[0]['trigger_type'] === 'equip'
                        && $effects[0]['source'] === 'cavern_of_time'
                        && $effects[0]['description'] === 'Original 3.3.5 effect.';
                }),
                null,
                0,
                'enUS'
            );

        $result = (new TooltipEnrichmentService($repository, [$wowhead, $cavern]))->enrichItem(50363);

        self::assertTrue($result);
        self::assertSame(1, $cavern->calls);
        self::assertSame(0, $wowhead->calls);
    }

    public function testAttributesSingleUnlinkedEvowowEffectToSingleItemSpell(): void
    {
        $evowow = new class implements ItemTooltipProviderInterface {
            public function getName(): string { return 'evowow'; }
            public function getPriority(): int { return 150; }
            public function fetch(int $itemId, string $locale = 'enUS'): array
            {
                return [
                    'provider' => 'evowow',
                    'source_url' => 'https://wotlk.evowow.com/?item=45756&power',
                    'raw_response' => '$WowheadPower.registerItem(...);',
                    'effects' => [[
                        'spell_id' => null,
                        'trigger_type' => 'use',
                        'description' => 'Reduces the cooldown by 2 sec.',
                    ]],
                    'set' => null,
                ];
            }
        };
        $repository = $this->createMock(TooltipEnrichmentRepository::class);
        $repository->method('hasSuccessfulEnrichment')->willReturn(false);
        $repository->method('findItemContext')->willReturn([
            'item_id' => 45756,
            'class' => 16,
            'set_id' => 0,
            'tooltip' => ['spells' => [['id' => 56175, 'trigger' => 0]]],
        ]);
        $repository->expects(self::once())->method('saveEnrichment')
            ->with(45756, self::callback(static fn (array $effects): bool => $effects === [[
                'spell_id' => 56175,
                'trigger_type' => 'use',
                'description' => 'Reduces the cooldown by 2 sec.',
                'source' => 'evowow',
                'source_url' => 'https://wotlk.evowow.com/?item=45756&power',
            ]]), null, 0, 'enUS');

        self::assertTrue((new TooltipEnrichmentService($repository, [$evowow]))->enrichItem(45756));
    }

    public function testDoesNotAttributeUnlinkedEvowowEffectWhenItemHasMultipleSpells(): void
    {
        $evowow = new class implements ItemTooltipProviderInterface {
            public function getName(): string { return 'evowow'; }
            public function getPriority(): int { return 150; }
            public function fetch(int $itemId, string $locale = 'enUS'): array
            {
                return [
                    'provider' => 'evowow', 'source_url' => 'https://example.test', 'raw_response' => '',
                    'effects' => [['spell_id' => null, 'trigger_type' => 'use', 'description' => 'Unknown spell.']],
                    'set' => null,
                ];
            }
        };
        $repository = $this->createMock(TooltipEnrichmentRepository::class);
        $repository->method('hasSuccessfulEnrichment')->willReturn(false);
        $repository->method('findItemContext')->willReturn([
            'item_id' => 42410,
            'class' => 16,
            'set_id' => 0,
            'tooltip' => ['spells' => [['id' => 1, 'trigger' => 0], ['id' => 2, 'trigger' => 0]]],
        ]);
        $repository->expects(self::never())->method('saveEnrichment');

        self::assertFalse((new TooltipEnrichmentService($repository, [$evowow]))->enrichItem(42410));
    }

    public function testSkipsEvowowForNonGlyphItems(): void
    {
        $evowow = $this->createMock(ItemTooltipProviderInterface::class);
        $evowow->method('getName')->willReturn('evowow');
        $evowow->method('getPriority')->willReturn(150);
        $evowow->expects(self::never())->method('fetch');
        $repository = $this->createMock(TooltipEnrichmentRepository::class);
        $repository->method('hasSuccessfulEnrichment')->willReturn(false);
        $repository->method('findItemContext')->willReturn([
            'item_id' => 50363,
            'class' => 2,
            'set_id' => 0,
            'tooltip' => ['spells' => [['id' => 71562, 'trigger' => 1]]],
        ]);

        self::assertFalse((new TooltipEnrichmentService($repository, [$evowow]))->enrichItem(50363));
    }

    public function testFallsBackToCavernWhenEvowowThrows(): void
    {
        $evowow = new class implements ItemTooltipProviderInterface {
            public function getName(): string { return 'evowow'; }
            public function getPriority(): int { return 150; }
            public function fetch(int $itemId, string $locale = 'enUS'): array { throw new \RuntimeException('Evowow unavailable.'); }
        };
        $cavern = new class implements ItemTooltipProviderInterface {
            public function getName(): string { return 'cavern_of_time'; }
            public function getPriority(): int { return 100; }
            public function fetch(int $itemId, string $locale = 'enUS'): array
            {
                return [
                    'provider' => 'cavern_of_time', 'source_url' => 'https://example.test/cot', 'raw_response' => '',
                    'effects' => [['spell_id' => 56175, 'trigger_type' => 'use', 'description' => 'Cavern fallback.']],
                    'set' => null,
                ];
            }
        };
        $repository = $this->createMock(TooltipEnrichmentRepository::class);
        $repository->method('hasSuccessfulEnrichment')->willReturn(false);
        $repository->method('findItemContext')->willReturn([
            'item_id' => 43673,
            'class' => 16,
            'set_id' => 0,
            'tooltip' => ['spells' => [['id' => 56175, 'trigger' => 0]]],
        ]);
        $repository->expects(self::once())->method('saveEnrichment')
            ->with(43673, self::callback(static fn (array $effects): bool => $effects[0]['source'] === 'cavern_of_time'), null, 0, 'enUS');

        self::assertTrue((new TooltipEnrichmentService($repository, [$cavern, $evowow]))->enrichItem(43673));
    }
}
