<?php

namespace App\Controller;

use App\Http\ConditionalResponse;
use App\Repository\GuildSnapshotRepository;
use App\Repository\UwuLogRankRepository;
use App\Service\GuildRefreshResult;
use App\Service\GuildSnapshotUpdater;
use App\Service\GuildUpdateThrottle;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class GuildController extends AbstractController
{
    public function __construct(
        private readonly GuildSnapshotRepository $guildRepository,
        private readonly UwuLogRankRepository $rankRepository,
        private readonly GuildSnapshotUpdater $guildUpdater,
        private readonly GuildUpdateThrottle $updateThrottle,
    ) {
    }

    #[Route('/guilds/{guildName}/{realmName}', name: 'app_guild_view', methods: ['GET'])]
    #[Route('/guild/{guildName}/{realmName}', name: 'app_guild_view_legacy', methods: ['GET'])]
    public function view(string $guildName, string $realmName, ?Request $request = null): Response
    {
        $request ??= new Request();
        $snapshot = $this->guildRepository->findByNameAndRealm($guildName, $realmName);
        if ($snapshot === null) {
            $decision = $this->updateThrottle->claim($guildName, $realmName);
            if (!$decision->accepted) {
                return $this->renderNotFound($guildName, $realmName);
            }

            $result = $this->guildUpdater->refresh($guildName, $realmName);
            $snapshot = $result->snapshot;
            if ($snapshot === null || $result->status === GuildRefreshResult::NOT_FOUND) {
                return $this->renderNotFound($guildName, $realmName);
            }
        }

        return $this->renderGuild($snapshot, $request);
    }

    #[Route('/guilds/{guildName}/{realmName}/refresh', name: 'app_guild_refresh', methods: ['POST'])]
    public function refresh(string $guildName, string $realmName): Response
    {
        $decision = $this->updateThrottle->claim($guildName, $realmName);
        if (!$decision->accepted) {
            $this->addFlash('warning', 'Guild rosters can only be updated once per day. Try again tomorrow.');

            return $this->redirectToRoute('app_guild_view', compact('guildName', 'realmName'));
        }

        $result = $this->guildUpdater->refresh($guildName, $realmName);
        if ($result->status === GuildRefreshResult::UPDATED) {
            $this->addFlash('success', 'Guild roster updated. Uwu-logs ranks are being refreshed gradually in the background.');
        } elseif ($result->status === GuildRefreshResult::UNCHANGED) {
            $this->addFlash('warning', 'The guild roster has not changed.');
        } else {
            $this->addFlash('warning', 'Warmane guild data is currently unavailable. Showing the saved roster.');
        }

        return $this->redirectToRoute('app_guild_view', compact('guildName', 'realmName'));
    }

    private function renderGuild(\App\Entity\GuildSnapshot $snapshot, Request $request): Response
    {
        $members = $snapshot->getMembers();
        $names = array_column($members, 'name');
        $ranks = $this->rankRepository->findBestForCharacters($names, (string) $snapshot->getRealm());
        $filter = trim($request->query->getString('filter'));
        if ($filter !== '') {
            $needle = mb_strtolower($filter);
            $members = array_values(array_filter($members, function (array $member) use ($needle, $ranks): bool {
                $memberKey = mb_strtolower((string) ($member['name'] ?? ''));
                $rank = $ranks[$memberKey] ?? null;
                $searchable = implode(' ', [
                    (string) ($member['name'] ?? ''),
                    (string) ($member['race'] ?? ''),
                    (string) ($member['class'] ?? ''),
                    (string) ($member['rank'] ?? ''),
                    implode(' ', $member['professions'] ?? []),
                    $rank ? $this->specName($rank, $member['class'] ?? null) : '',
                ]);

                return str_contains(mb_strtolower($searchable), $needle);
            }));
        }
        $refreshDecision = $this->updateThrottle->inspect((string) $snapshot->getName(), (string) $snapshot->getRealm());

        $response = $this->render('guild_view/index.html.twig', [
            'guild' => $snapshot,
            'members' => $members,
            'totalMembers' => count($snapshot->getMembers()),
            'memberFilter' => $filter,
            'ranks' => $ranks,
            'refreshDecision' => $refreshDecision,
            'flashes' => ConditionalResponse::flashes($request),
        ]);

        return ConditionalResponse::apply($response, $request, [
            __FILE__,
            dirname(__DIR__, 2).'/templates/guild_view/index.html.twig',
            dirname(__DIR__, 2).'/templates/_site_header.html.twig',
            dirname(__DIR__).'/Entity/GuildSnapshot.php',
            dirname(__DIR__).'/Entity/GuildRankSummary.php',
            dirname(__DIR__).'/Repository/UwuLogRankRepository.php',
            dirname(__DIR__).'/Repository/GuildSnapshotRepository.php',
            dirname(__DIR__).'/Service/GuildUpdateThrottle.php',
        ]);
    }

    private function renderNotFound(string $guildName, string $realmName): Response
    {
        return $this->render('guild_view/not_found.html.twig', compact('guildName', 'realmName'), new Response(status: 404));
    }

    private function specName(object $rank, ?string $className): string
    {
        if (method_exists($rank, 'specName')) {
            return $rank->specName($className);
        }

        return method_exists($rank, 'getSpecName')
            ? $rank->getSpecName($className)
            : 'Unknown specialization';
    }
}
