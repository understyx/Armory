<?php

namespace App\Tests\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\CharacterClaimOverviewService;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\User\UserProviderInterface;

final class UserProfileControllerTest extends WebTestCase
{
    public function testProfileLookupReceivesCaseInsensitiveUsernameAndIsPublicForAnonymousVisitors(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $user = $this->user('profile-user', 'CaseUser');
        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->expects(self::once())->method('findLatestByDiscordUsername')->with('caseuser')->willReturn($user);
        static::getContainer()->set(UserRepository::class, $userRepository);
        $overview = $this->createMock(CharacterClaimOverviewService::class);
        $overview->expects(self::once())->method('forUsers')->with([$user])->willReturn([$user->getId() => []]);
        static::getContainer()->set(CharacterClaimOverviewService::class, $overview);

        $client->request('GET', '/users/caseuser');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#user-profile-title', 'CaseUser');
        self::assertStringContainsString('public', (string) $client->getResponse()->headers->get('Cache-Control'));
        self::assertNotEmpty($client->getResponse()->headers->get('ETag'));
    }

    public function testProfileIsPrivateForSignedInVisitors(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $user = $this->user('profile-user', 'CaseUser');
        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('findLatestByDiscordUsername')->willReturn($user);
        static::getContainer()->set(UserRepository::class, $userRepository);
        $overview = $this->createMock(CharacterClaimOverviewService::class);
        $overview->method('forUsers')->willReturn([$user->getId() => []]);
        static::getContainer()->set(CharacterClaimOverviewService::class, $overview);
        $provider = $this->createMock(UserProviderInterface::class);
        $provider->method('supportsClass')->willReturn(true);
        $provider->method('refreshUser')->willReturnCallback(static fn (User $user): User => $user);
        static::getContainer()->set(UserProviderInterface::class, $provider);
        $client->loginUser($this->user('viewer', 'Viewer'));

        $client->request('GET', '/users/caseuser');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('private', (string) $client->getResponse()->headers->get('Cache-Control'));
        self::assertStringContainsString('no-store', (string) $client->getResponse()->headers->get('Cache-Control'));
        self::assertNull($client->getResponse()->headers->get('ETag'));
    }

    public function testUnknownUsernameReturns404(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $repository = $this->createMock(UserRepository::class);
        $repository->expects(self::once())->method('findLatestByDiscordUsername')->with('missing')->willReturn(null);
        static::getContainer()->set(UserRepository::class, $repository);

        $client->request('GET', '/users/missing');

        self::assertResponseStatusCodeSame(404);
    }

    private function user(string $discordId, string $username): User
    {
        $user = (new User())->setDiscordId($discordId)->setDiscordUsername($username);
        (new \ReflectionProperty($user, 'id'))->setValue($user, $discordId === 'profile-user' ? 12 : 13);

        return $user;
    }
}
