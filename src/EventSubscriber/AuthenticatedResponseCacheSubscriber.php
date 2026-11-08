<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final readonly class AuthenticatedResponseCacheSubscriber implements EventSubscriberInterface
{
    public function __construct(private TokenStorageInterface $tokenStorage)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::RESPONSE => ['markAuthenticatedResponsesPrivate', -128]];
    }

    public function markAuthenticatedResponsesPrivate(ResponseEvent $event): void
    {
        if (!$event->isMainRequest() || !$event->getRequest()->hasPreviousSession()) {
            return;
        }

        $user = $this->tokenStorage->getToken()?->getUser();
        if (!$user instanceof UserInterface) {
            return;
        }

        $response = $event->getResponse();
        $response->setPrivate();
        $response->setMaxAge(0);
        $response->headers->addCacheControlDirective('no-store');
    }
}
