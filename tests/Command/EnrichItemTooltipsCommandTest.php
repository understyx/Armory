<?php

namespace App\Tests\Command;

use App\Command\EnrichItemTooltipsCommand;
use App\Repository\TooltipEnrichmentRepository;
use App\Service\TooltipEnrichmentService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class EnrichItemTooltipsCommandTest extends TestCase
{
    public function testDoesNotBulkEnrichWithoutExplicitAllOption(): void
    {
        $repository = $this->createMock(TooltipEnrichmentRepository::class);
        $repository->expects(self::never())->method('findCandidateItems');
        $service = $this->createMock(TooltipEnrichmentService::class);
        $service->expects(self::never())->method('enrichItem');

        $tester = new CommandTester(new EnrichItemTooltipsCommand($repository, $service));
        $result = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $result);
        self::assertStringContainsString('No items were changed', $tester->getDisplay());
    }

    public function testGlyphsOptionLimitsBackfillToGlyphItemCandidates(): void
    {
        $repository = $this->createMock(TooltipEnrichmentRepository::class);
        $repository->expects(self::once())
            ->method('findCandidateItems')
            ->with(null, 0, true)
            ->willReturn([['item_id' => 43673, 'set_id' => 0]]);
        $service = $this->createMock(TooltipEnrichmentService::class);
        $service->expects(self::once())->method('enrichItem')->with(43673, 0, 'enUS', false)->willReturn(true);

        $tester = new CommandTester(new EnrichItemTooltipsCommand($repository, $service));
        $result = $tester->execute(['--glyphs' => true]);

        self::assertSame(Command::SUCCESS, $result);
        self::assertStringContainsString('Enriched or retained 1 of 1', $tester->getDisplay());
    }
}
