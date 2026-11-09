<?php

namespace App\Controller;

use App\Entity\CharacterComment;
use App\Entity\User;
use App\Http\ConditionalResponse;
use App\Repository\CharacterCommentRepository;
use App\Service\BestCharacterRankService;
use App\Service\CharacterCommentContent;
use App\Service\CharacterCommentEligibility;
use App\Service\CharacterCommentAuthorization;
use App\Service\CharacterCommentRateLimiter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CharacterCommentController extends AbstractController
{
    public function __construct(
        private readonly CharacterCommentRepository $commentRepository,
        private readonly CharacterCommentEligibility $eligibility,
        private readonly CharacterCommentAuthorization $authorization,
        private readonly CharacterCommentRateLimiter $rateLimiter,
        private readonly BestCharacterRankService $bestCharacterRankService,
        private readonly CharacterCommentContent $commentContent,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/characters/{characterName}/{realmName}/comments', name: 'app_character_comments', methods: ['GET'])]
    public function comments(string $characterName, string $realmName, Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));

        return $this->renderFragmentResponse($characterName, $realmName, $page, $request);
    }

    #[Route('/characters/{characterName}/{realmName}/comments', name: 'app_character_comment_create', methods: ['POST'])]
    public function create(string $characterName, string $realmName, Request $request): Response
    {
        $user = $this->currentUser();
        if (!$this->isCsrfTokenValid('comment-post', $request->request->getString('_token'))) {
            return $this->postError($characterName, $realmName, $request, $user, 'This form expired. Reload the comments and try again.', Response::HTTP_FORBIDDEN);
        }
        if ($user === null) {
            return $this->postError($characterName, $realmName, $request, null, 'Log in with Discord to comment.', Response::HTTP_UNAUTHORIZED);
        }
        $this->bestCharacterRankService->recompute($user);
        if (!$this->eligibility->isEligible($user)) {
            return $this->postError(
                $characterName,
                $realmName,
                $request,
                $user,
                'Link a character with 50+ Uwu-logs points on your account page to comment.',
                Response::HTTP_FORBIDDEN,
            );
        }

        $validated = $this->commentContent->validate($request->request->getString('body'));
        $body = $validated['body'] ?? $request->request->getString('body');
        if ($validated['error'] !== null) {
            return $this->postError($characterName, $realmName, $request, $user, $validated['error'], Response::HTTP_UNPROCESSABLE_ENTITY, $body);
        }

        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();
        try {
            $wait = $this->rateLimiter->secondsUntilAllowed($user, new \DateTimeImmutable());
            if ($wait > 0) {
                $connection->rollBack();

                return $this->postError(
                    $characterName,
                    $realmName,
                    $request,
                    $user,
                    sprintf('Please wait %d seconds before posting another comment.', $wait),
                    Response::HTTP_TOO_MANY_REQUESTS,
                    $body,
                );
            }

            $this->entityManager->persist(new CharacterComment($user, $characterName, $realmName, $body));
            $this->entityManager->flush();
            $connection->commit();
        } catch (\Throwable $exception) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }

            throw $exception;
        }

        if ($request->isXmlHttpRequest()) {
            return $this->privateFragmentResponse($characterName, $realmName, $request);
        }

        return $this->redirectToRoute('app_character_view', [
            'characterName' => ucfirst(trim($characterName)),
            'realmName' => ucfirst(trim($realmName)),
        ], Response::HTTP_SEE_OTHER);
    }

    #[Route('/characters/{characterName}/{realmName}/comments/{id}/delete', name: 'app_character_comment_delete', methods: ['POST'])]
    public function delete(int $id, string $characterName, string $realmName, Request $request): Response
    {
        $user = $this->currentUser();
        if ($user === null) {
            return $this->postError($characterName, $realmName, $request, null, 'Log in with Discord to manage comments.', Response::HTTP_UNAUTHORIZED);
        }
        if (!$this->isCsrfTokenValid('delete-character-comment-'.$id, $request->request->getString('_token'))) {
            return $this->postError($characterName, $realmName, $request, $user, 'This form expired. Reload the comments and try again.', Response::HTTP_FORBIDDEN);
        }

        $comment = $this->commentRepository->findActiveById($id);
        if ($comment === null) {
            throw $this->createNotFoundException();
        }
        if (!$this->authorization->canDelete($user, $comment, $this->isGranted('ROLE_ADMIN'))) {
            return $this->postError($characterName, $realmName, $request, $user, 'You cannot delete this comment.', Response::HTTP_FORBIDDEN);
        }

        $comment->delete(new \DateTimeImmutable());
        $this->entityManager->flush();
        if ($request->isXmlHttpRequest()) {
            return $this->privateFragmentResponse($characterName, $realmName, $request);
        }

        return new RedirectResponse($this->generateUrl('app_character_view', [
            'characterName' => ucfirst(trim($characterName)),
            'realmName' => ucfirst(trim($realmName)),
        ]).'#profile-comments', Response::HTTP_SEE_OTHER);
    }

    private function postError(
        string $characterName,
        string $realmName,
        Request $request,
        ?User $user,
        string $error,
        int $status,
        string $draft = '',
    ): Response {
        if (!$request->isXmlHttpRequest()) {
            if ($user === null) {
                return $this->redirectToRoute('app_login', [
                    'return' => $this->generateUrl('app_character_view', [
                        'characterName' => $characterName,
                        'realmName' => $realmName,
                    ]),
                ]);
            }
            $this->addFlash('error', $error);

            return $this->redirectToRoute('app_character_view', [
                'characterName' => $characterName,
                'realmName' => $realmName,
            ]);
        }

        $response = $this->renderFragmentResponse($characterName, $realmName, 1, $request, $error, $draft);
        $response->setStatusCode($status);

        return $this->privateResponse($response);
    }

    private function renderFragmentResponse(
        string $characterName,
        string $realmName,
        int $page,
        Request $request,
        ?string $error = null,
        string $draft = '',
    ): Response {
        $result = $this->commentRepository->findPage($characterName, $realmName, $page);
        $user = $this->currentUser();
        $response = $this->render('character_view/_comments_fragment.html.twig', [
            'characterName' => ucfirst(trim($characterName)),
            'realmName' => ucfirst(trim($realmName)),
            'comments' => $result['comments'],
            'hasMore' => $result['hasMore'],
            'nextPage' => $page + 1,
            'includeForm' => $page === 1,
            'user' => $user,
            'canComment' => $this->eligibility->isEligible($user),
            'commentContent' => $this->commentContent,
            'error' => $error,
            'draft' => $draft,
            'now' => new \DateTimeImmutable(),
        ]);

        if ($user !== null) {
            return $this->privateResponse($response);
        }

        return ConditionalResponse::apply(
            $response,
            $request,
            [dirname(__DIR__, 2).'/templates/character_view/_comments_fragment.html.twig'],
            sharedMaxAge: 30,
            sessionIndependent: true,
        );
    }

    private function privateFragmentResponse(string $characterName, string $realmName, Request $request): Response
    {
        return $this->privateResponse($this->renderFragmentResponse($characterName, $realmName, 1, $request));
    }

    private function privateResponse(Response $response): Response
    {
        $response->setPrivate();
        $response->setMaxAge(0);
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }

    private function currentUser(): ?User
    {
        $user = $this->getUser();

        return $user instanceof User ? $user : null;
    }
}
