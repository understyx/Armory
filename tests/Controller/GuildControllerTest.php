<?php

namespace App\Tests\Controller;

use App\Entity\GuildSnapshot;
use App\Entity\UwuLogRank;
use App\Repository\GuildSnapshotRepository;
use App\Repository\UwuLogRankRepository;
use App\Service\GuildSnapshotUpdater;
use App\Service\GuildUpdateThrottle;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class GuildControllerTest extends WebTestCase
{
    public function testItRendersACachedGuildRosterWithSavedRanks(): void
    {
        $client = static::createClient();
        $guild = (new GuildSnapshot())
            ->setName('Cadence')->setRealm('Icecrown')->setFaction('Horde')
            ->setMemberCount(1)->setPvePoints(480)
            ->setMembers([[
                'name' => 'Imtilted', 'race' => 'Blood Elf', 'class' => 'Paladin', 'level' => 80,
                'rank' => 'Officer', 'achievementPoints' => 1695, 'professions' => ['Engineering'],
            ]])
            ->setScrapedAt(new \DateTimeImmutable());
        $rank = (new UwuLogRank())
            ->setName('Imtilted')->setRealm('Icecrown')->setSpec('1')->setOverallRank(42)
            ->setPayload(['overallPoints' => 85.0])->setScrapedAt(new \DateTimeImmutable());

        $guildRepository = $this->createMock(GuildSnapshotRepository::class);
        $guildRepository->method('findByNameAndRealm')->willReturn($guild);
        $rankRepository = $this->createMock(UwuLogRankRepository::class);
        $rankRepository->method('findBestForCharacters')->with(['Imtilted'], 'Icecrown')->willReturn(['imtilted' => $rank]);
        static::getContainer()->set(GuildSnapshotRepository::class, $guildRepository);
        static::getContainer()->set(UwuLogRankRepository::class, $rankRepository);
        static::getContainer()->set(GuildSnapshotUpdater::class, $this->createMock(GuildSnapshotUpdater::class));
        static::getContainer()->set(GuildUpdateThrottle::class, $this->createMock(GuildUpdateThrottle::class));

        $client->request('GET', '/guilds/Cadence/Icecrown');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', '<Cadence>');
        self::assertSelectorTextContains('.guild-roster-table', 'Imtilted');
        self::assertSelectorTextContains('.guild-uwu-rank', '#42');
        self::assertSelectorTextContains('.guild-uwu-rank', 'Holy');
        self::assertSelectorExists('.guild-uwu-rank strong[style="color: #a335ee"]');
        self::assertSelectorExists('a[href="/characters/Imtilted/Icecrown"]');
        self::assertSelectorTextContains('body', 'limited to once per day');
    }
}
