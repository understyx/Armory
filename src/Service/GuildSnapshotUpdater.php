<?php

namespace App\Service;

use App\Entity\GuildSnapshot;
use App\Message\RefreshUwuRankMessage;
use App\Repository\GuildSnapshotRepository;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

class GuildSnapshotUpdater
{
    private const UWU_REQUEST_SPACING_MS = 15000;
    private const UWU_SPEC_SWEEP_SPACING_MS = 1860000;

    public function __construct(
        private readonly ArmoryScraperService $armoryScraperService,
        private readonly GuildSnapshotRepository $snapshotRepository,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    public function refresh(string $guildName, string $realmName): GuildRefreshResult
    {
        $existing = $this->snapshotRepository->findByNameAndRealm($guildName, $realmName);
        $html = $this->armoryScraperService->fetchGuildHtml($guildName, $realmName);
        if ($html === null) {
            return new GuildRefreshResult(GuildRefreshResult::SOURCE_UNAVAILABLE, $existing);
        }
        if (!$this->armoryScraperService->checkGuildExists($html)) {
            return new GuildRefreshResult(GuildRefreshResult::NOT_FOUND, $existing);
        }

        $data = $this->armoryScraperService->extractGuildSummary($html);
        $snapshot = (new GuildSnapshot())
            ->setName($data['name'] ?: $guildName)
            ->setRealm($realmName)
            ->setFaction($data['faction'])
            ->setMemberCount($data['memberCount'])
            ->setPvePoints($data['pvePoints'])
            ->setMembers($data['members'])
            ->setScrapedAt(new \DateTimeImmutable());

        $updated = $this->snapshotRepository->upsert($snapshot);

        foreach (['1', '2', '3'] as $specIndex => $spec) {
            foreach ($data['members'] as $memberIndex => $member) {
                $delay = ($memberIndex + 1) * self::UWU_REQUEST_SPACING_MS
                    + $specIndex * self::UWU_SPEC_SWEEP_SPACING_MS;
                $this->messageBus->dispatch(
                    new RefreshUwuRankMessage($member['name'], $realmName, $spec),
                    [new DelayStamp($delay)],
                );
            }
        }

        return new GuildRefreshResult(
            $updated ? GuildRefreshResult::UPDATED : GuildRefreshResult::UNCHANGED,
            $updated ? $snapshot : ($existing ?? $snapshot),
        );
    }
}
