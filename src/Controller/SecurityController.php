<?php
// src/Controller/SecurityController.php
namespace App\Controller;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    #[Route('/connect/discord', name: 'connect_discord_start')]
    public function connectDiscordAction(ClientRegistry $clientRegistry): RedirectResponse
    {
        // will redirect to Discord
        /** @var \KnpU\OAuth2ClientBundle\Client\OAuth2Client $client */
        $client = $clientRegistry->getClient('discord');

        return $client->redirect(['identify']); // Request these scopes
    }

    #[Route('/connect/discord/check', name: 'connect_discord_check')]
    public function connectDiscordCheckAction(Request $request): Response
    {
        // This route is primarily handled by the DiscordAuthenticator.
        // If execution reaches here, something went wrong with the authentication.
        $this->addFlash('error', 'Discord authentication check route was reached directly. Authentication might have failed.');
        return $this->redirectToRoute('app_login');
    }
}