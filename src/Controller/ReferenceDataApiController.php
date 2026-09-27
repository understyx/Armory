<?php

namespace App\Controller;

use App\Service\EnchantDatabase;
use App\Service\GemDatabase;
use App\Service\GlyphDatabase;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class ReferenceDataApiController extends AbstractController
{
    private const CACHE_MAX_AGE = 3600;

    #[Route('/api/enchants', name: 'api_enchants_get', methods: ['GET'])]
    public function enchants(): JsonResponse
    {
        $response = new JsonResponse(EnchantDatabase::getAll());
        $this->applyHeaders($response);

        return $response;
    }

    #[Route('/api/gems', name: 'api_gems_get', methods: ['GET'])]
    public function gems(): JsonResponse
    {
        $response = new JsonResponse(GemDatabase::getAllByItemId());
        $this->applyHeaders($response);

        return $response;
    }

    #[Route('/api/glyphs', name: 'api_glyphs_get', methods: ['GET'])]
    public function glyphs(): JsonResponse
    {
        $response = new JsonResponse(GlyphDatabase::getAll());
        $this->applyHeaders($response);

        return $response;
    }

    private function applyHeaders(JsonResponse $response): void
    {
        $response->setPublic();
        $response->setMaxAge(self::CACHE_MAX_AGE);
        $response->headers->set('Access-Control-Allow-Origin', '*');
    }
}
