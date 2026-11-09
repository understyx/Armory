<?php

namespace App\Service;

use App\Entity\CharacterComment;
use App\Entity\User;

final class CharacterCommentAuthorization
{
    public function canDelete(User $user, CharacterComment $comment, bool $isAdmin): bool
    {
        return $isAdmin || ($user->getId() !== null && $comment->getUser()?->getId() === $user->getId());
    }
}
