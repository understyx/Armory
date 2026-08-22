<?php

namespace App\Controller;

use App\Service\WowIconCache;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class WowIconController
{
    public function __construct(
        private readonly WowIconCache $iconCache,
    ) {
    }

    #[Route(
        '/wow-icons/large/{icon}.jpg',
        name: 'app_wow_icon',
        requirements: ['icon' => '[A-Za-z0-9][A-Za-z0-9_-]{0,127}'],
        methods: ['GET', 'HEAD']
    )]
    public function icon(string $icon): Response
    {
        $path = $this->iconCache->get($icon);
        if ($path === null) {
            return new Response('Icon unavailable.', Response::HTTP_BAD_GATEWAY);
        }

        $response = new BinaryFileResponse($path);
        $response->headers->set('Content-Type', 'image/jpeg');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->setPublic();
        $response->setMaxAge(31_536_000);
        $response->setSharedMaxAge(31_536_000);
        $response->setImmutable();

        return $response;
    }
}
