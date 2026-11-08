<?php

namespace App\Tests\Controller;

use App\Entity\CharacterClaimChallenge;
use App\Entity\User;
use App\Repository\CharacterClaimChallengeRepository;
use App\Repository\CharacterClaimRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\User\UserProviderInterface;

final class AccountControllerTest extends WebTestCase
{
    public function testAnonymousAccountRequestRedirectsToDiscordLogin(): void
    {
        $client = static::createClient();

        $client->request('GET', '/account');

        self::assertResponseRedirects('/connect/discord');
    }

    public function testLinkStatusReturnsTheCurrentChallengeStateForTheSignedInUser(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $user = (new User())->setDiscordId('123456')->setDiscordUsername('TestUser');
        $id = new \ReflectionProperty($user, 'id');
        $id->setValue($user, 123);
        $challenge = new CharacterClaimChallenge($user, 'Testchar', 'Icecrown', new \DateTimeImmutable());
        $challenge->setState(CharacterClaimChallenge::SCANNING_BASELINE);

        $userProvider = $this->createMock(UserProviderInterface::class);
        $userProvider->method('supportsClass')->willReturn(true);
        $userProvider->method('refreshUser')->willReturnCallback(static fn (User $user): User => $user);
        static::getContainer()->set(UserProviderInterface::class, $userProvider);

        $challengeRepository = $this->createMock(CharacterClaimChallengeRepository::class);
        $challengeRepository->expects(self::exactly(2))->method('findCurrentForUser')->willReturn($challenge);
        static::getContainer()->set(CharacterClaimChallengeRepository::class, $challengeRepository);
        $claimRepository = $this->createMock(CharacterClaimRepository::class);
        $claimRepository->expects(self::once())->method('findForUser')->willReturn([]);
        static::getContainer()->set(CharacterClaimRepository::class, $claimRepository);
        $client->loginUser($user, 'main');

        $client->request('GET', '/account');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.site-account-link', 'TestUser');
        self::assertStringContainsString('private', (string) $client->getResponse()->headers->get('Cache-Control'));
        self::assertStringContainsString('no-store', (string) $client->getResponse()->headers->get('Cache-Control'));

        $client->request('GET', '/account/link/status');

        self::assertResponseIsSuccessful();
        self::assertSame('scanning_baseline', json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['state']);
        self::assertStringContainsString('private', (string) $client->getResponse()->headers->get('Cache-Control'));
        self::assertStringContainsString('no-store', (string) $client->getResponse()->headers->get('Cache-Control'));
    }
}
