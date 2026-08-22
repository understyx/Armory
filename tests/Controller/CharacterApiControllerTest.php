<?php

namespace App\Tests\Controller;

use App\Controller\CharacterApiController;
use App\Entity\CharacterSnapshot;
use App\Message\RefreshCharacterSnapshotMessage;
use App\Repository\CharacterSnapshotRepository;
use App\Service\CharacterApiFormatter;
use App\Service\CharacterUpdateThrottle;
use App\Service\CharacterUpdateThrottleDecision;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class CharacterApiControllerTest extends TestCase
{
    public function testCharacterReturnsCachedSnapshotWithoutRequestingAnUpdate(): void
    {
        $snapshot = $this->createSnapshot();
        $repository = $this->createMock(CharacterSnapshotRepository::class);
        $repository->expects(self::once())
            ->method('findByNameAndRealm')
            ->with('Understyx', 'Icecrown')
            ->willReturn($snapshot);
        $throttle = $this->createMock(CharacterUpdateThrottle::class);
        $throttle->expects(self::never())->method('claim');
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $response = (new CharacterApiController(
            $repository,
            new CharacterApiFormatter(),
            $throttle,
            $bus,
        ))->character('Understyx', 'Icecrown');
        $payload = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('Understyx', $payload['character']['name']);
        self::assertStringContainsString('public', (string) $response->headers->get('Cache-Control'));
        self::assertStringContainsString('max-age=60', (string) $response->headers->get('Cache-Control'));
    }

    public function testCharacterReturnsNotFoundWhenThereIsNoSnapshot(): void
    {
        $repository = $this->createMock(CharacterSnapshotRepository::class);
        $repository->method('findByNameAndRealm')->willReturn(null);

        $response = $this->createController($repository)->character('Missing', 'Icecrown');
        $payload = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        self::assertSame('not_found', $payload['status']);
    }

    public function testRequestUpdateQueuesARefresh(): void
    {
        $nextAllowedAt = new \DateTimeImmutable('+5 minutes');
        $throttle = $this->createMock(CharacterUpdateThrottle::class);
        $throttle->expects(self::once())
            ->method('claim')
            ->with('Understyx', 'Icecrown')
            ->willReturn(new CharacterUpdateThrottleDecision(true, $nextAllowedAt));
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static fn(object $message): bool => $message instanceof RefreshCharacterSnapshotMessage
                && $message->characterName === 'Understyx'
                && $message->realmName === 'Icecrown'))
            ->willReturnCallback(static fn(object $message): Envelope => new Envelope($message));

        $response = $this->createController(throttle: $throttle, bus: $bus)
            ->requestUpdate('Understyx', 'Icecrown');
        $payload = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(Response::HTTP_ACCEPTED, $response->getStatusCode());
        self::assertSame('queued', $payload['status']);
        self::assertSame($nextAllowedAt->format(\DateTimeInterface::ATOM), $payload['nextUpdateAllowedAt']);
    }

    public function testRequestUpdateReturns429DuringCooldown(): void
    {
        $nextAllowedAt = new \DateTimeImmutable('+2 minutes');
        $throttle = $this->createMock(CharacterUpdateThrottle::class);
        $throttle->method('claim')
            ->willReturn(new CharacterUpdateThrottleDecision(false, $nextAllowedAt));
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $response = $this->createController(throttle: $throttle, bus: $bus)
            ->requestUpdate('Understyx', 'Icecrown');
        $payload = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(Response::HTTP_TOO_MANY_REQUESTS, $response->getStatusCode());
        self::assertSame('rate_limited', $payload['status']);
        self::assertGreaterThanOrEqual(118, (int) $response->headers->get('Retry-After'));
        self::assertLessThanOrEqual(120, (int) $response->headers->get('Retry-After'));
    }

    public function testInvalidIdentifiersAreRejectedBeforeDatabaseAccess(): void
    {
        $repository = $this->createMock(CharacterSnapshotRepository::class);
        $repository->expects(self::never())->method('findByNameAndRealm');

        $response = $this->createController($repository)->character('bad name', 'Icecrown');

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    private function createController(
        ?CharacterSnapshotRepository $repository = null,
        ?CharacterUpdateThrottle $throttle = null,
        ?MessageBusInterface $bus = null,
    ): CharacterApiController {
        return new CharacterApiController(
            $repository ?? $this->createMock(CharacterSnapshotRepository::class),
            new CharacterApiFormatter(),
            $throttle ?? $this->createMock(CharacterUpdateThrottle::class),
            $bus ?? $this->createMock(MessageBusInterface::class),
        );
    }

    private function createSnapshot(): CharacterSnapshot
    {
        return (new CharacterSnapshot())
            ->setName('Understyx')
            ->setRealm('Icecrown')
            ->setLevel(80)
            ->setRace('Human')
            ->setClass('Death Knight')
            ->setGearScore(6000)
            ->setAvgIlvl(264.5)
            ->setProfessions([])
            ->setSpecializations([])
            ->setEquippedItems([])
            ->setTalentStrings([])
            ->setScrapedAt(new \DateTimeImmutable('2026-08-23T10:00:00+00:00'));
    }
}
