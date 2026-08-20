<?php
// src/Security/DiscordAuthenticator.php - Adjusted for no email scope
namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Wohali\OAuth2\Client\Provider\DiscordUser;

class DiscordAuthenticator extends OAuth2Authenticator implements AuthenticationEntryPointInterface
{
    private ClientRegistry $clientRegistry;
    private EntityManagerInterface $entityManager;
    private RouterInterface $router;
    private UserRepository $userRepository;

    public function __construct(
        ClientRegistry $clientRegistry,
        EntityManagerInterface $entityManager,
        RouterInterface $router,
        UserRepository $userRepository
    ) {
        $this->clientRegistry = $clientRegistry;
        $this->entityManager = $entityManager;
        $this->router = $router;
        $this->userRepository = $userRepository;
    }

    public function supports(Request $request): ?bool
    {
        return $request->attributes->get('_route') === 'connect_discord_check';
    }

    public function authenticate(Request $request): Passport
    {
        $client = $this->clientRegistry->getClient('discord');
        $accessToken = $this->fetchAccessToken($client);

        return new SelfValidatingPassport(
            new UserBadge($accessToken->getToken(), function () use ($accessToken, $client) {
                /** @var DiscordUser $discordUser */
                $discordUser = $client->fetchUserFromToken($accessToken);

                // No email requested, so we cannot rely on it for identification or saving.
                // The Discord ID is now the primary unique identifier.
                $discordId = $discordUser->getId();
                $discordUsername = $discordUser->getUsername();

                if (empty($discordId)) {
                    throw new AuthenticationException('Discord did not return a user ID. Cannot log in.');
                }

                $user = $this->userRepository->findOneBy(['discordId' => $discordId]);

                if (!$user) {
                    // User does not exist in our system by Discord ID, create a new one.
                    $user = new User();
                    $user->setDiscordId($discordId);
                    $user->setDiscordUsername($discordUsername);
                    $user->setRoles(['ROLE_USER']);

                    // IMPORTANT: If your User entity requires a non-nullable 'email' field
                    // you MUST set a dummy email here, or change the User entity's email field to nullable.
                    // For example, using a generated unique email:
                    $user->setEmail('discord_user_' . $discordId . '@yourdomain.com'); // <--- IMPORTANT if email is non-nullable and unique
                    // Or, if email is nullable and not strictly unique:
                    // $user->setEmail(null); // Or leave unset if nullable

                } else {
                    // Update existing user's Discord username if it changed
                    $user->setDiscordUsername($discordUsername);
                    // No email to update as we didn't request it
                }

                $this->entityManager->persist($user);
                $this->entityManager->flush();

                return $user;
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $targetUrl = $this->router->generate('app_homepage');
        return new RedirectResponse($targetUrl);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $message = strtr($exception->getMessageKey(), $exception->getMessageData());
        $request->getSession()->getFlashBag()->add('error', 'Discord Login Failed: ' . $message);
        return new RedirectResponse($this->router->generate('app_login'));
    }

    public function start(Request $request, AuthenticationException $authException = null): Response
    {
        return new RedirectResponse(
            $this->router->generate('connect_discord_start')
        );
    }
}