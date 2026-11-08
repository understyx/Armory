<?php

namespace App\Controller;

use App\Entity\CharacterClaimChallenge;
use App\Entity\User;
use App\Message\ScanCharacterClaimMessage;
use App\Repository\CharacterClaimChallengeRepository;
use App\Repository\CharacterClaimRepository;
use App\Routing\RouteRequirements;
use App\Service\CharacterClaimChallengeManager;
use App\Service\DeduplicatingMessageDispatcher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class AccountController extends AbstractController
{
    public function __construct(
        private readonly CharacterClaimRepository $claimRepository,
        private readonly CharacterClaimChallengeRepository $challengeRepository,
        private readonly CharacterClaimChallengeManager $challengeManager,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    #[Route('/account', name: 'app_account', methods: ['GET'])]
    public function index(): Response
    {
        $user = $this->currentUser();
        $challenge = $this->currentChallenge($user);
        $response = $this->render('account/index.html.twig', [
            'user' => $user,
            'claims' => $this->claimRepository->findForUser($user),
            'challenge' => $challenge,
            'realms' => RouteRequirements::REALMS,
        ]);

        return $this->privateResponse($response);
    }

    #[Route('/account/link', name: 'account_link_start', methods: ['POST'])]
    public function startLink(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('link-character', $request->request->getString('_token'))) {
            $this->addFlash('error', 'That link form expired. Reload the page and submit it again.');

            return $this->redirectToRoute('app_account');
        }

        $name = trim($request->request->getString('name'));
        $realmInput = trim($request->request->getString('realm'));
        if (!RouteRequirements::isCharacterName($name)) {
            $this->addFlash('error', 'Enter a character name using 1–12 letters, or a Warmane C_ rename.');

            return $this->redirectToRoute('app_account');
        }

        $realm = null;
        foreach (RouteRequirements::REALMS as $validRealm) {
            if (strcasecmp($realmInput, $validRealm) === 0) {
                $realm = $validRealm;
                break;
            }
        }
        if ($realm === null) {
            $this->addFlash('error', 'Choose a realm from the list.');

            return $this->redirectToRoute('app_account');
        }

        $challenge = $this->challengeManager->start($this->currentUser(), ucfirst($name), $realm);
        if ($challenge === null) {
            $this->addFlash('warning', 'You can start up to 10 character link challenges per hour.');

            return $this->redirectToRoute('app_account');
        }

        DeduplicatingMessageDispatcher::dispatch(
            $this->messageBus,
            new ScanCharacterClaimMessage((int) $challenge->getId(), ScanCharacterClaimMessage::BASELINE),
        );

        return $this->redirectToRoute('app_account');
    }

    #[Route('/account/link/verify', name: 'account_link_verify', methods: ['POST'])]
    public function verifyLink(Request $request): Response
    {
        if ($this->isCsrfTokenValid('verify-character-claim', $request->request->getString('_token'))) {
            $challenge = $this->currentChallenge($this->currentUser());
            if ($challenge?->getState() === CharacterClaimChallenge::AWAITING_REMOVAL) {
                $challenge->setState(CharacterClaimChallenge::SCANNING_VERIFY);
                $challenge->setScanLeaseUntil(null);
                $this->entityManager->flush();
                DeduplicatingMessageDispatcher::dispatch(
                    $this->messageBus,
                    new ScanCharacterClaimMessage((int) $challenge->getId(), ScanCharacterClaimMessage::VERIFY),
                );
            }
        }

        return $this->redirectToRoute('app_account');
    }

    #[Route('/account/link/cancel', name: 'account_link_cancel', methods: ['POST'])]
    public function cancelLink(Request $request): Response
    {
        if ($this->isCsrfTokenValid('cancel-character-claim', $request->request->getString('_token'))) {
            $challenge = $this->currentChallenge($this->currentUser());
            if ($challenge !== null) {
                $this->entityManager->remove($challenge);
                $this->entityManager->flush();
            }
        }

        return $this->redirectToRoute('app_account');
    }

    #[Route('/account/characters/{id}/unlink', name: 'account_character_unlink', methods: ['POST'])]
    public function unlinkCharacter(int $id, Request $request): Response
    {
        if ($this->isCsrfTokenValid('unlink-character-'.$id, $request->request->getString('_token'))) {
            $claim = $this->claimRepository->findOneBy(['id' => $id, 'user' => $this->currentUser()]);
            if ($claim !== null) {
                $this->entityManager->remove($claim);
                $this->entityManager->flush();
            }
        }

        return $this->redirectToRoute('app_account');
    }

    #[Route('/account/link/status', name: 'account_link_status', methods: ['GET'])]
    public function linkStatus(): JsonResponse
    {
        $challenge = $this->currentChallenge($this->currentUser());
        $payload = $challenge === null ? ['state' => 'none'] : [
            'id' => $challenge->getId(),
            'state' => $challenge->getState(),
            'requiredSlots' => $challenge->getRequiredSlots(),
            'revision' => $challenge->getStateRevision(),
        ];
        $response = new JsonResponse($payload);

        return $this->privateResponse($response);
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    private function currentChallenge(User $user): ?CharacterClaimChallenge
    {
        $challenge = $this->challengeRepository->findCurrentForUser($user);
        if ($challenge !== null && !$challenge->isTerminal() && $challenge->getExpiresAt() !== null && $challenge->getExpiresAt() <= new \DateTimeImmutable()) {
            $challenge->setState(CharacterClaimChallenge::EXPIRED);
            $challenge->setScanLeaseUntil(null);
            $this->entityManager->flush();
        }

        return $challenge;
    }

    private function privateResponse(Response $response): Response
    {
        $response->setPrivate();
        $response->setMaxAge(0);
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }
}
