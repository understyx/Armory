<?php

namespace App\Service;

use App\Entity\CharacterSnapshot;
use App\Repository\CharacterSnapshotRepository;

class CharacterSnapshotUpdater
{
    public function __construct(
        private readonly ArmoryScraperService $armoryScraperService,
        private readonly CharacterSnapshotRepository $snapshotRepository,
    ) {
    }

    public function refresh(string $characterName, string $realmName): CharacterRefreshResult
    {
        $existingSnapshot = $this->snapshotRepository->findByNameAndRealm($characterName, $realmName);
        $profileHtml = $this->armoryScraperService->fetchArmoryHtml($characterName, $realmName, 'summary');

        if ($profileHtml === null) {
            return new CharacterRefreshResult(CharacterRefreshResult::SOURCE_UNAVAILABLE, $existingSnapshot);
        }

        if (!$this->armoryScraperService->checkCharacterExists($profileHtml)) {
            return new CharacterRefreshResult(CharacterRefreshResult::NOT_FOUND, $existingSnapshot);
        }

        $talentHtml = $this->armoryScraperService->fetchArmoryHtml($characterName, $realmName, 'talents');
        $matchHistoryHtml = $this->armoryScraperService->fetchArmoryHtml($characterName, $realmName, 'match-history');
        $characterDetails = $this->armoryScraperService->extractLevelRaceClass($profileHtml);
        $guild = $this->armoryScraperService->extractGuild($profileHtml);
        $professions = $this->armoryScraperService->extractProfessions($profileHtml);
        $specializations = $this->armoryScraperService->extractSpecializations($profileHtml);
        $glyphs = $this->armoryScraperService->extractGlyphs($talentHtml ?? '');
        $equippedItems = $this->armoryScraperService->extractEquippedItemsData($profileHtml);
        $characterModel = $this->armoryScraperService->extractCharacterModelData($profileHtml);

        $generalKills = [];
        $killCountHtmlJson = $this->armoryScraperService->fetchArmoryHtml($characterName, $realmName, 'kills');
        if ($killCountHtmlJson) {
            $generalKills = $this->armoryScraperService->extractKillCount($killCountHtmlJson, 'general_kills');
        }

        $matchHistory = [];
        if ($matchHistoryHtml) {
            $matchHistory = $this->armoryScraperService->extractMatchHistory($matchHistoryHtml);
        }

        $talentStrings = $this->armoryScraperService->extractTalentPointsString(
            $talentHtml ?? '',
            $characterDetails['class'] ?? null,
        );
        $talentTreesData = $this->armoryScraperService->extractTalentTrees($talentHtml ?? '');
        $gearScore = $this->armoryScraperService->calculateGearScore($equippedItems);
        $avgIlvl = $this->armoryScraperService->calculateAvgIlvl($equippedItems);

        $newSnapshot = (new CharacterSnapshot())
            ->setName($characterName)
            ->setRealm($realmName)
            ->setLevel((int) ($characterDetails['level'] ?? 80))
            ->setRace($characterDetails['race'] ?? 'Unknown')
            ->setClass($characterDetails['class'] ?? 'Unknown')
            ->setGuild($guild)
            ->setGearScore($gearScore)
            ->setAvgIlvl($avgIlvl)
            ->setProfessions($professions)
            ->setSpecializations($specializations)
            ->setEquippedItems($equippedItems)
            ->setCharacterModel($characterModel)
            ->setTalentStrings($talentStrings)
            ->setTalentTreesData($talentTreesData)
            ->setGlyphs($glyphs)
            ->setEnchantsStatus($this->armoryScraperService->checkEnchants(
                $equippedItems,
                $characterDetails['class'] ?? null,
                $professions,
            ))
            ->setGemsStatus($this->armoryScraperService->checkGems($equippedItems))
            ->setKillStats($generalKills)
            ->setPvpStats($this->armoryScraperService->extractPvpSummary($profileHtml))
            ->setMatchHistory($matchHistory)
            ->setScrapedAt(new \DateTimeImmutable());

        if (!$this->snapshotRepository->upsert($newSnapshot) && $existingSnapshot !== null) {
            return new CharacterRefreshResult(CharacterRefreshResult::UNCHANGED, $existingSnapshot);
        }

        return new CharacterRefreshResult(CharacterRefreshResult::UPDATED, $newSnapshot);
    }
}
