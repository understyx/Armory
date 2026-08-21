<?php

namespace App\Tests\Controller;

use App\Exception\UwuLogsException;
use App\Service\UwuLogsService;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class UwuLogsControllerTest extends WebTestCase
{
    public function testRankingsAreFetchedOnlyByPostEndpoint(): void
    {
        $client = static::createClient();
        $service = $this->createMock(UwuLogsService::class);
        $service->expects(self::once())
            ->method('fetchRankings')
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
        static::getContainer()->set(UwuLogsService::class, $service);

        $client->jsonRequest('POST', '/characters/Nomoredots/Icecrown/uwu-logs', ['spec' => '2']);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/json');
        $payload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(1525, $payload['overallRank']);
    }

    public function testRankingsEndpointReturnsReadableUpstreamError(): void
    {
        $client = static::createClient();
        $service = $this->createMock(UwuLogsService::class);
        $service->method('fetchRankings')
            ->willThrowException(new UwuLogsException('Uwu-logs is currently unavailable. Please try again later.'));
        static::getContainer()->set(UwuLogsService::class, $service);

        $client->jsonRequest('POST', '/characters/Nomoredots/Icecrown/uwu-logs', ['spec' => '1']);

        self::assertResponseStatusCodeSame(502);
        self::assertSame(
            ['error' => 'Uwu-logs is currently unavailable. Please try again later.'],
            json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR)
        );
    }
}
