<?php

namespace App\Service;

use App\Message\EnrichItemTooltipMessage;
use App\Message\RefreshCharacterSnapshotMessage;
use App\Message\RefreshUwuRankMessage;
use Symfony\Component\Lock\Exception\LockAcquiringException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DeduplicateStamp;
use Symfony\Component\Messenger\Stamp\DelayStamp;

final class DeduplicatingMessageDispatcher
{
    /** @param list<object> $stamps */
    public static function dispatch(MessageBusInterface $messageBus, object $message, array $stamps = []): Envelope
    {
        [$key, $ttl] = match (true) {
            $message instanceof RefreshCharacterSnapshotMessage => [
                self::key('character-refresh', $message->realmName, $message->characterName),
                3600.0,
            ],
            $message instanceof RefreshUwuRankMessage => [
                self::key('uwu-rank', $message->realmName, $message->characterName, $message->spec),
                3600.0 + (int) ceil(self::delayMilliseconds($stamps) / 1000),
            ],
            $message instanceof EnrichItemTooltipMessage => [
                'tooltip:'.$message->itemId.':'.hash('sha256', implode("\0", [
                    self::normalize((string) $message->setId),
                    self::normalize($message->locale),
                ])),
                3600.0,
            ],
            default => throw new \InvalidArgumentException(sprintf('No deduplication key is defined for %s.', $message::class)),
        };

        $stamps[] = new DeduplicateStamp($key, $ttl, onlyDeduplicateInQueue: true);

        try {
            return $messageBus->dispatch($message, $stamps);
        } catch (LockAcquiringException) {
            // Keep refresh requests usable if the lock store alone is unavailable.
            $stamps = array_values(array_filter(
                $stamps,
                static fn (object $stamp): bool => !$stamp instanceof DeduplicateStamp,
            ));

            return $messageBus->dispatch($message, $stamps);
        }
    }

    private static function key(string $prefix, string ...$parts): string
    {
        $normalizedParts = array_map(self::normalize(...), $parts);

        return $prefix.':'.hash('sha256', implode("\0", $normalizedParts));
    }

    private static function normalize(string $value): string
    {
        return mb_strtolower(trim($value));
    }

    /** @param list<object> $stamps */
    private static function delayMilliseconds(array $stamps): int
    {
        $delay = 0;
        foreach ($stamps as $stamp) {
            if ($stamp instanceof DelayStamp) {
                $delay = max(0, $stamp->getDelay());
            }
        }

        return $delay;
    }
}
