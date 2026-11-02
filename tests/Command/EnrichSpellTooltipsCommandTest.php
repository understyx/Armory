<?php

namespace App\Tests\Command;

use App\Command\EnrichSpellTooltipsCommand;
use App\Repository\WowSpellTooltipRepository;
use App\Service\SpellTooltipEnrichmentService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class EnrichSpellTooltipsCommandTest extends TestCase
{
    public function testFetchesMissingSpellsDirectlyWithAGapAndReportsFailures(): void
    {
        $repository = $this->createStub(WowSpellTooltipRepository::class);
        $repository->method('findTalentSpellIdsFromSnapshots')->willReturn([1, 2, 3, 4]);
        $repository->method('findQueueableSpellIds')->willReturn([1, 2, 3]);

        $service = $this->createMock(SpellTooltipEnrichmentService::class);
        $service->expects(self::exactly(3))->method('enrich')->willReturnCallback(
            static fn (int $spellId): bool => match ($spellId) {
                1 => true,
                2 => false,
                default => throw new \RuntimeException('Cavern of Time returned HTTP 500.'),
            }
        );

        $pauses = [];
        $tester = new CommandTester(new EnrichSpellTooltipsCommand(
            $repository,
            $service,
            static function (int $microseconds) use (&$pauses): void {
                $pauses[] = $microseconds;
            },
        ));

        self::assertSame(Command::FAILURE, $tester->execute([]));
        self::assertSame([500000, 500000, 500000], $pauses);
        self::assertStringContainsString('Saved 1 talent spell tooltips, 1 not found, 1 failed.', $tester->getDisplay());
    }
}
