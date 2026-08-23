<?php

namespace App\Tests\Service;

use App\Entity\UwuLogRank;
use App\Exception\UwuLogsException;
use App\Repository\UwuLogRankRepository;
use App\Service\UwuLogsService;
use App\Service\UwuLogUpdateThrottle;
use App\Service\UwuLogUpdateThrottleDecision;
use App\Service\UwuRankUpdater;
use PHPUnit\Framework\TestCase;

final class UwuRankUpdaterTest extends TestCase
{
    public function testItReturnsARecentCachedResultWithoutAnUpstreamRequest(): void
    {
        $cached = (new UwuLogRank())
            ->setName('Imtilted')->setRealm('Icecrown')->setSpec('1')->setOverallRank(42)
            ->setPayload(['overallRank' => 42, 'bosses' => []])
            ->setScrapedAt(new \DateTimeImmutable('-5 seconds'));
        $repository = $this->createMock(UwuLogRankRepository::class);
        $repository->method('findCached')->willReturn($cached);
        $service = $this->createMock(UwuLogsService::class);
        $service->expects(self::never())->method('fetchRankings');
        $throttle = $this->createMock(UwuLogUpdateThrottle::class);
        $throttle->expects(self::never())->method('claim');

        $result = (new UwuRankUpdater($service, $repository, $throttle))->getOrRefresh('Imtilted', 'Icecrown', '1');

        self::assertTrue($result['cached']);
        self::assertSame(42, $result['overallRank']);
    }

    public function testItStoresAFreshRankingAfterClaimingTheCharacterThrottle(): void
    {
        $payload = ['overallRank' => 42, 'bosses' => []];
        $stored = (new UwuLogRank())
            ->setName('Imtilted')->setRealm('Icecrown')->setSpec('1')->setOverallRank(42)
            ->setPayload($payload)->setScrapedAt(new \DateTimeImmutable());
        $repository = $this->createMock(UwuLogRankRepository::class);
        $repository->method('findCached')->willReturn(null);
        $repository->expects(self::once())->method('upsertResult')->with('Imtilted', 'Icecrown', '1', $payload)->willReturn($stored);
        $service = $this->createMock(UwuLogsService::class);
        $service->expects(self::once())->method('fetchRankings')->willReturn($payload);
        $throttle = $this->createMock(UwuLogUpdateThrottle::class);
        $throttle->method('claim')->willReturn(new UwuLogUpdateThrottleDecision(true, new \DateTimeImmutable('+30 seconds')));

        $result = (new UwuRankUpdater($service, $repository, $throttle))->getOrRefresh('Imtilted', 'Icecrown', '1');

        self::assertFalse($result['cached']);
        self::assertSame(42, $result['overallRank']);
    }

    public function testItRejectsANewSpecDuringThePerCharacterCooldown(): void
    {
        $repository = $this->createMock(UwuLogRankRepository::class);
        $repository->method('findCached')->willReturn(null);
        $service = $this->createMock(UwuLogsService::class);
        $service->expects(self::never())->method('fetchRankings');
        $throttle = $this->createMock(UwuLogUpdateThrottle::class);
        $throttle->method('claim')->willReturn(new UwuLogUpdateThrottleDecision(false, new \DateTimeImmutable('+20 seconds')));

        $this->expectException(UwuLogsException::class);
        $this->expectExceptionMessage('once per character every 30 seconds');

        (new UwuRankUpdater($service, $repository, $throttle))->getOrRefresh('Imtilted', 'Icecrown', '2');
    }

    public function testItReleasesTheClaimWhenUwuLogsIsTemporarilyUnavailable(): void
    {
        $repository = $this->createMock(UwuLogRankRepository::class);
        $repository->method('findCached')->willReturn(null);
        $service = $this->createMock(UwuLogsService::class);
        $service->method('fetchRankings')->willThrowException(new UwuLogsException('Unavailable', 502));
        $throttle = $this->createMock(UwuLogUpdateThrottle::class);
        $throttle->method('claim')->willReturn(new UwuLogUpdateThrottleDecision(
            true,
            new \DateTimeImmutable('+30 seconds'),
            'claim-token',
        ));
        $throttle->expects(self::once())->method('release')->with('Imtilted', 'Icecrown', 'claim-token');

        $this->expectException(UwuLogsException::class);

        (new UwuRankUpdater($service, $repository, $throttle))->getOrRefresh('Imtilted', 'Icecrown', '1');
    }
}
