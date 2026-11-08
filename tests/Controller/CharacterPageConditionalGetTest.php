<?php

namespace App\Tests\Controller;

use App\Entity\CharacterSnapshot;
use App\Repository\CharacterSnapshotRepository;
use App\Repository\UwuLogRankRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class CharacterPageConditionalGetTest extends WebTestCase
{
    public function testSnapshotBackedPageReturns304AndChangesValidatorWhenRenderedDataChanges(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $snapshot = (new CharacterSnapshot())
            ->setName('Understyx')
            ->setRealm('Icecrown')
            ->setLevel(80)
            ->setRace('Human')
            ->setClass('Death Knight')
            ->setGearScore(6000)
            ->setAvgIlvl(264.5)
            ->setProfessions([])
            ->setSpecializations([])
            ->setEquippedItems([])
            ->setCharacterModel(null)
            ->setTalentStrings([])
            ->setTalentTreesData([])
            ->setGlyphs([])
            ->setEnchantsStatus('All items enchanted')
            ->setGemsStatus('All gem slots filled')
            ->setKillStats([])
            ->setPvpStats(['totalKills' => 0, 'killsToday' => 0, 'arenaTeams' => []])
            ->setMatchHistory([])
            ->setScrapedAt(new \DateTimeImmutable('2026-11-01T12:00:00+00:00'));

        $snapshotRepository = $this->createMock(CharacterSnapshotRepository::class);
        $snapshotRepository->method('findViewByNameAndRealm')->willReturn($snapshot);
        $rankRepository = $this->createMock(UwuLogRankRepository::class);
        $rankRepository->method('findBest')->willReturn(null);
        static::getContainer()->set(CharacterSnapshotRepository::class, $snapshotRepository);
        static::getContainer()->set(UwuLogRankRepository::class, $rankRepository);
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects(self::never())->method('request');
        static::getContainer()->set('http_client', $httpClient);

        $client->request('GET', '/characters/Understyx/Icecrown');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.discord-login-button', 'Log in with Discord');
        self::assertStringContainsString('public', (string) $client->getResponse()->headers->get('Cache-Control'));
        $etag = $client->getResponse()->headers->get('ETag');
        self::assertNotEmpty($etag);

        $client->request('GET', '/characters/Understyx/Icecrown', server: ['HTTP_IF_NONE_MATCH' => $etag]);
        self::assertSame(Response::HTTP_NOT_MODIFIED, $client->getResponse()->getStatusCode());
        self::assertSame('', $client->getResponse()->getContent());

        $snapshot->setGearScore(6100);
        $client->request('GET', '/characters/Understyx/Icecrown', server: ['HTTP_IF_NONE_MATCH' => $etag]);
        self::assertResponseIsSuccessful();
        self::assertNotSame($etag, $client->getResponse()->headers->get('ETag'));
        self::assertStringContainsString('6100', $client->getResponse()->getContent());
    }
}
