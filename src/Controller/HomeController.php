<?php
// src/Controller/HomeController.php
namespace App\Controller;

use App\Http\ConditionalResponse;
use App\Repository\CharacterSnapshotRepository;
use App\Routing\RouteRequirements;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_homepage', methods: ['GET'])]
    #[Route('/characters', name: 'app_characters', methods: ['GET'])]
    public function index(Request $request, CharacterSnapshotRepository $snapshotRepository): Response
    {
        $characterName = trim($request->query->getString('character'));
        $realmName = $request->query->getString('realm', RouteRequirements::REALMS[0]);
        $searchType = $request->query->getString('type', 'character');
        $searchError = null;

        if ($characterName !== '' && $searchType !== 'guild' && !RouteRequirements::isCharacterName($characterName)) {
            $searchError = 'Character names are 1–12 letters (A–Z) only.';
        } elseif ($characterName !== '') {
            if (!in_array($realmName, RouteRequirements::REALMS, true)) {
                $realmName = RouteRequirements::REALMS[0];
            }

            if ($searchType === 'guild') {
                return $this->redirectToRoute('app_guild_view', [
                    'guildName' => $characterName,
                    'realmName' => $realmName,
                ]);
            }

            return $this->redirectToRoute('app_character_view', [
                'characterName' => $characterName,
                'realmName' => $realmName,
            ]);
        }

        $response = $this->render('home/index.html.twig', [
            'realms' => RouteRequirements::REALMS,
            'recentCharacters' => $snapshotRepository->findRecentlyViewed(10),
            'searchError' => $searchError,
        ]);
        if ($searchError !== null) {
            $response->setStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY);

            return $response;
        }

        return ConditionalResponse::apply($response, $request, [
            __FILE__,
            dirname(__DIR__, 2).'/templates/home/index.html.twig',
            dirname(__DIR__, 2).'/templates/_site_header.html.twig',
            dirname(__DIR__).'/Repository/CharacterSnapshotRepository.php',
        ]);
    }

    #[Route('/dashboard', name: 'app_dashboard')]
    #[IsGranted('ROLE_USER')]
    public function dashboard(): Response
    {
        return $this->render('home/dashboard.html.twig', [
            'user' => $this->getUser(),
        ]);
    }
}
