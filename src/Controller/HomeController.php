<?php
// src/Controller/HomeController.php
namespace App\Controller;

use App\Http\ConditionalResponse;
use App\Repository\CharacterSnapshotRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class HomeController extends AbstractController
{
    private const REALMS = ['Icecrown', 'Lordaeron', 'Onyxia', 'Blackrock'];

    #[Route('/', name: 'app_homepage', methods: ['GET'])]
    #[Route('/characters', name: 'app_characters', methods: ['GET'])]
    public function index(Request $request, CharacterSnapshotRepository $snapshotRepository): Response
    {
        $characterName = trim($request->query->getString('character'));
        $realmName = $request->query->getString('realm', self::REALMS[0]);
        $searchType = $request->query->getString('type', 'character');

        if ($characterName !== '') {
            if (!in_array($realmName, self::REALMS, true)) {
                $realmName = self::REALMS[0];
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
            'realms' => self::REALMS,
            'recentCharacters' => $snapshotRepository->findRecentlyViewed(10),
        ]);

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
