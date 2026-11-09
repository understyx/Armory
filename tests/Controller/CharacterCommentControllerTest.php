<?php

namespace App\Tests\Controller;

use App\Entity\CharacterComment;
use App\Entity\User;
use App\Repository\CharacterCommentRepository;
use App\Repository\CharacterClaimRepository;
use App\Repository\UwuLogRankRepository;
use App\Service\BestCharacterRankService;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Security\Core\User\UserProviderInterface;

final class CharacterCommentControllerTest extends WebTestCase
{
    public function testFragmentShowsDenormalizedAuthorBestCharacterAndOffersPagination(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $author = $this->user(22, 'author', 'CommentsAuthor')->setBestCharacter('Bestchar', 'Lordaeron', 'Unholy', 82.5, 31);
        $comment = new CharacterComment($author, 'Testchar', 'Icecrown', '<script>plain</script>');
        (new \ReflectionProperty($comment, 'id'))->setValue($comment, 8);
        $commentRepository = $this->createMock(CharacterCommentRepository::class);
        $commentRepository->expects(self::once())->method('findPage')->with('Testchar', 'Icecrown', 1)->willReturn([
            'comments' => [$comment],
            'hasMore' => true,
        ]);
        static::getContainer()->set(CharacterCommentRepository::class, $commentRepository);
        $claimRepository = $this->createMock(CharacterClaimRepository::class);
        $claimRepository->expects(self::never())->method('findForUser');
        $claimRepository->expects(self::never())->method('findForUserIds');
        $claimRepository->expects(self::never())->method('findOwnerIdByCharacter');
        static::getContainer()->set(CharacterClaimRepository::class, $claimRepository);
        $rankRepository = $this->createMock(UwuLogRankRepository::class);
        $rankRepository->expects(self::never())->method('findBest');
        $rankRepository->expects(self::never())->method('findBestForCharactersAndRealms');
        $rankRepository->expects(self::never())->method('findBestForCharacters');
        static::getContainer()->set(UwuLogRankRepository::class, $rankRepository);
        $client->request('GET', '/characters/Testchar/Icecrown/comments');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.character-comment-header a', 'CommentsAuthor');
        self::assertSelectorExists('.character-comment-header a[href="/users/CommentsAuthor"]');
        self::assertStringContainsString('Bestchar · Lordaeron', $client->getResponse()->getContent());
        self::assertStringContainsString('Unholy', $client->getResponse()->getContent());
        self::assertStringContainsString('82.50 points', $client->getResponse()->getContent());
        self::assertStringContainsString('&lt;script&gt;plain&lt;/script&gt;', $client->getResponse()->getContent());
        self::assertSelectorExists('[data-comments-more][data-url*="page=2"]');
        self::assertSelectorTextContains('.comment-eligibility-message a', 'Log in with Discord to comment');
        self::assertStringContainsString('public', (string) $client->getResponse()->headers->get('Cache-Control'));
    }

    public function testLoggedInCommentFragmentIsPrivateAndNoStore(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $user = $this->login($client, 23, 'commenter', 'Commenter');
        $comments = $this->createMock(CharacterCommentRepository::class);
        $comments->method('findPage')->willReturn(['comments' => [], 'hasMore' => false]);
        static::getContainer()->set(CharacterCommentRepository::class, $comments);
        $client->request('GET', '/characters/Testchar/Icecrown/comments');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('private', (string) $client->getResponse()->headers->get('Cache-Control'));
        self::assertStringContainsString('no-store', (string) $client->getResponse()->headers->get('Cache-Control'));
    }

    public function testPostRequiresCsrfToken(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $user = $this->login($client, 24, 'csrf-user', 'CsrfUser');
        $comments = $this->createMock(CharacterCommentRepository::class);
        $comments->expects(self::once())->method('findPage')->willReturn(['comments' => [], 'hasMore' => false]);
        static::getContainer()->set(CharacterCommentRepository::class, $comments);
        $client->request('POST', '/characters/Testchar/Icecrown/comments', ['body' => 'Hello'], server: [
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
            'HTTP_ACCEPT' => 'text/html',
        ]);

        self::assertResponseStatusCodeSame(403);
        self::assertStringContainsString('This form expired', $client->getResponse()->getContent());
    }

    public function testDeleteRequiresCsrfToken(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $user = $this->login($client, 26, 'delete-user', 'DeleteUser');
        $comments = $this->createMock(CharacterCommentRepository::class);
        $comments->expects(self::never())->method('findActiveById');
        $comments->expects(self::once())->method('findPage')->willReturn(['comments' => [], 'hasMore' => false]);
        static::getContainer()->set(CharacterCommentRepository::class, $comments);
        $client->request('POST', '/characters/Testchar/Icecrown/comments/42/delete', server: [
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
            'HTTP_ACCEPT' => 'text/html',
        ]);

        self::assertResponseStatusCodeSame(403);
        self::assertStringContainsString('This form expired', $client->getResponse()->getContent());
    }

    public function testPostTrimsAndValidatesTheMaximumLength(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $user = $this->login($client, 25, 'length-user', 'LengthUser', 55.0);
        $comments = $this->createMock(CharacterCommentRepository::class);
        $comments->expects(self::exactly(2))->method('findPage')->willReturn(['comments' => [], 'hasMore' => false]);
        static::getContainer()->set(CharacterCommentRepository::class, $comments);
        $best = $this->createMock(BestCharacterRankService::class);
        $best->expects(self::once())->method('recompute')->with($user)->willReturnCallback(static function (User $user): void {
            $user->setBestCharacter('Testchar', 'Icecrown', 'Shadow', 55, 10);
        });
        static::getContainer()->set(BestCharacterRankService::class, $best);

        $crawler = $client->request('GET', '/characters/Testchar/Icecrown/comments');
        $token = $crawler->filter('[data-comment-form] input[name="_token"]')->attr('value');
        $client->request('POST', '/characters/Testchar/Icecrown/comments', [
            '_token' => $token,
            'body' => str_repeat('x', 256),
        ], server: [
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
            'HTTP_ACCEPT' => 'text/html',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('between 1 and 255 characters', $client->getResponse()->getContent());
    }

    private function login(KernelBrowser $client, int $id, string $discordId, string $username, ?float $points = null): User
    {
        $user = $this->user($id, $discordId, $username);
        if ($points !== null) {
            $user->setBestCharacter('Testchar', 'Icecrown', 'Shadow', $points, 10);
        }
        $provider = $this->createMock(UserProviderInterface::class);
        $provider->method('supportsClass')->willReturn(true);
        $provider->method('refreshUser')->willReturnCallback(static fn (User $user): User => $user);
        static::getContainer()->set(UserProviderInterface::class, $provider);
        $client->loginUser($user, 'main');

        return $user;
    }

    private function user(int $id, string $discordId, string $username): User
    {
        $user = (new User())->setDiscordId($discordId)->setDiscordUsername($username);
        (new \ReflectionProperty($user, 'id'))->setValue($user, $id);

        return $user;
    }
}
