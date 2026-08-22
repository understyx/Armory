<?php

namespace App\Tests\Controller;

use App\Entity\CharacterSnapshot;
use App\Repository\CharacterSnapshotRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class HomeControllerTest extends WebTestCase
{
    public function testCharactersSearchPageIsPublic(): void
    {
        $client = static::createClient();
        $repository = $this->createMock(CharacterSnapshotRepository::class);
        $repository->method('findRecentlyViewed')->willReturn([]);
        static::getContainer()->set(CharacterSnapshotRepository::class, $repository);

        $client->request('GET', '/characters');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Armory');
        self::assertSelectorExists('form[action="/characters"]');
        self::assertSelectorNotExists('form[action="/guilds"]');
        self::assertSelectorNotExists('a[href="/login"]');
        self::assertSelectorExists('option[value="Onyxia"]');
        self::assertSelectorNotExists('option[value="Frostmourne"]');
    }

    public function testSearchRedirectsToPublicCharacterPage(): void
    {
        $client = static::createClient();
        $client->request('GET', '/characters?character=Understyx&realm=Icecrown');

        self::assertResponseRedirects('/characters/Understyx/Icecrown');
    }

    public function testLandingPageShowsTenMostRecentlySearchedCharacters(): void
    {
        $client = static::createClient();
        $characters = array_map(
            static fn(int $number): CharacterSnapshot => (new CharacterSnapshot())
                ->setName('Character'.$number)
                ->setRealm('Icecrown'),
            range(1, 10)
        );

        $repository = $this->createMock(CharacterSnapshotRepository::class);
        $repository->expects(self::once())
            ->method('findRecentlyViewed')
            ->with(10)
            ->willReturn($characters);
        static::getContainer()->set(CharacterSnapshotRepository::class, $repository);

        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#recent-searches-title', 'Recently searched');
        self::assertSelectorCount(10, '.recent-searches-list li');
        self::assertSelectorExists('a[href="/characters/Character1/Icecrown"]');
        self::assertSelectorTextContains('a[href="/api"]', 'API documentation');
    }

    public function testApiDocumentationDescribesBothPublicEndpoints(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Warmane Armory API');
        self::assertSelectorTextContains('.api-method-get', 'GET');
        self::assertSelectorTextContains('.api-method-post', 'POST');
        self::assertSelectorTextContains('body', '/api/character/{name}/{realm}');
        self::assertSelectorTextContains('body', '/api/requestupdate/{name}/{realm}');
    }
}
