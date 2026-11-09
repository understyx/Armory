<?php

namespace App\Tests\Service;

use App\Entity\CharacterClaim;
use App\Entity\UwuLogRank;
use App\Entity\User;
use App\Repository\CharacterClaimRepository;
use App\Repository\CharacterSnapshotRepository;
use App\Repository\UwuLogRankRepository;
use App\Service\CharacterClaimOverviewService;
use PHPUnit\Framework\TestCase;

final class CharacterClaimOverviewServiceTest extends TestCase
{
    public function testItBatchesClaimsSnapshotsAndRanksForCommentAuthors(): void
    {
        $firstUser = $this->user(1);
        $secondUser = $this->user(2);
        $firstClaim = (new CharacterClaim())->setUser($firstUser)->setName('Understyx')->setRealm('Icecrown');
        $secondClaim = (new CharacterClaim())->setUser($secondUser)->setName('Testchar')->setRealm('Lordaeron');
        $claimRepository = $this->createMock(CharacterClaimRepository::class);
        $claimRepository->expects(self::once())->method('findForUserIds')->with([1, 2])->willReturn([$firstClaim, $secondClaim]);
        $snapshotRepository = $this->createMock(CharacterSnapshotRepository::class);
        $snapshotRepository->expects(self::once())->method('findDetailsForCharacters')->with([
            ['name' => 'Understyx', 'realm' => 'Icecrown'],
            ['name' => 'Testchar', 'realm' => 'Lordaeron'],
        ])->willReturn([
            CharacterClaimOverviewService::characterKey('Understyx', 'Icecrown') => ['class' => 'Death Knight', 'level' => 80, 'gearScore' => 6000],
        ]);
        $firstRank = (new UwuLogRank())->setName('Understyx')->setRealm('Icecrown')->setSpec('3')->setPayload(['overallPoints' => 70]);
        $secondRank = (new UwuLogRank())->setName('Testchar')->setRealm('Lordaeron')->setSpec('1')->setPayload(['overallPoints' => 80]);
        $rankRepository = $this->createMock(UwuLogRankRepository::class);
        $rankRepository->expects(self::once())->method('findBestForCharactersAndRealms')->with([
            ['name' => 'Understyx', 'realm' => 'Icecrown'],
            ['name' => 'Testchar', 'realm' => 'Lordaeron'],
        ])->willReturn([
            CharacterClaimOverviewService::characterKey('Understyx', 'Icecrown') => $firstRank,
            CharacterClaimOverviewService::characterKey('Testchar', 'Lordaeron') => $secondRank,
        ]);

        $service = new CharacterClaimOverviewService($claimRepository, $snapshotRepository, $rankRepository);
        $overview = $service->forUsers([$firstUser, $secondUser]);

        self::assertSame('Understyx', $service->bestRankedCharacter($overview[1])['claim']->getName());
        self::assertSame('Testchar', $service->bestRankedCharacter($overview[2])['claim']->getName());
        self::assertTrue($service->canComment($overview[1]));
    }

    private function user(int $id): User
    {
        $user = (new User())->setDiscordId('discord-'.$id);
        (new \ReflectionProperty($user, 'id'))->setValue($user, $id);

        return $user;
    }
}
