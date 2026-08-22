<?php

namespace App\Tests\MessageHandler;

use App\Message\RefreshCharacterSnapshotMessage;
use App\MessageHandler\RefreshCharacterSnapshotMessageHandler;
use App\Service\CharacterRefreshResult;
use App\Service\CharacterSnapshotUpdater;
use PHPUnit\Framework\TestCase;

class RefreshCharacterSnapshotMessageHandlerTest extends TestCase
{
    public function testItRefreshesTheRequestedCharacter(): void
    {
        $updater = $this->createMock(CharacterSnapshotUpdater::class);
        $updater->expects(self::once())
            ->method('refresh')
            ->with('Understyx', 'Icecrown')
            ->willReturn(new CharacterRefreshResult(CharacterRefreshResult::UPDATED));

        (new RefreshCharacterSnapshotMessageHandler($updater))(
            new RefreshCharacterSnapshotMessage('Understyx', 'Icecrown'),
        );
    }
}
