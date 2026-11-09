<?php

namespace App\Tests\Service;

use App\Entity\UwuLogRank;
use App\Exception\UwuLogsException;
use App\Exception\UwuLogsThrottleException;
use App\Repository\UwuLogRankRepository;
use App\Service\UwuLogsService;
use App\Service\UwuLogUpdateThrottle;
use App\Service\UwuLogUpdateThrottleDecision;
use App\Service\UwuRankUpdater;
use App\Service\BestCharacterRankService;
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
        $throttle->method('claim')->willReturn(new UwuLogUpdateThrottleDecision(true, new \DateTimeImmutable('+4 seconds')));
        $bestCharacter = $this->createMock(BestCharacterRankService::class);
        $bestCharacter->expects(self::once())->method('refreshForCharacter')->with('Imtilted', 'Icecrown');

        $result = (new UwuRankUpdater($service, $repository, $throttle, $bestCharacter))->getOrRefresh('Imtilted', 'Icecrown', '1');

        self::assertFalse($result['cached']);
        self::assertSame(42, $result['overallRank']);
    }

    public function testForcedRefreshBypassesTheFourSecondCacheShortcut(): void
    {
        $payload = ['overallRank' => 43, 'bosses' => []];
        $stored = (new UwuLogRank())
            ->setName('Imtilted')->setRealm('Icecrown')->setSpec('1')->setOverallRank(43)
            ->setPayload($payload)->setScrapedAt(new \DateTimeImmutable());
        $repository = $this->createMock(UwuLogRankRepository::class);
        $repository->expects(self::never())->method('findCached');
        $repository->expects(self::once())->method('upsertResult')->with('Imtilted', 'Icecrown', '1', $payload)->willReturn($stored);
        $service = $this->createMock(UwuLogsService::class);
        $service->expects(self::once())->method('fetchRankings')->with('Imtilted', 'Icecrown', '1')->willReturn($payload);
        $throttle = $this->createMock(UwuLogUpdateThrottle::class);
        $throttle->expects(self::once())->method('claim')->willReturn(new UwuLogUpdateThrottleDecision(true, new \DateTimeImmutable('+4 seconds')));

        $result = (new UwuRankUpdater($service, $repository, $throttle))->getOrRefresh('Imtilted', 'Icecrown', '1', true);

        self::assertFalse($result['cached']);
        self::assertSame(43, $result['overallRank']);
    }

    public function testItRejectsANewSpecDuringThePerCharacterCooldown(): void
    {
        $repository = $this->createMock(UwuLogRankRepository::class);
        $repository->method('findCached')->willReturn(null);
        $service = $this->createMock(UwuLogsService::class);
        $service->expects(self::never())->method('fetchRankings');
        $throttle = $this->createMock(UwuLogUpdateThrottle::class);
        $throttle->method('claim')->willReturn(new UwuLogUpdateThrottleDecision(false, new \DateTimeImmutable('+2 seconds')));

        $this->expectException(UwuLogsThrottleException::class);
        $this->expectExceptionMessage('once per character every 4 seconds');

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
            new \DateTimeImmutable('+4 seconds'),
            'claim-token',
        ));
        $throttle->expects(self::once())->method('release')->with('Imtilted', 'Icecrown', 'claim-token');

        $this->expectException(UwuLogsException::class);

        (new UwuRankUpdater($service, $repository, $throttle))->getOrRefresh('Imtilted', 'Icecrown', '1');
    }
}
