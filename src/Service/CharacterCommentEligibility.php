<?php

namespace App\Service;

use App\Entity\User;

final readonly class CharacterCommentEligibility
{
    public function isEligible(?User $user): bool
    {
        return $user?->getId() !== null && ($user->getBestCharacterPoints() ?? 0.0) >= 50.0;
    }
}
