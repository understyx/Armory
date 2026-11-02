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
use Symfony\Component\Messenger\Stamp\DeduplicateStamp;
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
        $ttls = [];
        $bus = $this->createMock(MessageBusInterface::class);
        $specs = [];
        $bus->expects(self::exactly(6))->method('dispatch')->willReturnCallback(
            static function (object $message, array $stamps) use (&$delays, &$ttls, &$specs): Envelope {
                self::assertInstanceOf(RefreshUwuRankMessage::class, $message);
                self::assertInstanceOf(DelayStamp::class, $stamps[0]);
                self::assertInstanceOf(DeduplicateStamp::class, $stamps[1]);
                $delays[] = $stamps[0]->getDelay();
                $ttls[] = $stamps[1]->getTtl();
                $specs[] = $message->spec;
                return new Envelope($message, $stamps);
            },
        );

        $result = (new GuildSnapshotUpdater($scraper, $repository, $bus))->refresh('Cadence', 'Icecrown');

        self::assertSame(GuildRefreshResult::UPDATED, $result->status);
        self::assertSame([5000, 10000, 15000, 20000, 25000, 30000], $delays);
        self::assertSame([3605.0, 3610.0, 3615.0, 3620.0, 3625.0, 3630.0], $ttls);
        self::assertSame(['1', '2', '3', '1', '2', '3'], $specs);
    }
}
