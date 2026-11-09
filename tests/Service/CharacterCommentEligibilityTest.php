<?php

namespace App\Tests\Service;

use App\Entity\User;
use App\Service\CharacterCommentEligibility;
use PHPUnit\Framework\TestCase;

final class CharacterCommentEligibilityTest extends TestCase
{
    public function testAClaimBelowFiftyPointsIsNotEligible(): void
    {
        self::assertFalse($this->eligibility()->isEligible($this->user(49.99)));
    }

    public function testAClaimAtFiftyPointsIsEligible(): void
    {
        self::assertTrue($this->eligibility()->isEligible($this->user(50.0)));
    }

    public function testNoClaimsAreNotEligible(): void
    {
        self::assertFalse($this->eligibility()->isEligible($this->user(null)));
    }

    public function testAnonymousUsersAreNotEligibleEvenWithAClaimOverview(): void
    {
        self::assertFalse($this->eligibility()->isEligible(null));
    }

    private function eligibility(): CharacterCommentEligibility
    {
        return new CharacterCommentEligibility();
    }

    private function user(?float $points): User
    {
        $user = (new User())->setDiscordId('commenter');
        (new \ReflectionProperty($user, 'id'))->setValue($user, 1);
        $user->setBestCharacter($points === null ? null : 'X', $points === null ? null : 'Icecrown', null, $points, null);

        return $user;
    }
}
