<?php

namespace App\Service;

use App\Entity\CharacterSnapshot;

final readonly class CharacterRefreshResult
{
    public const UPDATED = 'updated';
    public const UNCHANGED = 'unchanged';
    public const NOT_FOUND = 'not_found';
    public const SOURCE_UNAVAILABLE = 'source_unavailable';

    public function __construct(
        public string $status,
        public ?CharacterSnapshot $snapshot = null,
    ) {
    }
}
