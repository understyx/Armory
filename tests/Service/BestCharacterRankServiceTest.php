<?php

namespace App\Tests\Service;

use App\Entity\CharacterClaim;
use App\Entity\UwuLogRank;
use App\Entity\User;
use App\Repository\CharacterClaimRepository;
use App\Repository\UwuLogRankRepository;
use App\Service\BestCharacterRankService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class BestCharacterRankServiceTest extends TestCase
{
    public function testRecomputeStoresTheBestRankAcrossClaims(): void
    {
        $user = $this->user(1);
        $claims = [
            (new CharacterClaim())->setUser($user)->setName('First')->setRealm('Icecrown'),
            (new CharacterClaim())->setUser($user)->setName('Second')->setRealm('Lordaeron'),
        ];
        $claimRepository = $this->createMock(CharacterClaimRepository::class);
        $claimRepository->expects(self::once())->method('findForUser')->with($user)->willReturn($claims);
        $rankRepository = $this->createMock(UwuLogRankRepository::class);
        $rankRepository->expects(self::exactly(2))->method('findBest')->willReturnMap([
            ['First', 'Icecrown', $this->rank('1', 65.0, 15, 1)],
            ['Second', 'Lordaeron', $this->rank('3', 82.5, 7, 6)],
        ]);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        (new BestCharacterRankService($claimRepository, $rankRepository, $entityManager))->recompute($user);

        self::assertSame('Second', $user->getBestCharacterName());
        self::assertSame('Lordaeron', $user->getBestCharacterRealm());
        self::assertSame('Unholy', $user->getBestCharacterSpec());
        self::assertSame(82.5, $user->getBestCharacterPoints());
        self::assertSame(7, $user->getBestCharacterRank());
    }

    public function testRankSaveRefreshDoesOneIndexedOwnerLookupAndRecomputesThatUser(): void
    {
        $user = $this->user(9);
        $claim = (new CharacterClaim())->setUser($user)->setName('Claimed')->setRealm('Icecrown');
        $claimRepository = $this->createMock(CharacterClaimRepository::class);
        $claimRepository->expects(self::once())->method('findOwnerIdByCharacter')->with('Claimed', 'Icecrown')->willReturn(9);
        $claimRepository->expects(self::once())->method('findForUser')->with($user)->willReturn([$claim]);
        $rankRepository = $this->createMock(UwuLogRankRepository::class);
        $rankRepository->expects(self::once())->method('findBest')->with('Claimed', 'Icecrown')->willReturn($this->rank('2', 70.0, 3, 5));
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('find')->with(User::class, 9)->willReturn($user);
        $entityManager->expects(self::once())->method('flush');

        (new BestCharacterRankService($claimRepository, $rankRepository, $entityManager))->refreshForCharacter('Claimed', 'Icecrown');

        self::assertSame(70.0, $user->getBestCharacterPoints());
    }

    public function testTransferRecomputesBothPreviousAndNewOwners(): void
    {
        $oldOwner = $this->user(3);
        $oldOwner->setBestCharacter('Claimed', 'Icecrown', 'Unholy', 90, 2);
        $newOwner = $this->user(4);
        $oldClaim = (new CharacterClaim())->setUser($oldOwner)->setName('OldBest')->setRealm('Icecrown');
        $newClaim = (new CharacterClaim())->setUser($newOwner)->setName('NewBest')->setRealm('Lordaeron');
        $claimRepository = $this->createMock(CharacterClaimRepository::class);
        $claimRepository->expects(self::once())->method('findOwnerIdByCharacter')->with('Claimed', 'Icecrown')->willReturn(3);
        $claimRepository->expects(self::once())->method('transferOwnership')->with($newOwner, 'Claimed', 'Icecrown', self::isInstanceOf(\DateTimeImmutable::class));
        $claimRepository->expects(self::exactly(2))->method('findForUser')->willReturnCallback(static fn (User $user): array => $user->getId() === 3 ? [$oldClaim] : [$newClaim]);
        $rankRepository = $this->createMock(UwuLogRankRepository::class);
        $rankRepository->expects(self::exactly(2))->method('findBest')->willReturnCallback(static fn (string $name, string $realm): ?UwuLogRank => (new UwuLogRank())->setName($name)->setRealm($realm)->setSpec('1')->setOverallRank(1)->setPayload(['overallPoints' => $name === 'OldBest' ? 60 : 61, 'classId' => 1]));
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('find')->with(User::class, 3)->willReturn($oldOwner);
        $entityManager->expects(self::exactly(2))->method('flush');

        (new BestCharacterRankService($claimRepository, $rankRepository, $entityManager))->transferClaim($newOwner, 'Claimed', 'Icecrown', new \DateTimeImmutable());

        self::assertSame('OldBest', $oldOwner->getBestCharacterName());
        self::assertSame(60.0, $oldOwner->getBestCharacterPoints());
        self::assertSame('NewBest', $newOwner->getBestCharacterName());
    }

    public function testNewClaimRecomputesTheNewOwnerWhenThereWasNoPreviousOwner(): void
    {
        $newOwner = $this->user(11);
        $claim = (new CharacterClaim())->setUser($newOwner)->setName('NewlyClaimed')->setRealm('Icecrown');
        $claims = $this->createMock(CharacterClaimRepository::class);
        $claims->expects(self::once())->method('findOwnerIdByCharacter')->willReturn(null);
        $claims->expects(self::once())->method('transferOwnership')->with($newOwner, 'NewlyClaimed', 'Icecrown', self::isInstanceOf(\DateTimeImmutable::class));
        $claims->expects(self::once())->method('findForUser')->with($newOwner)->willReturn([$claim]);
        $ranks = $this->createMock(UwuLogRankRepository::class);
        $ranks->expects(self::once())->method('findBest')->willReturn($this->rank('1', 70, 4, 5));
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('find');
        $entityManager->expects(self::once())->method('flush');

        (new BestCharacterRankService($claims, $ranks, $entityManager))->transferClaim($newOwner, 'NewlyClaimed', 'Icecrown', new \DateTimeImmutable());

        self::assertSame('NewlyClaimed', $newOwner->getBestCharacterName());
        self::assertSame(70.0, $newOwner->getBestCharacterPoints());
    }

    public function testUnlinkRemovesTheClaimAndRecomputesItsOwner(): void
    {
        $user = $this->user(7);
        $user->setBestCharacter('Linked', 'Icecrown', 'Shadow', 58.0, 9);
        $claim = (new CharacterClaim())->setUser($user)->setName('Linked')->setRealm('Icecrown');
        $claims = $this->createMock(CharacterClaimRepository::class);
        $claims->expects(self::once())->method('findForUser')->with($user)->willReturn([]);
        $ranks = $this->createMock(UwuLogRankRepository::class);
        $ranks->expects(self::never())->method('findBest');
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('remove')->with($claim);
        $entityManager->expects(self::exactly(2))->method('flush');

        (new BestCharacterRankService($claims, $ranks, $entityManager))->unlinkClaim($claim);

        self::assertNull($user->getBestCharacterName());
        self::assertNull($user->getBestCharacterPoints());
    }

    private function user(int $id): User
    {
        $user = (new User())->setDiscordId((string) $id);
        (new \ReflectionProperty($user, 'id'))->setValue($user, $id);

        return $user;
    }

    private function rank(string $spec, float $points, int $overallRank, int $classId): UwuLogRank
    {
        return (new UwuLogRank())->setName('X')->setRealm('Icecrown')->setSpec($spec)->setOverallRank($overallRank)->setPayload([
            'overallPoints' => $points,
            'classId' => $classId,
        ]);
    }
}
