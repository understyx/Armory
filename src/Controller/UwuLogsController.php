<?php

namespace App\Controller;

use App\Exception\UwuLogsException;
use App\Service\UwuRankUpdater;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class UwuLogsController extends AbstractController
{
    #[Route('/characters/{characterName}/{realmName}/uwu-logs', name: 'app_character_uwu_logs', methods: ['POST'])]
    public function rankings(
        string $characterName,
        string $realmName,
        Request $request,
        UwuRankUpdater $uwuRankUpdater
    ): JsonResponse {
        $spec = $request->getPayload()->getString('spec', '1');

        try {
            return $this->json($uwuRankUpdater->getOrRefresh($characterName, $realmName, $spec));
        } catch (UwuLogsException $exception) {
            return $this->json(
                ['error' => $exception->getMessage()],
                $exception->getHttpStatus()
            );
        }
    }
}
