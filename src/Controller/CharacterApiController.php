<?php

namespace App\Controller;

use App\Entity\CharacterSnapshot;
use App\Message\RefreshCharacterSnapshotMessage;
use App\Repository\CharacterSnapshotRepository;
use App\Repository\UwuLogRankRepository;
use App\Service\CharacterApiFormatter;
use App\Service\CharacterUpdateThrottle;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

class CharacterApiController extends AbstractController
{
    public function __construct(
        private readonly CharacterSnapshotRepository $snapshotRepository,
        private readonly CharacterApiFormatter $formatter,
        private readonly CharacterUpdateThrottle $updateThrottle,
        private readonly MessageBusInterface $messageBus,
        private readonly ?UwuLogRankRepository $uwuLogRankRepository = null,
    ) {
    }

    #[Route('/api/character/{name}/{realm}', name: 'api_character_get', methods: ['GET'])]
    public function character(string $name, string $realm): JsonResponse
    {
        if (!$this->hasValidIdentifiers($name, $realm)) {
            return $this->invalidIdentifierResponse();
        }

        $snapshot = $this->snapshotRepository->findByNameAndRealm($name, $realm);
        if ($snapshot === null) {
            return new JsonResponse([
                'status' => 'not_found',
                'message' => 'No cached data exists for this character. Request an update first.',
            ], Response::HTTP_NOT_FOUND);
        }

        $uwuRank = $this->uwuLogRankRepository?->findBest($name, $realm);
        $response = new JsonResponse($this->formatter->format($snapshot, $uwuRank));
        $lastModified = $snapshot->getScrapedAt();
        if ($uwuRank?->getScrapedAt() !== null
            && ($lastModified === null || $uwuRank->getScrapedAt() > $lastModified)) {
            $lastModified = $uwuRank->getScrapedAt();
        }
        $this->applyCacheHeaders($response, $lastModified);

        return $response;
    }

    #[Route('/api/character/{name}/{realm}/stats', name: 'api_character_stats', methods: ['GET'])]
    public function stats(string $name, string $realm): JsonResponse
    {
        $snapshot = $this->findSnapshot($name, $realm);
        if ($snapshot instanceof JsonResponse) {
            return $snapshot;
        }

        $response = new JsonResponse($this->formatter->formatDetailedStats($snapshot));
        $this->applyCacheHeaders($response, $snapshot->getScrapedAt());

        return $response;
    }

    #[Route('/api/character/{name}/{realm}/achievements', name: 'api_character_achievements', methods: ['GET'])]
    public function achievements(string $name, string $realm): JsonResponse
    {
        $snapshot = $this->findSnapshot($name, $realm);
        if ($snapshot instanceof JsonResponse) {
            return $snapshot;
        }

        $response = new JsonResponse($this->formatter->formatAchievements($snapshot));
        $this->applyCacheHeaders($response, $snapshot->getScrapedAt());

        return $response;
    }

    #[Route('/api/requestupdate/{name}/{realm}', name: 'api_character_request_update', methods: ['POST'])]
    public function requestUpdate(string $name, string $realm): JsonResponse
    {
        if (!$this->hasValidIdentifiers($name, $realm)) {
            return $this->invalidIdentifierResponse();
        }

        $decision = $this->updateThrottle->claim($name, $realm);
        if (!$decision->accepted) {
            $retryAfter = $decision->retryAfterSeconds();

            return new JsonResponse([
                'status' => 'rate_limited',
                'message' => 'This character can only be updated once every five minutes.',
                'retryAfterSeconds' => $retryAfter,
                'nextUpdateAllowedAt' => $decision->nextAllowedAt->format(\DateTimeInterface::ATOM),
            ], Response::HTTP_TOO_MANY_REQUESTS, ['Retry-After' => (string) $retryAfter]);
        }

        $this->messageBus->dispatch(new RefreshCharacterSnapshotMessage($name, $realm));

        return new JsonResponse([
            'status' => 'queued',
            'message' => 'The character update has been queued.',
            'character' => ['name' => ucfirst($name), 'realm' => ucfirst($realm)],
            'nextUpdateAllowedAt' => $decision->nextAllowedAt->format(\DateTimeInterface::ATOM),
        ], Response::HTTP_ACCEPTED);
    }

    private function hasValidIdentifiers(string $name, string $realm): bool
    {
        foreach ([$name, $realm] as $identifier) {
            if (strlen($identifier) > 64 || preg_match("/^[\\p{L}\\p{N}'-]+$/u", $identifier) !== 1) {
                return false;
            }
        }

        return true;
    }

    private function invalidIdentifierResponse(): JsonResponse
    {
        return new JsonResponse([
            'status' => 'invalid_request',
            'message' => 'Character and realm must contain only letters, numbers, apostrophes, or hyphens.',
        ], Response::HTTP_BAD_REQUEST);
    }

    private function findSnapshot(string $name, string $realm): CharacterSnapshot|JsonResponse
    {
        if (!$this->hasValidIdentifiers($name, $realm)) {
            return $this->invalidIdentifierResponse();
        }

        return $this->snapshotRepository->findByNameAndRealm($name, $realm)
            ?? new JsonResponse([
                'status' => 'not_found',
                'message' => 'No cached data exists for this character. Request an update first.',
            ], Response::HTTP_NOT_FOUND);
    }

    private function applyCacheHeaders(JsonResponse $response, ?\DateTimeImmutable $lastModified): void
    {
        $response->setPublic();
        $response->setMaxAge(60);
        if ($lastModified !== null) {
            $response->setLastModified($lastModified);
        }
    }
}
