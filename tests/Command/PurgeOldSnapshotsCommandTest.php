<?php

namespace App\Tests\Command;

use App\Command\PurgeOldSnapshotsCommand;
use App\Repository\CharacterSnapshotRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class PurgeOldSnapshotsCommandTest extends TestCase
{
    public function testExecutePurgesOldSnapshots(): void
    {
        $repo = $this->createMock(CharacterSnapshotRepository::class);
        $repo->expects($this->once())
            ->method('purgeOlderThan')
            ->willReturn(3);

        $command = new PurgeOldSnapshotsCommand($repo);
        $tester = new CommandTester($command);

        $statusCode = $tester->execute([]);

        $this->assertEquals(0, $statusCode);
        $this->assertStringContainsString('Purged 3 snapshot(s)', $tester->getDisplay());
    }
}
