<?php
// src/Controller/HomeController.php
namespace App\Controller;

use App\Repository\CharacterSnapshotRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class HomeController extends AbstractController
{
    private const REALMS = ['Icecrown', 'Lordaeron', 'Frostmourne', 'Blackrock'];

    #[Route('/', name: 'app_homepage', methods: ['GET'])]
    #[Route('/characters', name: 'app_characters', methods: ['GET'])]
    public function index(Request $request, CharacterSnapshotRepository $snapshotRepository): Response
    {
        $characterName = trim($request->query->getString('character'));
        $realmName = $request->query->getString('realm', self::REALMS[0]);

        if ($characterName !== '') {
            if (!in_array($realmName, self::REALMS, true)) {
                $realmName = self::REALMS[0];
            }

            return $this->redirectToRoute('app_character_view', [
                'characterName' => $characterName,
                'realmName' => $realmName,
            ]);
        }

        return $this->render('home/index.html.twig', [
            'realms' => self::REALMS,
            'recentCharacters' => $snapshotRepository->findRecentlyViewed(10),
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
