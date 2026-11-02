<?php

namespace App\Tests\Controller;

use App\Entity\GuildSnapshot;
use App\Entity\UwuLogRank;
use App\Repository\GuildSnapshotRepository;
use App\Repository\UwuLogRankRepository;
use App\Service\GuildSnapshotUpdater;
use App\Service\GuildUpdateThrottleDecision;
use App\Service\GuildUpdateThrottle;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class GuildControllerTest extends WebTestCase
{
    public function testItRendersACachedGuildRosterWithSavedRanks(): void
    {
        $client = static::createClient();
        $guild = (new GuildSnapshot())
            ->setName('Cadence')->setRealm('Icecrown')->setFaction('Horde')
            ->setMemberCount(3)->setPvePoints(480)
            ->setMembers([
                [
                    'name' => 'Imtilted', 'race' => 'Blood Elf', 'class' => 'Paladin', 'level' => 80,
                    'rank' => 'Officer', 'achievementPoints' => 1695, 'professions' => ['Engineering'],
                ],
                [
                    'name' => 'Secondchar', 'race' => 'Human', 'class' => 'Warrior', 'level' => 80,
                    'rank' => 'Member', 'achievementPoints' => 1200, 'professions' => ['Blacksmithing'],
                ],
                [
                    'name' => 'Thirdchar', 'race' => 'Orc', 'class' => 'Shaman', 'level' => 80,
                    'rank' => 'Member', 'achievementPoints' => 900, 'professions' => ['Alchemy'],
                ],
            ])
            ->setScrapedAt(new \DateTimeImmutable());
        $rank = (new UwuLogRank())
            ->setName('Imtilted')->setRealm('Icecrown')->setSpec('1')->setOverallRank(42)
            ->setPayload(['overallPoints' => 85.0])->setScrapedAt(new \DateTimeImmutable());

        $guildRepository = $this->createMock(GuildSnapshotRepository::class);
        $guildRepository->method('findByNameAndRealm')->willReturn($guild);
        $rankRepository = $this->createMock(UwuLogRankRepository::class);
        $rankRepository->expects(self::once())
            ->method('findBestForCharacters')
            ->with(['Imtilted', 'Secondchar', 'Thirdchar'], 'Icecrown')
            ->willReturn(['imtilted' => $rank]);
        static::getContainer()->set(GuildSnapshotRepository::class, $guildRepository);
        static::getContainer()->set(UwuLogRankRepository::class, $rankRepository);
        static::getContainer()->set(GuildSnapshotUpdater::class, $this->createMock(GuildSnapshotUpdater::class));
        $throttle = $this->createMock(GuildUpdateThrottle::class);
        $throttle->method('inspect')->willReturn(new GuildUpdateThrottleDecision(true, new \DateTimeImmutable('tomorrow')));
        static::getContainer()->set(GuildUpdateThrottle::class, $throttle);

        $client->request('GET', '/guilds/Cadence/Icecrown');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', '<Cadence>');
        self::assertSelectorTextContains('.guild-roster-table', 'Imtilted');
        self::assertSelectorTextContains('.guild-uwu-rank', '#42');
        self::assertSelectorTextContains('.guild-uwu-rank', '85.00 points');
        self::assertSelectorTextContains('.guild-uwu-rank', 'Holy');
        self::assertSelectorTextContains('.guild-roster-table', 'Secondchar');
        self::assertSelectorTextContains('.guild-roster-table', 'Thirdchar');
        self::assertSelectorCount(3, '.guild-roster-table tbody tr[data-sortable-row]');
        self::assertSelectorNotExists('.guild-roster-table td[data-label]');
        self::assertSelectorNotExists('.guild-roster-table td[data-sort-value]');
        self::assertSelectorExists('.guild-uwu-rank strong[style="color: #a335ee"]');
        self::assertSelectorCount(7, '.guild-roster-table .guild-sort-button');
        self::assertSelectorCount(3, '.guild-roster-table .guild-sort-button[data-sort-type="number"]');
        self::assertSelectorExists('script[src*="guild-roster-sort"]');
        self::assertSelectorExists('a[href="/characters/Imtilted/Icecrown"]');
        self::assertSelectorTextContains('body', 'One roster refresh is available each day');
        self::assertSelectorExists('.site-header-search select[name="type"]');
        self::assertSelectorExists('.site-header-brand[href="/"]');
        self::assertSelectorNotExists('.btn-refresh:disabled');
    }

    public function testGuildRosterCanBeFilteredBySpecializationAndShowsUsedRefreshState(): void
    {
        $client = static::createClient();
        $guild = (new GuildSnapshot())
            ->setName('Cadence')->setRealm('Icecrown')->setFaction('Horde')
            ->setMemberCount(2)->setPvePoints(480)
            ->setMembers([
                ['name' => 'Imtilted', 'race' => 'Blood Elf', 'class' => 'Paladin', 'level' => 80, 'rank' => 'Officer', 'achievementPoints' => 1695, 'professions' => ['Engineering']],
                ['name' => 'Secondchar', 'race' => 'Human', 'class' => 'Warrior', 'level' => 80, 'rank' => 'Member', 'achievementPoints' => 1200, 'professions' => ['Blacksmithing']],
            ])
            ->setScrapedAt(new \DateTimeImmutable());
        $rank = (new UwuLogRank())
            ->setName('Imtilted')->setRealm('Icecrown')->setSpec('1')->setOverallRank(42)
            ->setPayload(['overallPoints' => 85.0])->setScrapedAt(new \DateTimeImmutable());

        $guildRepository = $this->createMock(GuildSnapshotRepository::class);
        $guildRepository->method('findByNameAndRealm')->willReturn($guild);
        $rankRepository = $this->createMock(UwuLogRankRepository::class);
        $rankRepository->expects(self::once())
            ->method('findBestForCharacters')
            ->with(['Imtilted', 'Secondchar'], 'Icecrown')
            ->willReturn(['imtilted' => $rank]);
        $throttle = $this->createMock(GuildUpdateThrottle::class);
        $throttle->method('inspect')->willReturn(new GuildUpdateThrottleDecision(false, new \DateTimeImmutable('tomorrow')));
        static::getContainer()->set(GuildSnapshotRepository::class, $guildRepository);
        static::getContainer()->set(UwuLogRankRepository::class, $rankRepository);
        static::getContainer()->set(GuildSnapshotUpdater::class, $this->createMock(GuildSnapshotUpdater::class));
        static::getContainer()->set(GuildUpdateThrottle::class, $throttle);

        $client->request('GET', '/guilds/Cadence/Icecrown?filter=holy');

        self::assertResponseIsSuccessful();
        self::assertSelectorCount(1, '.guild-roster-table tbody tr[data-sortable-row]');
        self::assertSelectorTextContains('.guild-match-count', 'Showing 1 of 2 members');
        self::assertSelectorTextContains('.guild-roster-table', 'Imtilted');
        self::assertSelectorNotExists('.guild-roster-table tbody tr[data-sortable-row] a[href="/characters/Secondchar/Icecrown"]');
        self::assertSelectorExists('.btn-refresh:disabled');
        self::assertSelectorTextContains('.guild-header-actions', 'Today\'s refresh is used');
    }
}
