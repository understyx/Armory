<?php

namespace App\Controller;

use App\Entity\CharacterSnapshot;
use App\Repository\CharacterSnapshotRepository;
use App\Service\ArmoryScraperService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class ArmoryController extends AbstractController
{
    public function __construct(
        private readonly ArmoryScraperService $armoryScraperService,
        private readonly CharacterSnapshotRepository $snapshotRepository
    ) {
    }

    #[Route('/api/armory/{characterName}/{realmName}', name: 'api_armory', methods: ['GET'])]
    public function scrapeArmory(
        string $characterName,
        string $realmName,
        Request $request
    ): JsonResponse {
        $forceRefresh = $request->query->getBoolean('refresh', false);
        $existingSnapshot = $this->snapshotRepository->findByNameAndRealm($characterName, $realmName);

        if (!$forceRefresh && $existingSnapshot !== null) {
            return $this->buildJsonResponse($existingSnapshot, false);
        }

        // Scrape fresh
        $profileHtml = $this->armoryScraperService->fetchArmoryHtml($characterName, $realmName, 'summary');
        $talentHtml = $this->armoryScraperService->fetchArmoryHtml($characterName, $realmName, 'talents');
        $matchHistoryHtml = $this->armoryScraperService->fetchArmoryHtml($characterName, $realmName, 'match-history');

        if ($profileHtml === null || !$this->armoryScraperService->checkCharacterExists($profileHtml)) {
            // Check fallback snapshot if forceRefresh was requested but scrape failed
            if ($existingSnapshot !== null) {
                return $this->buildJsonResponse($existingSnapshot, true, 'Warmane armory is currently unreachable. Returning cached snapshot.');
            }

            return new JsonResponse(
                [
                    'status' => 'error',
                    'message' => 'The character you are looking for does not exist or does not meet the minimum required level.',
                    'details' => [
                        'characterName' => $characterName,
                        'realmName' => $realmName,
                        'sourceUrl' => sprintf(
                            'https://armory.warmane.com/character/%s/%s/summary',
                            ucfirst($characterName),
                            ucfirst($realmName)
                        ),
                    ],
                ],
                JsonResponse::HTTP_NOT_FOUND
            );
        }

        $characterDetails = $this->armoryScraperService->extractLevelRaceClass($profileHtml);
        $guild = $this->armoryScraperService->extractGuild($profileHtml);
        $professions = $this->armoryScraperService->extractProfessions($profileHtml);
        $specializations = $this->armoryScraperService->extractSpecializations($profileHtml);
        $glyphs = $this->armoryScraperService->extractGlyphs($talentHtml ?? '');
        $equippedItems = $this->armoryScraperService->extractEquippedItemsData($profileHtml);
        $characterModel = $this->armoryScraperService->extractCharacterModelData($profileHtml);

        $killCountHtmlJson = $this->armoryScraperService->fetchArmoryHtml($characterName, $realmName, 'kills');
        $generalKills = [];
        if ($killCountHtmlJson) {
            $generalKills = $this->armoryScraperService->extractKillCount($killCountHtmlJson, 'general_kills');
        }

        $pvpStats = $this->armoryScraperService->extractPvpSummary($profileHtml);
        $matchHistory = [];
        if ($matchHistoryHtml) {
            $matchHistory = $this->armoryScraperService->extractMatchHistory($matchHistoryHtml);
        }

        $talentStrings = $this->armoryScraperService->extractTalentPointsString(
            $talentHtml ?? '',
            $characterDetails['class']
        );
        $talentTreesData = $this->armoryScraperService->extractTalentTrees($talentHtml ?? '');

        $gearScore = $this->armoryScraperService->calculateGearScore($equippedItems);
        $avgIlvl = $this->armoryScraperService->calculateAvgIlvl($equippedItems);
        $enchantsStatus = $this->armoryScraperService->checkEnchants($equippedItems, $characterDetails['class'], $professions);
        $gemsStatus = $this->armoryScraperService->checkGems($equippedItems);

        $newSnapshot = new CharacterSnapshot();
        $newSnapshot->setName($characterName);
        $newSnapshot->setRealm($realmName);
        $newSnapshot->setLevel((int)($characterDetails['level'] ?? 80));
        $newSnapshot->setRace($characterDetails['race'] ?? 'Unknown');
        $newSnapshot->setClass($characterDetails['class'] ?? 'Unknown');
        $newSnapshot->setGuild($guild);
        $newSnapshot->setGearScore($gearScore);
        $newSnapshot->setAvgIlvl($avgIlvl);
        $newSnapshot->setProfessions($professions);
        $newSnapshot->setSpecializations($specializations);
        $newSnapshot->setEquippedItems($equippedItems);
        $newSnapshot->setCharacterModel($characterModel);
        $newSnapshot->setTalentStrings($talentStrings);
        $newSnapshot->setTalentTreesData($talentTreesData);
        $newSnapshot->setGlyphs($glyphs);
        $newSnapshot->setEnchantsStatus($enchantsStatus);
        $newSnapshot->setGemsStatus($gemsStatus);
        $newSnapshot->setKillStats($generalKills);
        $newSnapshot->setPvpStats($pvpStats);
        $newSnapshot->setMatchHistory($matchHistory);
        $newSnapshot->setScrapedAt(new \DateTimeImmutable());

        $updated = $this->snapshotRepository->upsert($newSnapshot);

        if (!$updated && $existingSnapshot !== null) {
            return $this->buildJsonResponse(
                $existingSnapshot,
                false,
                'No new character data was available. The saved data was not changed.',
                'unchanged',
                false
            );
        }

        return $this->buildJsonResponse($newSnapshot, false, null, 'success', true);
    }

    private function buildJsonResponse(
        CharacterSnapshot $snapshot,
        bool $isStale = false,
        ?string $warning = null,
        string $status = 'success',
        ?bool $updated = null
    ): JsonResponse {
        $data = [
            'status' => $status,
            'characterName' => $snapshot->getName(),
            'realmName' => $snapshot->getRealm(),
            'level' => $snapshot->getLevel(),
            'gender' => $snapshot->getGender(),
            'race' => $snapshot->getRace(),
            'class' => $snapshot->getClass(),
            'guild' => $snapshot->getGuild(),
            'gearScore' => $snapshot->getGearScore(),
            'avgIlvl' => $snapshot->getAvgIlvl(),
            'professions' => $snapshot->getProfessions(),
            'specializations' => $snapshot->getSpecializations(),
            'glyphs' => $snapshot->getGlyphs(),
            'talentPointsStrings' => $snapshot->getTalentStrings(),
            'equippedItems' => $snapshot->getEquippedItems(),
            'characterModel' => $snapshot->getCharacterModel(),
            'enchantsStatus' => $snapshot->getEnchantsStatus(),
            'gemsStatus' => $snapshot->getGemsStatus(),
            'killStats' => $snapshot->getKillStats(),
            'pvpStats' => $snapshot->getPvpStats(),
            'matchHistory' => $snapshot->getMatchHistory(),
            'scrapedAt' => $snapshot->getScrapedAt()?->format(\DateTimeInterface::ATOM),
            'isStale' => $isStale,
        ];

        if ($warning !== null) {
            $data['warning'] = $warning;
        }

        if ($updated !== null) {
            $data['updated'] = $updated;
        }

        return new JsonResponse($data);
    }
}
