<?php

namespace App\Controller;

use App\Entity\CharacterSnapshot;
use App\Repository\CharacterSnapshotRepository;
use App\Repository\UwuLogRankRepository;
use App\Service\ArmoryScraperService;
use App\Service\CharacterUpdateThrottle;
use App\Service\PaperdollService;
use App\Service\TalentTreeService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CharacterViewController extends AbstractController
{
    public function __construct(
        private readonly ArmoryScraperService $armoryScraperService,
        private readonly CharacterSnapshotRepository $snapshotRepository,
        private readonly TalentTreeService $talentTreeService,
        private readonly PaperdollService $paperdollService,
        private readonly LoggerInterface $logger,
        private readonly CharacterUpdateThrottle $updateThrottle,
        private readonly ?UwuLogRankRepository $uwuRankRepository = null,
    ) {
    }

    #[Route('/characters/{characterName}/{realmName}', name: 'app_character_view', methods: ['GET'])]
    #[Route('/character/{characterName}/{realmName}', name: 'app_character_view_legacy', methods: ['GET'])]
    public function viewCharacter(string $characterName, string $realmName): Response
    {
        $snapshot = $this->snapshotRepository->findByNameAndRealm($characterName, $realmName);
        $warningMessage = null;

        if ($snapshot === null) {
            // Scrape fresh
            $scrapeResult = $this->scrapeAndSave($characterName, $realmName);
            if ($scrapeResult['status'] === 'error') {
                $this->logger->warning(sprintf("Character not found or failed to fetch for %s/%s", $characterName, $realmName));

                return $this->render('character_view/not_found.html.twig', [
                    'characterName' => $characterName,
                    'realmName' => $realmName,
                ], new Response(status: Response::HTTP_NOT_FOUND));
            }
            $snapshot = $scrapeResult['snapshot'];
        } elseif ($snapshot->getCharacterModel() === null) {
            // Backfill snapshots created before 3D model data was persisted.
            $profileHtml = $this->armoryScraperService->fetchArmoryHtml($characterName, $realmName, 'summary');
            if ($profileHtml !== null) {
                $characterModel = $this->armoryScraperService->extractCharacterModelData($profileHtml);
                if ($characterModel !== null) {
                    $snapshot->setCharacterModel($characterModel);
                    $this->snapshotRepository->save($snapshot);
                }
            }
        }

        $now = new \DateTimeImmutable();
        $scrapedAt = $snapshot->getScrapedAt();
        $snapshotAgeSeconds = $scrapedAt === null
            ? 0
            : max(0, $now->getTimestamp() - $scrapedAt->getTimestamp());
        $staleAgeDays = $snapshotAgeSeconds >= 86400
            ? intdiv($snapshotAgeSeconds, 86400)
            : null;

        return $this->renderCharacterView($snapshot, $staleAgeDays, $warningMessage);
    }

    #[Route('/characters/{characterName}/{realmName}/refresh', name: 'app_character_refresh', methods: ['POST'])]
    #[Route('/character/{characterName}/{realmName}/refresh', name: 'app_character_refresh_legacy', methods: ['POST'])]
    public function refreshCharacter(string $characterName, string $realmName): Response
    {
        $decision = $this->updateThrottle->claim($characterName, $realmName);
        if (!$decision->accepted) {
            $retryMinutes = max(1, (int) ceil($decision->retryAfterSeconds() / 60));
            $this->addFlash('warning', sprintf(
                'Character data can only be refreshed once every five minutes. Try again in about %d %s.',
                $retryMinutes,
                $retryMinutes === 1 ? 'minute' : 'minutes',
            ));

            return $this->redirectToRoute('app_character_view', [
                'characterName' => $characterName,
                'realmName' => $realmName,
            ]);
        }

        $existingSnapshot = $this->snapshotRepository->findByNameAndRealm($characterName, $realmName);
        $scrapeResult = $this->scrapeAndSave($characterName, $realmName);

        if ($scrapeResult['status'] === 'error') {
            if ($existingSnapshot !== null) {
                $this->addFlash('warning', 'Warmane armory is currently unreachable. Showing previously saved data.');
                return $this->redirectToRoute('app_character_view', [
                    'characterName' => $characterName,
                    'realmName' => $realmName,
                ]);
            }

            return $this->render('character_view/not_found.html.twig', [
                'characterName' => $characterName,
                'realmName' => $realmName,
            ], new Response(status: Response::HTTP_NOT_FOUND));
        }

        if ($scrapeResult['status'] === 'unchanged') {
            $this->addFlash('warning', 'No new character data was available. The saved data was not changed.');
            return $this->redirectToRoute('app_character_view', [
                'characterName' => $characterName,
                'realmName' => $realmName,
            ]);
        }

        $this->addFlash('success', 'Character data successfully refreshed!');
        return $this->redirectToRoute('app_character_view', [
            'characterName' => $characterName,
            'realmName' => $realmName,
        ]);
    }

    #[Route('/characters/{characterName}/{realmName}/match-details/{gameId}', name: 'app_character_match_details', methods: ['GET'])]
    #[Route('/character/{characterName}/{realmName}/match-details/{gameId}', name: 'app_character_match_details_legacy', methods: ['GET'])]
    public function getMatchDetails(string $characterName, string $realmName, string $gameId): JsonResponse
    {
        $details = $this->armoryScraperService->fetchMatchDetails($characterName, $realmName, $gameId);
        return new JsonResponse($details);
    }

    private function scrapeAndSave(string $characterName, string $realmName): array
    {
        $profileHtml = $this->armoryScraperService->fetchArmoryHtml($characterName, $realmName, 'summary');

        if ($profileHtml === null || !$this->armoryScraperService->checkCharacterExists($profileHtml)) {
            return ['status' => 'error'];
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
        $talentStrings = $this->armoryScraperService->extractTalentPointsString(
            $talentHtml ?? '',
            $characterDetails['class']
        );
        $talentTreesData = $this->armoryScraperService->extractTalentTrees($talentHtml ?? '');

        $gearScore = $this->armoryScraperService->calculateGearScore($equippedItems);
        $avgIlvl = $this->armoryScraperService->calculateAvgIlvl($equippedItems);

        $enchantsStatus = $this->armoryScraperService->checkEnchants($equippedItems, $characterDetails['class'], $professions);
        $gemsStatus = $this->armoryScraperService->checkGems($equippedItems);

        $killStatsHtml = $this->armoryScraperService->fetchArmoryHtml($characterName, $realmName, 'kills');
        $generalKills = [];
        if ($killStatsHtml) {
            $generalKills = $this->armoryScraperService->extractKillCount($killStatsHtml, 'general_kills');
        }

        $pvpStats = $this->armoryScraperService->extractPvpSummary($profileHtml);
        $matchHistory = [];
        if ($matchHistoryHtml) {
            $matchHistory = $this->armoryScraperService->extractMatchHistory($matchHistoryHtml);
        }

        $snapshot = new CharacterSnapshot();
        $snapshot->setName($characterName);
        $snapshot->setRealm($realmName);
        $snapshot->setLevel((int)($characterDetails['level'] ?? 80));
        $snapshot->setRace($characterDetails['race'] ?? 'Unknown');
        $snapshot->setClass($characterDetails['class'] ?? 'Unknown');
        $snapshot->setGuild($guild);
        $snapshot->setGearScore($gearScore);
        $snapshot->setAvgIlvl($avgIlvl);
        $snapshot->setProfessions($professions);
        $snapshot->setSpecializations($specializations);
        $snapshot->setEquippedItems($equippedItems);
        $snapshot->setCharacterModel($characterModel);
        $snapshot->setTalentStrings($talentStrings);
        $snapshot->setTalentTreesData($talentTreesData);
        $snapshot->setGlyphs($glyphs);
        $snapshot->setEnchantsStatus($enchantsStatus);
        $snapshot->setGemsStatus($gemsStatus);
        $snapshot->setKillStats($generalKills);
        $snapshot->setPvpStats($pvpStats);
        $snapshot->setMatchHistory($matchHistory);
        $snapshot->setScrapedAt(new \DateTimeImmutable());

        $updated = $this->snapshotRepository->upsert($snapshot);

        return [
            'status' => $updated ? 'success' : 'unchanged',
            'snapshot' => $snapshot,
        ];
    }

    private function renderCharacterView(CharacterSnapshot $snapshot, ?int $staleAgeDays = null, ?string $warningMessage = null): Response
    {
        $isWotlkServer = $this->talentTreeService->isWotlkServer(
            $snapshot->getRealm(),
            $snapshot->getGlyphs() ?? []
        );

        $parsedSpecs = $this->talentTreeService->parseTalentTrees(
            $snapshot->getClass(),
            $snapshot->getSpecializations() ?? [],
            $snapshot->getTalentStrings() ?? [],
            $snapshot->getTalentTreesData() ?? []
        );

        $paperdollData = $this->paperdollService->buildPaperdollSlots(
            $snapshot->getEquippedItems() ?? [],
            $snapshot->getClass()
        );

        return $this->render('character_view/index.html.twig', [
            'characterName' => $snapshot->getName(),
            'realmName' => $snapshot->getRealm(),
            'characterDetails' => [
                'level' => $snapshot->getLevel(),
                'gender' => $snapshot->getGender(),
                'race' => $snapshot->getRace(),
                'class' => $snapshot->getClass(),
            ],
            'guild' => $snapshot->getGuild(),
            'professions' => $snapshot->getProfessions(),
            'specializations' => $snapshot->getSpecializations(),
            'glyphs' => $snapshot->getGlyphs(),
            'talentStrings' => $snapshot->getTalentStrings(),
            'parsedSpecs' => $parsedSpecs,
            'isWotlkServer' => $isWotlkServer,
            'equippedItems' => $paperdollData['enrichedItems'],
            'paperdollSlots' => $paperdollData['slots'],
            'itemTooltips' => $paperdollData['tooltips'],
            'transmogItems' => $paperdollData['transmogItems'],
            'characterModel' => $snapshot->getCharacterModel(),
            'gearScore' => $snapshot->getGearScore(),
            'avgIlvl' => $snapshot->getAvgIlvl(),
            'enchantsStatus' => $snapshot->getEnchantsStatus(),
            'gemsStatus' => $snapshot->getGemsStatus(),
            'killStats' => $snapshot->getKillStats(),
            'pvpStats' => $snapshot->getPvpStats() ?? ['totalKills' => 0, 'killsToday' => 0, 'arenaTeams' => []],
            'matchHistory' => $snapshot->getMatchHistory() ?? [],
            'scrapedAt' => $snapshot->getScrapedAt(),
            'staleAgeDays' => $staleAgeDays,
            'warningMessage' => $warningMessage,
            'uwuRank' => $this->uwuRankRepository?->findBest((string) $snapshot->getName(), (string) $snapshot->getRealm()),
        ]);
    }
}
