<?php

namespace App\Tests\Http;

use App\Http\ConditionalResponse;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

final class ConditionalResponseTest extends TestCase
{
    public function testExistingSessionKeepsResponsePrivateAndSkipsNotModified(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $request = new Request(server: ['HTTP_IF_NONE_MATCH' => '"previous-validator"']);
        $request->setSession($session);
        $request->cookies->set($session->getName(), 'existing-session');
        $response = new Response('content that may contain user-specific data');

        ConditionalResponse::apply($response, $request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertNull($response->headers->get('ETag'));
        self::assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        self::assertSame('content that may contain user-specific data', $response->getContent());
    }

    public function testSessionStartedDuringRequestKeepsResponsePrivate(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $session->start();
        $request = new Request(server: ['HTTP_IF_NONE_MATCH' => '"previous-validator"']);
        $request->setSession($session);
        $response = new Response('content rendered after session start');

        ConditionalResponse::apply($response, $request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertNull($response->headers->get('ETag'));
        self::assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function testUserSpecificRepresentationIsPrivateWithoutAConditionalValidator(): void
    {
        $request = new Request(server: ['HTTP_IF_NONE_MATCH' => '"previous-validator"']);
        $response = new Response('a response containing account identity');

        ConditionalResponse::apply($response, $request, userSpecific: true);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertNull($response->headers->get('ETag'));
        self::assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }
}
