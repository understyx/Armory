<?php

namespace App\Tests\Message;

use App\Message\RefreshCharacterSnapshotMessage;
use App\Message\RefreshUwuRankMessage;
use App\Message\EnrichSpellTooltipMessage;
use App\Service\DeduplicatingMessageDispatcher;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class MessageRoutingTest extends KernelTestCase
{
    public function testRankRefreshUsesUwuAndCharacterRefreshUsesAsync(): void
    {
        $previousEnv = $_ENV['MESSENGER_TRANSPORT_DSN'] ?? null;
        $previousServer = $_SERVER['MESSENGER_TRANSPORT_DSN'] ?? null;
        $previousProcess = getenv('MESSENGER_TRANSPORT_DSN');

        $_ENV['MESSENGER_TRANSPORT_DSN'] = 'in-memory://';
        $_SERVER['MESSENGER_TRANSPORT_DSN'] = 'in-memory://';
        putenv('MESSENGER_TRANSPORT_DSN=in-memory://');

        try {
            self::bootKernel();
            $container = self::getContainer();
            $bus = $container->get('messenger.default_bus');

            DeduplicatingMessageDispatcher::dispatch($bus, new RefreshUwuRankMessage(' Test ', 'Icecrown', '1'));
            DeduplicatingMessageDispatcher::dispatch($bus, new RefreshUwuRankMessage('test', 'icecrown', '1'));
            DeduplicatingMessageDispatcher::dispatch($bus, new RefreshUwuRankMessage('Test', 'Icecrown', '2'));
            DeduplicatingMessageDispatcher::dispatch($bus, new RefreshUwuRankMessage('Other', 'Icecrown', '1'));
            DeduplicatingMessageDispatcher::dispatch($bus, new RefreshUwuRankMessage('Test', 'Lordaeron', '1'));

            DeduplicatingMessageDispatcher::dispatch($bus, new RefreshCharacterSnapshotMessage('Test', 'Icecrown'));
            DeduplicatingMessageDispatcher::dispatch($bus, new RefreshCharacterSnapshotMessage(' test ', 'icecrown'));
            DeduplicatingMessageDispatcher::dispatch($bus, new RefreshCharacterSnapshotMessage('Test', 'Lordaeron'));
            DeduplicatingMessageDispatcher::dispatch($bus, new RefreshCharacterSnapshotMessage('Other', 'Icecrown'));
            DeduplicatingMessageDispatcher::dispatch($bus, new EnrichSpellTooltipMessage(56175));
            DeduplicatingMessageDispatcher::dispatch($bus, new EnrichSpellTooltipMessage(56175));

            $uwuMessages = $container->get('messenger.transport.uwu')->get();
            $asyncMessages = $container->get('messenger.transport.async')->get();

            self::assertCount(4, $uwuMessages);
            self::assertInstanceOf(RefreshUwuRankMessage::class, $uwuMessages[0]->getMessage());
            self::assertCount(4, $asyncMessages);
            self::assertInstanceOf(RefreshCharacterSnapshotMessage::class, $asyncMessages[0]->getMessage());
            self::assertInstanceOf(EnrichSpellTooltipMessage::class, $asyncMessages[3]->getMessage());
        } finally {
            self::ensureKernelShutdown();

            if ($previousEnv === null) {
                unset($_ENV['MESSENGER_TRANSPORT_DSN']);
            } else {
                $_ENV['MESSENGER_TRANSPORT_DSN'] = $previousEnv;
            }
            if ($previousServer === null) {
                unset($_SERVER['MESSENGER_TRANSPORT_DSN']);
            } else {
                $_SERVER['MESSENGER_TRANSPORT_DSN'] = $previousServer;
            }
            putenv($previousProcess === false
                ? 'MESSENGER_TRANSPORT_DSN'
                : 'MESSENGER_TRANSPORT_DSN='.$previousProcess);
        }
    }
}
