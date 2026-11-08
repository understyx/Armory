<?php

namespace App\Http;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ConditionalResponse
{
    /**
     * Add a representation validator and return 304 when the current GET
     * representation matches the client's validator.
     *
     * Source hashes make template and controller deployments invalidate the
     * validator even when the new code happens to produce the same body.
     * Snapshot, rank and tooltip changes are represented in the rendered body.
     *
     * @param list<string> $sourceFiles
     */
    public static function apply(
        Response $response,
        Request $request,
        array $sourceFiles = [],
        int $maxAge = 0,
        int $sharedMaxAge = 60,
        bool $sessionIndependent = false,
        bool $userSpecific = false,
    ): Response
    {
        if ($userSpecific || (!$sessionIndependent && self::hasSessionContent($request))) {
            $response->setPrivate();
            $response->setMaxAge(0);
            $response->headers->addCacheControlDirective('no-store');

            return $response;
        }

        $representation = (string) $response->getContent();
        foreach ($sourceFiles as $sourceFile) {
            if (is_file($sourceFile)) {
                $representation .= "\0".hash_file('sha256', $sourceFile);
            }
        }

        $response->setPublic();
        $response->setMaxAge($maxAge);
        $response->setSharedMaxAge($sharedMaxAge);
        // Nginx can gzip the response, so this is a weak representation validator.
        $response->setEtag(hash('sha256', $representation), true);
        $response->isNotModified($request);

        return $response;
    }

    /** @return array<string, list<string>> */
    public static function flashes(Request $request): array
    {
        return $request->hasPreviousSession()
            ? $request->getSession()->getFlashBag()->all()
            : [];
    }

    private static function hasSessionContent(Request $request): bool
    {
        if ($request->hasPreviousSession()) {
            return true;
        }

        return $request->hasSession() && $request->getSession()->isStarted();
    }
}
