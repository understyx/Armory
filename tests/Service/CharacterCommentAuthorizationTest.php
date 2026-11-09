<?php

namespace App\Tests\Service;

use App\Entity\CharacterComment;
use App\Entity\User;
use App\Service\CharacterCommentAuthorization;
use PHPUnit\Framework\TestCase;

final class CharacterCommentAuthorizationTest extends TestCase
{
    public function testAuthorCanDeleteTheirOwnComment(): void
    {
        $author = $this->user(1);
        self::assertTrue((new CharacterCommentAuthorization())->canDelete($author, new CharacterComment($author, 'Testchar', 'Icecrown', 'Body'), false));
    }

    public function testOtherUsersCannotDeleteAndAdminsCanDeleteAnyComment(): void
    {
        $author = $this->user(1);
        $other = $this->user(2);
        $comment = new CharacterComment($author, 'Testchar', 'Icecrown', 'Body');
        $authorization = new CharacterCommentAuthorization();

        self::assertFalse($authorization->canDelete($other, $comment, false));
        self::assertTrue($authorization->canDelete($other, $comment, true));
    }

    private function user(int $id): User
    {
        $user = (new User())->setDiscordId('discord-'.$id);
        (new \ReflectionProperty($user, 'id'))->setValue($user, $id);

        return $user;
    }
}
