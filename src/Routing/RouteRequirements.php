<?php

namespace App\Routing;

/**
 * Route parameter requirements shared by every controller route (see config/routes.yaml).
 */
final class RouteRequirements
{
    public const REALMS = ['Icecrown', 'Lordaeron', 'Onyxia', 'Blackrock'];

    public const REALM = '(?i:Icecrown|Lordaeron|Onyxia|Blackrock)';

    public const CHARACTER_NAME = '[A-Za-z]{1,12}';

    public static function isCharacterName(string $name): bool
    {
        return preg_match('/^'.self::CHARACTER_NAME.'$/D', $name) === 1;
    }
}
