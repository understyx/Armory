<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class HomeControllerTest extends WebTestCase
{
    public function testCharactersSearchPageIsPublic(): void
    {
        $client = static::createClient();
        $client->request('GET', '/characters');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Armory');
        self::assertSelectorExists('form[action="/characters"]');
        self::assertSelectorNotExists('a[href="/login"]');
    }

    public function testSearchRedirectsToPublicCharacterPage(): void
    {
        $client = static::createClient();
        $client->request('GET', '/characters?character=Understyx&realm=Icecrown');

        self::assertResponseRedirects('/characters/Understyx/Icecrown');
    }
}
