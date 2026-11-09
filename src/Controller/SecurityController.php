<?php

namespace App\Controller;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

final class SecurityController extends AbstractController
{
    use TargetPathTrait;

    #[Route('/login', name: 'app_login', methods: ['GET'])]
    public function login(Request $request): RedirectResponse
    {
        $returnPath = $request->query->get('return');
        if (is_string($returnPath)
            && str_starts_with($returnPath, '/')
            && !str_starts_with($returnPath, '//')
            // Browsers treat "\" as "/", so "/\evil.com" would leave the site.
            && preg_match('/[\\\\\x00-\x1F\x7F]/', $returnPath) !== 1
            && parse_url($returnPath, PHP_URL_HOST) === null
        ) {
            $this->saveTargetPath($request->getSession(), 'main', $returnPath);
        }

        return $this->redirectToRoute('connect_discord_start');
    }

    #[Route('/logout', name: 'app_logout', methods: ['POST'])]
    public function logout(): void
    {
        throw new \LogicException('The firewall handles logout requests.');
    }

    #[Route('/connect/discord', name: 'connect_discord_start', methods: ['GET'])]
    public function connectDiscord(ClientRegistry $clientRegistry): RedirectResponse
    {
        return $clientRegistry->getClient('discord')->redirect(['identify']);
    }

    #[Route('/connect/discord/check', name: 'connect_discord_check', methods: ['GET'])]
    public function connectDiscordCheck(Request $request): Response
    {
        $request->getSession()->getFlashBag()->add('error', 'Discord login could not be completed.');

        return $this->redirectToRoute('app_homepage');
    }
}
