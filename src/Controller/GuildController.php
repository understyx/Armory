<?php

namespace App\Controller;

use App\Repository\GuildSnapshotRepository;
use App\Repository\UwuLogRankRepository;
use App\Service\GuildRefreshResult;
use App\Service\GuildSnapshotUpdater;
use App\Service\GuildUpdateThrottle;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
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
    public function view(string $guildName, string $realmName): Response
    {
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

        return $this->renderGuild($snapshot);
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

    private function renderGuild(\App\Entity\GuildSnapshot $snapshot): Response
    {
        $names = array_column($snapshot->getMembers(), 'name');

        return $this->render('guild_view/index.html.twig', [
            'guild' => $snapshot,
            'ranks' => $this->rankRepository->findBestForCharacters($names, (string) $snapshot->getRealm()),
        ]);
    }

    private function renderNotFound(string $guildName, string $realmName): Response
    {
        return $this->render('guild_view/not_found.html.twig', compact('guildName', 'realmName'), new Response(status: 404));
    }
}
