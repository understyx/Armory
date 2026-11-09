<?php

namespace App\Controller;

use App\Http\ConditionalResponse;
use App\Repository\UserRepository;
use App\Service\CharacterClaimOverviewService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class UserProfileController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly CharacterClaimOverviewService $overviewService,
    ) {
    }

    #[Route('/users/{discordUsername}', name: 'app_user_profile', methods: ['GET'])]
    public function profile(string $discordUsername, Request $request): Response
    {
        $user = $this->userRepository->findLatestByDiscordUsername($discordUsername);
        if ($user === null) {
            throw $this->createNotFoundException('No Discord profile was found.');
        }

        $overview = $user->getId() === null
            ? []
            : ($this->overviewService->forUsers([$user])[$user->getId()] ?? []);
        $response = $this->render('user_profile/index.html.twig', [
            'profileUser' => $user,
            'characters' => $overview,
        ]);

        return ConditionalResponse::apply($response, $request, [
            __FILE__,
            dirname(__DIR__, 2).'/templates/user_profile/index.html.twig',
            dirname(__DIR__, 2).'/templates/_site_header.html.twig',
            dirname(__DIR__).'/Repository/UserRepository.php',
            dirname(__DIR__).'/Repository/CharacterClaimRepository.php',
            dirname(__DIR__).'/Repository/CharacterSnapshotRepository.php',
            dirname(__DIR__).'/Repository/UwuLogRankRepository.php',
            dirname(__DIR__).'/Entity/User.php',
            dirname(__DIR__).'/Entity/CharacterClaim.php',
            dirname(__DIR__).'/Entity/CharacterSnapshot.php',
            dirname(__DIR__).'/Entity/UwuLogRank.php',
            dirname(__DIR__, 2).'/assets/styles/app.css',
        ], userSpecific: $request->hasPreviousSession());
    }
}
