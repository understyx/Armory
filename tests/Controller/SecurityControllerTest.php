<?php

namespace App\Tests\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SecurityControllerTest extends WebTestCase
{
    public function testLoginKeepsLocalReturnPath(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login', ['return' => '/characters/Lifedenier/Icecrown']);

        self::assertResponseRedirects('/connect/discord');
        self::assertSame(
            '/characters/Lifedenier/Icecrown',
            $client->getRequest()->getSession()->get('_security.main.target_path'),
        );
    }

    #[DataProvider('offSiteReturnPaths')]
    public function testLoginIgnoresOffSiteReturnPaths(string $returnPath): void
    {
        $client = static::createClient();
        $client->request('GET', '/login', ['return' => $returnPath]);

        self::assertResponseRedirects('/connect/discord');
        self::assertNull($client->getRequest()->getSession()->get('_security.main.target_path'));
    }

    public static function offSiteReturnPaths(): iterable
    {
        yield 'protocol relative' => ['//evil.example'];
        yield 'backslash' => ['/\\evil.example'];
        yield 'tab' => ["/\t/evil.example"];
        yield 'absolute' => ['https://evil.example/'];
    }
}
