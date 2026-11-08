<?php

namespace App\Controller;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SecurityController extends AbstractController
{
    #[Route('/login', name: 'app_login', methods: ['GET'])]
    public function login(): RedirectResponse
    {
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
