<?php

namespace App\Tests\Service;

use App\Repository\TooltipEnrichmentRepository;
use App\Service\ItemTooltipProvider\ItemTooltipProviderInterface;
use App\Service\TooltipEnrichmentService;
use PHPUnit\Framework\TestCase;

class TooltipEnrichmentServiceTest extends TestCase
{
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
}
