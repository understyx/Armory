<?php

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
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Wohali\OAuth2\Client\Provider\DiscordResourceOwner;

final class DiscordAuthenticator extends OAuth2Authenticator implements AuthenticationEntryPointInterface
{
    use TargetPathTrait;

    public function __construct(
        private readonly ClientRegistry $clientRegistry,
        private readonly EntityManagerInterface $entityManager,
        private readonly RouterInterface $router,
        private readonly UserRepository $userRepository,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return $request->attributes->get('_route') === 'connect_discord_check';
    }

    public function authenticate(Request $request): Passport
    {
        $client = $this->clientRegistry->getClient('discord');
        $accessToken = $this->fetchAccessToken($client);

        return new SelfValidatingPassport(new UserBadge($accessToken->getToken(), function () use ($accessToken, $client): User {
            /** @var DiscordResourceOwner $discordUser */
            $discordUser = $client->fetchUserFromToken($accessToken);
            $discordId = (string) $discordUser->getId();
            if ($discordId === '') {
                throw new AuthenticationException('Discord did not return a user ID.');
            }

            $user = $this->userRepository->findOneBy(['discordId' => $discordId]) ?? new User();
            $user
                ->setDiscordId($discordId)
                ->setDiscordUsername($discordUser->getUsername())
                ->setDiscordAvatarHash($discordUser->getAvatarHash())
                ->setRoles(['ROLE_USER']);

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            return $user;
        }));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $session = $request->getSession();
        $targetPath = $this->getTargetPath($session, $firewallName);
        $this->removeTargetPath($session, $firewallName);

        return new RedirectResponse($targetPath ?? $this->router->generate('app_account'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $request->getSession()->getFlashBag()->add('error', 'Discord login failed. Please try again.');

        return new RedirectResponse($this->router->generate('app_homepage'));
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new RedirectResponse($this->router->generate('connect_discord_start'));
    }
}
