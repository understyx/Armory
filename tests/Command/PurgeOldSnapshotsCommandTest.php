<?php

namespace App\Tests\Command;

use App\Command\PurgeOldSnapshotsCommand;
use App\Repository\CharacterSnapshotRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class PurgeOldSnapshotsCommandTest extends TestCase
{
    public function testExecutePurgesSnapshotsOlderThanSixtyDaysByDefault(): void
    {
        $repo = $this->createMock(CharacterSnapshotRepository::class);
        $repo->expects($this->once())
            ->method('purgeOlderThan')
            ->with($this->callback(fn (\DateTimeImmutable $cutoff): bool => $this->isDaysAgo($cutoff, 60)))
            ->willReturn(3);

        $tester = new CommandTester(new PurgeOldSnapshotsCommand($repo));

        $this->assertSame(Command::SUCCESS, $tester->execute([]));
        $this->assertStringContainsString('Purged 3 snapshot(s)', $tester->getDisplay());
    }

    public function testDaysOptionSetsTheCutoff(): void
    {
        $repo = $this->createMock(CharacterSnapshotRepository::class);
        $repo->expects($this->once())
            ->method('purgeOlderThan')
            ->with($this->callback(fn (\DateTimeImmutable $cutoff): bool => $this->isDaysAgo($cutoff, 30)))
            ->willReturn(0);

        $tester = new CommandTester(new PurgeOldSnapshotsCommand($repo));

        $this->assertSame(Command::SUCCESS, $tester->execute(['--days' => '30']));
    }

    public function testDryRunOnlyCounts(): void
    {
        $repo = $this->createMock(CharacterSnapshotRepository::class);
        $repo->expects($this->never())->method('purgeOlderThan');
        $repo->expects($this->once())->method('countOlderThan')->willReturn(42);

        $tester = new CommandTester(new PurgeOldSnapshotsCommand($repo));

        $this->assertSame(Command::SUCCESS, $tester->execute(['--dry-run' => true]));
        $this->assertStringContainsString('Would purge 42 snapshot(s)', $tester->getDisplay());
    }

    public function testRejectsInvalidDays(): void
    {
        $repo = $this->createMock(CharacterSnapshotRepository::class);
        $repo->expects($this->never())->method('purgeOlderThan');

        $tester = new CommandTester(new PurgeOldSnapshotsCommand($repo));

        $this->assertSame(Command::INVALID, $tester->execute(['--days' => '0']));
    }

    private function isDaysAgo(\DateTimeImmutable $cutoff, int $days): bool
    {
        $expected = (new \DateTimeImmutable())->modify(sprintf('-%d days', $days));

        return abs($expected->getTimestamp() - $cutoff->getTimestamp()) < 60;
    }
}
