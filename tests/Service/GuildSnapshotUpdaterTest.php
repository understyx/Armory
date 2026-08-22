<?php

namespace App\Tests\Service;

use App\Message\RefreshUwuRankMessage;
use App\Repository\GuildSnapshotRepository;
use App\Service\ArmoryScraperService;
use App\Service\GuildRefreshResult;
use App\Service\GuildSnapshotUpdater;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

final class GuildSnapshotUpdaterTest extends TestCase
{
    public function testItStoresRosterAndQueuesPacedRankUpdates(): void
    {
        $scraper = $this->createMock(ArmoryScraperService::class);
        $scraper->method('fetchGuildHtml')->with('Cadence', 'Icecrown')->willReturn('<html>guild</html>');
        $scraper->method('checkGuildExists')->willReturn(true);
        $scraper->method('extractGuildSummary')->willReturn([
            'name' => 'Cadence', 'faction' => 'Horde', 'memberCount' => 2, 'pvePoints' => 480,
            'members' => [
                ['name' => 'Imtilted'],
                ['name' => 'Imleaf'],
            ],
        ]);
        $repository = $this->createMock(GuildSnapshotRepository::class);
        $repository->method('findByNameAndRealm')->willReturn(null);
        $repository->expects(self::once())->method('upsert')->willReturn(true);
        $delays = [];
        $bus = $this->createMock(MessageBusInterface::class);
        $specs = [];
        $bus->expects(self::exactly(6))->method('dispatch')->willReturnCallback(
            static function (object $message, array $stamps) use (&$delays, &$specs): Envelope {
                self::assertInstanceOf(RefreshUwuRankMessage::class, $message);
                self::assertInstanceOf(DelayStamp::class, $stamps[0]);
                $delays[] = $stamps[0]->getDelay();
                $specs[] = $message->spec;
                return new Envelope($message, $stamps);
            },
        );

        $result = (new GuildSnapshotUpdater($scraper, $repository, $bus))->refresh('Cadence', 'Icecrown');

        self::assertSame(GuildRefreshResult::UPDATED, $result->status);
        self::assertSame([15000, 30000, 1875000, 1890000, 3735000, 3750000], $delays);
        self::assertSame(['1', '1', '2', '2', '3', '3'], $specs);
    }
}
