<?php

namespace App\Tests\Service;

use App\Exception\UwuLogsException;
use App\Service\UwuLogsService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class UwuLogsServiceTest extends TestCase
{
    public function testFetchRankingsPostsCharacterAndNormalizesResponse(): void
    {
        $httpClient = new MockHttpClient(
            function (string $method, string $url, array $options): MockResponse {
                self::assertSame('POST', $method);
                self::assertSame('https://uwu-logs.xyz/character', $url);
                self::assertSame([
                    'name' => 'Nomoredots',
                    'server' => 'Icecrown',
                    'spec' => '1',
                ], json_decode($options['body'], true, 512, JSON_THROW_ON_ERROR));

                return new MockResponse(json_encode([
                    'class_i' => 8,
                    'name' => 'Nomoredots',
                    'server' => 'Icecrown',
                    'overall_points' => 5851.0097,
                    'overall_rank' => 1525,
                    'bosses' => [
                        'Lord Marrowgar' => [
                            'rank_raids' => 13296,
                            'rank_players' => 3376,
                            'dps_max' => 11944.14,
                            'points' => 6221.4,
                            'spec_total_players' => 4483,
                            'spec_total_raids' => 16162,
                            'spec_r1_dps' => 27115.33,
                            'report_id' => 'test-report',
                            'fastest_kill_duration' => 102.938,
                        ],
                        'Halion' => [],
                    ],
                ], JSON_THROW_ON_ERROR));
            }
        );

        $rankings = (new UwuLogsService($httpClient, new NullLogger()))
            ->fetchRankings('Nomoredots', 'Icecrown', '1');

        self::assertSame(8, $rankings['classId']);
        self::assertSame(1525, $rankings['overallRank']);
        self::assertSame(5851.0097, $rankings['overallPoints']);
        self::assertCount(1, $rankings['bosses']);
        self::assertSame('Lord Marrowgar', $rankings['bosses'][0]['name']);
        self::assertSame(3376, $rankings['bosses'][0]['playerRank']);
        self::assertSame(11944.14, $rankings['bosses'][0]['dps']);
        self::assertSame(102.938, $rankings['bosses'][0]['fastestKillSeconds']);
    }

    public function testFetchRankingsRejectsInvalidSpecializationWithoutRequest(): void
    {
        $httpClient = new MockHttpClient(static function (): never {
            self::fail('No HTTP request should be made for an invalid specialization.');
        });

        $service = new UwuLogsService($httpClient, new NullLogger());

        $this->expectException(UwuLogsException::class);
        $this->expectExceptionMessage('Please choose a valid specialization.');

        $service->fetchRankings('Nomoredots', 'Icecrown', '4');
    }

    public function testFetchRankingsHandlesUnexpectedResponse(): void
    {
        $httpClient = new MockHttpClient(new MockResponse('{"message":"maintenance"}'));
        $service = new UwuLogsService($httpClient, new NullLogger());

        $this->expectException(UwuLogsException::class);
        $this->expectExceptionMessage('unexpected response');

        $service->fetchRankings('Nomoredots', 'Icecrown');
    }
}
