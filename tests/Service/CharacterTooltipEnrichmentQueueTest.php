<?php

namespace App\Tests\Service;

use App\Entity\CharacterSnapshot;
use App\Message\EnrichSpellTooltipMessage;
use App\Repository\TooltipEnrichmentRepository;
use App\Repository\WowItemRepository;
use App\Repository\WowSpellTooltipRepository;
use App\Service\CharacterTooltipEnrichmentQueue;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class CharacterTooltipEnrichmentQueueTest extends TestCase
{
    public function testQueuesEachMissingSpellOnlyOnce(): void
    {
        $spellRepository = $this->createMock(WowSpellTooltipRepository::class);
        $calls = [];
        $spellRepository->expects(self::once())->method('findQueueableSpellIds')->with([123])->willReturn([123]);
        $spellRepository->expects(self::once())->method('markQueued')->with(123)
            ->willReturnCallback(static function () use (&$calls): void {
                $calls[] = 'marked';
            });

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static fn (object $message): bool => $message instanceof EnrichSpellTooltipMessage
                && $message->spellId === 123), self::isArray())
            ->willReturnCallback(static function (object $message) use (&$calls): Envelope {
                $calls[] = 'dispatched';

                return new Envelope($message);
            });

        $queue = new CharacterTooltipEnrichmentQueue(
            $spellRepository,
            $this->createMock(WowItemRepository::class),
            $this->createMock(TooltipEnrichmentRepository::class),
            $bus,
        );

        $snapshot = (new CharacterSnapshot())->setTalentTreesData(['0' => [['tiers' => [[['spellId' => 123], ['spellId' => '123']]]]]]);

        self::assertSame(1, $queue->queueAfterRefresh($snapshot));
        self::assertSame(['marked', 'dispatched'], $calls);
    }
}
