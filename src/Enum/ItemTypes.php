<?php

namespace App\Enum;

enum ItemTypes: int
{
    case HEAD = 1;
    case NECK = 2;
    case SHOULDER = 3;
    case SHIRT = 4;
    case CHEST = 5;
    case WAIST = 6;
    case LEGS = 7;
    case FEET = 8;
    case WRIST = 9;
    case GLOVES = 10;
    case RING = 11;
    case TRINKET = 12;
    case WEAPON_1H = 13;
    case SHIELD = 14;
    case BOW = 15;
    case BACK = 16;
    case WEAPON_2H = 17;
    case TABARD = 19;
    case ROBE = 20;
    case WEAPON_MAINHAND = 21;
    case WEAPON_OFFHAND = 22;
    case OFF_HAND = 23;
    case THROWN = 25;
    case RANGED = 26;
    case RELIC = 28;

    public function getName(): string
    {
        return match($this) {
            self::HEAD => "Head",
            self::NECK => "Neck",
            self::SHOULDER => "Shoulders",
            self::BACK => "Back",
            self::CHEST, self::ROBE => "Chest",
            self::SHIRT => "Shirt",
            self::TABARD => "Tabard",
            self::WRIST => "Wrist",
            self::GLOVES => "Hands",
            self::WAIST => "Belt",
            self::LEGS => "Legs",
            self::FEET => "Feet",
            self::RING => "Ring",
            self::TRINKET => "Trinket",
            self::WEAPON_2H => "2H Weapon",
            self::WEAPON_1H => "1H Weapon",
            self::WEAPON_MAINHAND => "Main Hand Weapon",
            self::WEAPON_OFFHAND => "Off Hand Weapon",
            self::SHIELD => "Shield",
            self::OFF_HAND => "Off-Hand",
            self::BOW, self::THROWN, self::RANGED => "Ranged",
            self::RELIC => "Relic",
        };
    }
}