<?php

namespace App\Tests\Controller;

use App\Repository\CharacterSnapshotRepository;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function testGuildTypeSearchRedirectsToPublicGuildPage(): void
    {
        $client = static::createClient();
        $client->request('GET', '/characters?character=VIVAMAX&type=guild&realm=Icecrown');

        self::assertResponseRedirects('/guilds/VIVAMAX/Icecrown');
    }

    public function testSearchRejectsInvalidCharacterNames(): void
    {
        $client = static::createClient();
        $repository = $this->createStub(CharacterSnapshotRepository::class);
        $repository->method('findRecentlyViewed')->willReturn([]);
        static::getContainer()->set(CharacterSnapshotRepository::class, $repository);
        $client->request('GET', '/characters?character=Abcdefghijklm&realm=Icecrown');

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.home-search-error', '1–12 letters');
    }

    public function testGuildSearchAllowsNamesLongerThanCharacterNames(): void
    {
        $client = static::createClient();
        $client->request('GET', '/characters?character=Some+Long+Guild+Name&type=guild&realm=Lordaeron');

        self::assertResponseRedirects('/guilds/Some%20Long%20Guild%20Name/Lordaeron');
    }

    #[DataProvider('rejectedPaths')]
    public function testRoutesRejectInvalidCharacterNamesAndRealms(string $path): void
    {
        $client = static::createClient();
        $client->request('GET', $path);

        self::assertResponseStatusCodeSame(404);
    }

    /** @return iterable<string, array{string}> */
    public static function rejectedPaths(): iterable
    {
        yield 'name too long' => ['/characters/Abcdefghijklm/Icecrown'];
        yield 'name with digits' => ['/characters/Foo1/Icecrown'];
        yield 'name with accents' => ['/characters/F%C3%B6o/Icecrown'];
        yield 'unknown realm' => ['/characters/Foo/Frostmourne'];
        yield 'api unknown realm' => ['/api/character/Foo/Frostmourne'];
        yield 'guild unknown realm' => ['/guilds/Foo/Frostmourne'];
    }

    public function testLandingPageShowsTenRecentlyUpdatedCharacters(): void
    {
        $client = static::createClient();
        $characters = array_map(
            static fn(int $number): array => ['name' => 'Character'.chr(64 + $number), 'realm' => 'Icecrown'],
            range(1, 10),
        );

        $repository = $this->createMock(CharacterSnapshotRepository::class);
        $repository->expects(self::once())
            ->method('findRecentlyViewed')
            ->with(10)
            ->willReturn($characters);
        static::getContainer()->set(CharacterSnapshotRepository::class, $repository);

        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#recent-searches-title', 'Recently updated');
        self::assertSelectorCount(10, '.recent-searches-list li');
        self::assertSelectorExists('a[href="/characters/CharacterA/Icecrown"]');
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
