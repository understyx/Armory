<?php

namespace App\Tests\Controller;

use App\Exception\UwuLogsException;
use App\Exception\UwuLogsThrottleException;
use App\Service\UwuRankUpdater;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class UwuLogsControllerTest extends WebTestCase
{
    public function testRankingsAreFetchedOnlyByPostEndpoint(): void
    {
        $client = static::createClient();
        $service = $this->createMock(UwuRankUpdater::class);
        $service->expects(self::once())
            ->method('getOrRefresh')
            ->with('Nomoredots', 'Icecrown', '2')
            ->willReturn([
                'classId' => 8,
                'name' => 'Nomoredots',
                'server' => 'Icecrown',
                'spec' => '2',
                'overallPoints' => 5851.01,
                'overallRank' => 1525,
                'bosses' => [],
            ]);
        static::getContainer()->set(UwuRankUpdater::class, $service);

        $client->jsonRequest('POST', '/characters/Nomoredots/Icecrown/uwu-logs', ['spec' => '2']);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/json');
        $payload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(1525, $payload['overallRank']);
    }

    public function testRankingsEndpointReturnsReadableUpstreamError(): void
    {
        $client = static::createClient();
        $service = $this->createMock(UwuRankUpdater::class);
        $service->method('getOrRefresh')
            ->willThrowException(new UwuLogsException('Uwu-logs is currently unavailable. Please try again later.'));
        static::getContainer()->set(UwuRankUpdater::class, $service);

        $client->jsonRequest('POST', '/characters/Nomoredots/Icecrown/uwu-logs', ['spec' => '1']);

        self::assertResponseStatusCodeSame(502);
        self::assertSame(
            ['error' => 'Uwu-logs is currently unavailable. Please try again later.'],
            json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR)
        );
    }

    public function testLocalThrottleAndUpstreamRateLimitBothRemain429Responses(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $exceptions = [
            new UwuLogsThrottleException('Local request cooldown.', 429),
            new UwuLogsException('Uwu-logs rate limit.', 429),
        ];
        $service = $this->createMock(UwuRankUpdater::class);
        $service->method('getOrRefresh')->willReturnCallback(static function () use (&$exceptions): never {
            throw array_shift($exceptions);
        });
        static::getContainer()->set(UwuRankUpdater::class, $service);

        foreach ($exceptions as $_exception) {
            $client->jsonRequest('POST', '/characters/Nomoredots/Icecrown/uwu-logs', ['spec' => '1']);

            self::assertResponseStatusCodeSame(429);
        }
    }
}
