<?php

namespace App\Controller;

use App\Http\ConditionalResponse;
use App\Service\EnchantDatabase;
use App\Service\GemDatabase;
use App\Service\GlyphDatabase;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class ReferenceDataApiController extends AbstractController
{
    private const CACHE_MAX_AGE = 3600;

    #[Route('/api/enchants', name: 'api_enchants_get', methods: ['GET'])]
    public function enchants(?Request $request = null): JsonResponse
    {
        $request ??= new Request();
        $response = new JsonResponse(EnchantDatabase::getAll());
        $this->applyHeaders($response, $request, dirname(__DIR__).'/Service/EnchantDatabase.php');

        return $response;
    }

    #[Route('/api/gems', name: 'api_gems_get', methods: ['GET'])]
    public function gems(?Request $request = null): JsonResponse
    {
        $request ??= new Request();
        $response = new JsonResponse(GemDatabase::getAllByItemId());
        $this->applyHeaders($response, $request, dirname(__DIR__).'/Service/GemDatabase.php');

        return $response;
    }

    #[Route('/api/glyphs', name: 'api_glyphs_get', methods: ['GET'])]
    public function glyphs(?Request $request = null): JsonResponse
    {
        $request ??= new Request();
        $response = new JsonResponse(GlyphDatabase::getAll());
        $this->applyHeaders($response, $request, dirname(__DIR__).'/Service/GlyphDatabase.php');

        return $response;
    }

    private function applyHeaders(JsonResponse $response, Request $request, string $dataFile): void
    {
        $response->headers->set('Access-Control-Allow-Origin', '*');
        ConditionalResponse::apply(
            $response,
            $request,
            [__FILE__, $dataFile],
            self::CACHE_MAX_AGE,
            self::CACHE_MAX_AGE,
            sessionIndependent: true,
        );
    }
}
