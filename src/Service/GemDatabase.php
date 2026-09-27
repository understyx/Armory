<?php

namespace App\Service;

class GemDatabase
{
    /**
     * Maps Gem Enchantment ID => [item_id, name, icon, quality, spell_id, craft_spell_id, effect_spell_id]
     */
    public const GEMS = [
        2787 => ["item_id" => 41386, "name" => "Spiked Titansteel Helm", "icon" => "inv_helmet_134", "quality" => 4, "spell_id" => 55372, "craft_spell_id" => 55372, "effect_spell_id" => null],
        2843 => ["item_id" => 41388, "name" => "Brilliant Titansteel Helm", "icon" => "inv_helmet_134", "quality" => 4, "spell_id" => 55374, "craft_spell_id" => 55374, "effect_spell_id" => null],
        3302 => ["item_id" => 41387, "name" => "Tempered Titansteel Helm", "icon" => "inv_helmet_134", "quality" => 4, "spell_id" => 55373, "craft_spell_id" => 55373, "effect_spell_id" => null],
        3371 => ["item_id" => 39900, "name" => "Bold Bloodstone", "icon" => "inv_jewelcrafting_gem_22", "quality" => 2, "spell_id" => 53831, "craft_spell_id" => 53831, "effect_spell_id" => null],
        3374 => ["item_id" => 39905, "name" => "Delicate Bloodstone", "icon" => "inv_jewelcrafting_gem_22", "quality" => 2, "spell_id" => 53832, "craft_spell_id" => 53832, "effect_spell_id" => null],
        3375 => ["item_id" => 39906, "name" => "Bright Bloodstone", "icon" => "inv_jewelcrafting_gem_22", "quality" => 2, "spell_id" => 53835, "craft_spell_id" => 53835, "effect_spell_id" => null],
        3376 => ["item_id" => 39907, "name" => "Subtle Bloodstone", "icon" => "inv_jewelcrafting_gem_22", "quality" => 2, "spell_id" => 53843, "craft_spell_id" => 53843, "effect_spell_id" => null],
        3377 => ["item_id" => 39908, "name" => "Flashing Bloodstone", "icon" => "inv_jewelcrafting_gem_22", "quality" => 2, "spell_id" => 53844, "craft_spell_id" => 53844, "effect_spell_id" => null],
        3378 => ["item_id" => 39909, "name" => "Fractured Bloodstone", "icon" => "inv_jewelcrafting_gem_22", "quality" => 2, "spell_id" => 53845, "craft_spell_id" => 53845, "effect_spell_id" => null],
        3379 => ["item_id" => 39910, "name" => "Precise Bloodstone", "icon" => "inv_jewelcrafting_gem_22", "quality" => 2, "spell_id" => 54017, "craft_spell_id" => 54017, "effect_spell_id" => null],
        3380 => ["item_id" => 39911, "name" => "Runed Bloodstone", "icon" => "inv_jewelcrafting_gem_22", "quality" => 2, "spell_id" => 53834, "craft_spell_id" => 53834, "effect_spell_id" => null],
        3446 => ["item_id" => 39996, "name" => "Bold Scarlet Ruby", "icon" => "inv_jewelcrafting_gem_28", "quality" => 3, "spell_id" => 53830, "craft_spell_id" => 53830, "effect_spell_id" => null],
        3447 => ["item_id" => 39997, "name" => "Delicate Scarlet Ruby", "icon" => "inv_jewelcrafting_gem_28", "quality" => 3, "spell_id" => 53945, "craft_spell_id" => 53945, "effect_spell_id" => null],
        3448 => ["item_id" => 39998, "name" => "Runed Scarlet Ruby", "icon" => "inv_jewelcrafting_gem_28", "quality" => 3, "spell_id" => 53946, "craft_spell_id" => 53946, "effect_spell_id" => null],
        3449 => ["item_id" => 39999, "name" => "Bright Scarlet Ruby", "icon" => "inv_jewelcrafting_gem_28", "quality" => 3, "spell_id" => 53947, "craft_spell_id" => 53947, "effect_spell_id" => null],
        3450 => ["item_id" => 40000, "name" => "Subtle Scarlet Ruby", "icon" => "inv_jewelcrafting_gem_28", "quality" => 3, "spell_id" => 53948, "craft_spell_id" => 53948, "effect_spell_id" => null],
        3451 => ["item_id" => 40001, "name" => "Flashing Scarlet Ruby", "icon" => "inv_jewelcrafting_gem_28", "quality" => 3, "spell_id" => 53949, "craft_spell_id" => 53949, "effect_spell_id" => null],
        3452 => ["item_id" => 40002, "name" => "Fractured Scarlet Ruby", "icon" => "inv_jewelcrafting_gem_28", "quality" => 3, "spell_id" => 53950, "craft_spell_id" => 53950, "effect_spell_id" => null],
        3453 => ["item_id" => 40003, "name" => "Precise Scarlet Ruby", "icon" => "inv_jewelcrafting_gem_28", "quality" => 3, "spell_id" => 53951, "craft_spell_id" => 53951, "effect_spell_id" => null],
        3454 => ["item_id" => 40008, "name" => "Solid Sky Sapphire", "icon" => "inv_jewelcrafting_gem_27", "quality" => 3, "spell_id" => 53952, "craft_spell_id" => 53952, "effect_spell_id" => null],
        3455 => ["item_id" => 40009, "name" => "Sparkling Sky Sapphire", "icon" => "inv_jewelcrafting_gem_27", "quality" => 3, "spell_id" => 53953, "craft_spell_id" => 53953, "effect_spell_id" => null],
        3456 => ["item_id" => 40010, "name" => "Lustrous Sky Sapphire", "icon" => "inv_jewelcrafting_gem_27", "quality" => 3, "spell_id" => 53954, "craft_spell_id" => 53954, "effect_spell_id" => null],
        3457 => ["item_id" => 40011, "name" => "Stormy Sky Sapphire", "icon" => "inv_jewelcrafting_gem_27", "quality" => 3, "spell_id" => 26283, "craft_spell_id" => 53955, "effect_spell_id" => 26283],
        3458 => ["item_id" => 40012, "name" => "Brilliant Autumn\'s Glow", "icon" => "inv_jewelcrafting_gem_26", "quality" => 3, "spell_id" => 53956, "craft_spell_id" => 53956, "effect_spell_id" => null],
        3459 => ["item_id" => 40013, "name" => "Smooth Autumn\'s Glow", "icon" => "inv_jewelcrafting_gem_26", "quality" => 3, "spell_id" => 53957, "craft_spell_id" => 53957, "effect_spell_id" => null],
        3460 => ["item_id" => 40014, "name" => "Rigid Autumn\'s Glow", "icon" => "inv_jewelcrafting_gem_26", "quality" => 3, "spell_id" => 53958, "craft_spell_id" => 53958, "effect_spell_id" => null],
        3461 => ["item_id" => 40015, "name" => "Thick Autumn\'s Glow", "icon" => "inv_jewelcrafting_gem_26", "quality" => 3, "spell_id" => 53959, "craft_spell_id" => 53959, "effect_spell_id" => null],
        3462 => ["item_id" => 40016, "name" => "Mystic Autumn\'s Glow", "icon" => "inv_jewelcrafting_gem_26", "quality" => 3, "spell_id" => 53960, "craft_spell_id" => 53960, "effect_spell_id" => null],
        3463 => ["item_id" => 40017, "name" => "Quick Autumn\'s Glow", "icon" => "inv_jewelcrafting_gem_26", "quality" => 3, "spell_id" => 53961, "craft_spell_id" => 53961, "effect_spell_id" => null],
        3464 => ["item_id" => 40022, "name" => "Sovereign Twilight Opal", "icon" => "inv_jewelcrafting_gem_29", "quality" => 3, "spell_id" => 53962, "craft_spell_id" => 53962, "effect_spell_id" => null],
        3465 => ["item_id" => 40023, "name" => "Shifting Twilight Opal", "icon" => "inv_jewelcrafting_gem_29", "quality" => 3, "spell_id" => 53963, "craft_spell_id" => 53963, "effect_spell_id" => null],
        3466 => ["item_id" => 40025, "name" => "Glowing Twilight Opal", "icon" => "inv_jewelcrafting_gem_29", "quality" => 3, "spell_id" => 53965, "craft_spell_id" => 53965, "effect_spell_id" => null],
        3467 => ["item_id" => 40029, "name" => "Balanced Twilight Opal", "icon" => "inv_jewelcrafting_gem_29", "quality" => 3, "spell_id" => 53969, "craft_spell_id" => 53969, "effect_spell_id" => null],
        3468 => ["item_id" => 40031, "name" => "Regal Twilight Opal", "icon" => "inv_jewelcrafting_gem_29", "quality" => 3, "spell_id" => 53971, "craft_spell_id" => 53971, "effect_spell_id" => null],
        3469 => ["item_id" => 40032, "name" => "Defender\'s Twilight Opal", "icon" => "inv_jewelcrafting_gem_29", "quality" => 3, "spell_id" => 53972, "craft_spell_id" => 53972, "effect_spell_id" => null],
        3470 => ["item_id" => 40033, "name" => "Puissant Twilight Opal", "icon" => "inv_jewelcrafting_gem_29", "quality" => 3, "spell_id" => 53973, "craft_spell_id" => 53973, "effect_spell_id" => null],
        3471 => ["item_id" => 40034, "name" => "Guardian\'s Twilight Opal", "icon" => "inv_jewelcrafting_gem_29", "quality" => 3, "spell_id" => 53974, "craft_spell_id" => 53974, "effect_spell_id" => null],
        3472 => ["item_id" => 40026, "name" => "Purified Twilight Opal", "icon" => "inv_jewelcrafting_gem_29", "quality" => 3, "spell_id" => 53966, "craft_spell_id" => 53966, "effect_spell_id" => null],
        3473 => ["item_id" => 40027, "name" => "Royal Twilight Opal", "icon" => "inv_jewelcrafting_gem_29", "quality" => 3, "spell_id" => 53967, "craft_spell_id" => 53967, "effect_spell_id" => null],
        3474 => ["item_id" => 40024, "name" => "Tenuous Twilight Opal", "icon" => "inv_jewelcrafting_gem_29", "quality" => 3, "spell_id" => 53964, "craft_spell_id" => 53964, "effect_spell_id" => null],
        3475 => ["item_id" => 40030, "name" => "Infused Twilight Opal", "icon" => "inv_jewelcrafting_gem_29", "quality" => 3, "spell_id" => 53970, "craft_spell_id" => 53970, "effect_spell_id" => null],
        3476 => ["item_id" => 40028, "name" => "Mysterious Twilight Opal", "icon" => "inv_jewelcrafting_gem_29", "quality" => 3, "spell_id" => 25975, "craft_spell_id" => 53968, "effect_spell_id" => 25975],
        3477 => ["item_id" => 40037, "name" => "Inscribed Monarch Topaz", "icon" => "inv_jewelcrafting_gem_30", "quality" => 3, "spell_id" => 53975, "craft_spell_id" => 53975, "effect_spell_id" => null],
        3478 => ["item_id" => 40038, "name" => "Etched Monarch Topaz", "icon" => "inv_jewelcrafting_gem_30", "quality" => 3, "spell_id" => 53976, "craft_spell_id" => 53976, "effect_spell_id" => null],
        3479 => ["item_id" => 40039, "name" => "Champion\'s Monarch Topaz", "icon" => "inv_jewelcrafting_gem_30", "quality" => 3, "spell_id" => 53977, "craft_spell_id" => 53977, "effect_spell_id" => null],
        3480 => ["item_id" => 40040, "name" => "Resplendent Monarch Topaz", "icon" => "inv_jewelcrafting_gem_30", "quality" => 3, "spell_id" => 53978, "craft_spell_id" => 53978, "effect_spell_id" => null],
        3481 => ["item_id" => 40041, "name" => "Fierce Monarch Topaz", "icon" => "inv_jewelcrafting_gem_30", "quality" => 3, "spell_id" => 54019, "craft_spell_id" => 54019, "effect_spell_id" => null],
        3482 => ["item_id" => 40043, "name" => "Deadly Monarch Topaz", "icon" => "inv_jewelcrafting_gem_30", "quality" => 3, "spell_id" => 53979, "craft_spell_id" => 53979, "effect_spell_id" => null],
        3483 => ["item_id" => 40044, "name" => "Glinting Monarch Topaz", "icon" => "inv_jewelcrafting_gem_30", "quality" => 3, "spell_id" => 53980, "craft_spell_id" => 53980, "effect_spell_id" => null],
        3484 => ["item_id" => 40045, "name" => "Lucent Monarch Topaz", "icon" => "inv_jewelcrafting_gem_30", "quality" => 3, "spell_id" => 53981, "craft_spell_id" => 53981, "effect_spell_id" => null],
        3485 => ["item_id" => 40046, "name" => "Deft Monarch Topaz", "icon" => "inv_jewelcrafting_gem_30", "quality" => 3, "spell_id" => 53982, "craft_spell_id" => 53982, "effect_spell_id" => null],
        3486 => ["item_id" => 40047, "name" => "Luminous Monarch Topaz", "icon" => "inv_jewelcrafting_gem_30", "quality" => 3, "spell_id" => 53983, "craft_spell_id" => 53983, "effect_spell_id" => null],
        3487 => ["item_id" => 40048, "name" => "Potent Monarch Topaz", "icon" => "inv_jewelcrafting_gem_30", "quality" => 3, "spell_id" => 53984, "craft_spell_id" => 53984, "effect_spell_id" => null],
        3488 => ["item_id" => 40049, "name" => "Veiled Monarch Topaz", "icon" => "inv_jewelcrafting_gem_30", "quality" => 3, "spell_id" => 53985, "craft_spell_id" => 53985, "effect_spell_id" => null],
        3489 => ["item_id" => 40050, "name" => "Durable Monarch Topaz", "icon" => "inv_jewelcrafting_gem_30", "quality" => 3, "spell_id" => 53986, "craft_spell_id" => 53986, "effect_spell_id" => null],
        3490 => ["item_id" => 40051, "name" => "Reckless Monarch Topaz", "icon" => "inv_jewelcrafting_gem_30", "quality" => 3, "spell_id" => 53987, "craft_spell_id" => 53987, "effect_spell_id" => null],
        3491 => ["item_id" => 40052, "name" => "Wicked Monarch Topaz", "icon" => "inv_jewelcrafting_gem_30", "quality" => 3, "spell_id" => 53988, "craft_spell_id" => 53988, "effect_spell_id" => null],
        3492 => ["item_id" => 40053, "name" => "Pristine Monarch Topaz", "icon" => "inv_jewelcrafting_gem_30", "quality" => 3, "spell_id" => 53989, "craft_spell_id" => 53989, "effect_spell_id" => null],
        3493 => ["item_id" => 40054, "name" => "Empowered Monarch Topaz", "icon" => "inv_jewelcrafting_gem_30", "quality" => 3, "spell_id" => 53990, "craft_spell_id" => 53990, "effect_spell_id" => null],
        3494 => ["item_id" => 40055, "name" => "Stark Monarch Topaz", "icon" => "inv_jewelcrafting_gem_30", "quality" => 3, "spell_id" => 53991, "craft_spell_id" => 53991, "effect_spell_id" => null],
        3495 => ["item_id" => 40056, "name" => "Stalwart Monarch Topaz", "icon" => "inv_jewelcrafting_gem_30", "quality" => 3, "spell_id" => 53992, "craft_spell_id" => 53992, "effect_spell_id" => null],
        3496 => ["item_id" => 40057, "name" => "Glimmering Monarch Topaz", "icon" => "inv_jewelcrafting_gem_30", "quality" => 3, "spell_id" => 53993, "craft_spell_id" => 53993, "effect_spell_id" => null],
        3497 => ["item_id" => 40058, "name" => "Accurate Monarch Topaz", "icon" => "inv_jewelcrafting_gem_30", "quality" => 3, "spell_id" => 53994, "craft_spell_id" => 53994, "effect_spell_id" => null],
        3498 => ["item_id" => 40059, "name" => "Resolute Monarch Topaz", "icon" => "inv_jewelcrafting_gem_30", "quality" => 3, "spell_id" => 54023, "craft_spell_id" => 54023, "effect_spell_id" => null],
        3518 => ["item_id" => 40111, "name" => "Bold Cardinal Ruby", "icon" => "inv_jewelcrafting_gem_37", "quality" => 4, "spell_id" => 66447, "craft_spell_id" => 66447, "effect_spell_id" => null],
        3519 => ["item_id" => 40112, "name" => "Delicate Cardinal Ruby", "icon" => "inv_jewelcrafting_gem_37", "quality" => 4, "spell_id" => 66448, "craft_spell_id" => 66448, "effect_spell_id" => null],
        3520 => ["item_id" => 40113, "name" => "Runed Cardinal Ruby", "icon" => "inv_jewelcrafting_gem_37", "quality" => 4, "spell_id" => 66446, "craft_spell_id" => 66446, "effect_spell_id" => null],
        3521 => ["item_id" => 40114, "name" => "Bright Cardinal Ruby", "icon" => "inv_jewelcrafting_gem_37", "quality" => 4, "spell_id" => 66449, "craft_spell_id" => 66449, "effect_spell_id" => null],
        3522 => ["item_id" => 40115, "name" => "Subtle Cardinal Ruby", "icon" => "inv_jewelcrafting_gem_37", "quality" => 4, "spell_id" => 66452, "craft_spell_id" => 66452, "effect_spell_id" => null],
        3523 => ["item_id" => 40116, "name" => "Flashing Cardinal Ruby", "icon" => "inv_jewelcrafting_gem_37", "quality" => 4, "spell_id" => 66453, "craft_spell_id" => 66453, "effect_spell_id" => null],
        3524 => ["item_id" => 40118, "name" => "Precise Cardinal Ruby", "icon" => "inv_jewelcrafting_gem_37", "quality" => 4, "spell_id" => 66450, "craft_spell_id" => 66450, "effect_spell_id" => null],
        3525 => ["item_id" => 40117, "name" => "Fractured Cardinal Ruby", "icon" => "inv_jewelcrafting_gem_37", "quality" => 4, "spell_id" => 66451, "craft_spell_id" => 66451, "effect_spell_id" => null],
        3526 => ["item_id" => 40123, "name" => "Brilliant King\'s Amber", "icon" => "inv_jewelcrafting_gem_38", "quality" => 4, "spell_id" => 66503, "craft_spell_id" => 66503, "effect_spell_id" => null],
        3527 => ["item_id" => 40124, "name" => "Smooth King\'s Amber", "icon" => "inv_jewelcrafting_gem_38", "quality" => 4, "spell_id" => 66502, "craft_spell_id" => 66502, "effect_spell_id" => null],
        3528 => ["item_id" => 40125, "name" => "Rigid King\'s Amber", "icon" => "inv_jewelcrafting_gem_38", "quality" => 4, "spell_id" => 66501, "craft_spell_id" => 66501, "effect_spell_id" => null],
        3529 => ["item_id" => 40126, "name" => "Thick King\'s Amber", "icon" => "inv_jewelcrafting_gem_38", "quality" => 4, "spell_id" => 66504, "craft_spell_id" => 66504, "effect_spell_id" => null],
        3530 => ["item_id" => 40127, "name" => "Mystic King\'s Amber", "icon" => "inv_jewelcrafting_gem_38", "quality" => 4, "spell_id" => 66505, "craft_spell_id" => 66505, "effect_spell_id" => null],
        3531 => ["item_id" => 40128, "name" => "Quick King\'s Amber", "icon" => "inv_jewelcrafting_gem_38", "quality" => 4, "spell_id" => 66506, "craft_spell_id" => 66506, "effect_spell_id" => null],
        3532 => ["item_id" => 40119, "name" => "Solid Majestic Zircon", "icon" => "inv_jewelcrafting_gem_42", "quality" => 4, "spell_id" => 66497, "craft_spell_id" => 66497, "effect_spell_id" => null],
        3533 => ["item_id" => 40120, "name" => "Sparkling Majestic Zircon", "icon" => "inv_jewelcrafting_gem_42", "quality" => 4, "spell_id" => 66498, "craft_spell_id" => 66498, "effect_spell_id" => null],
        3534 => ["item_id" => 40121, "name" => "Lustrous Majestic Zircon", "icon" => "inv_jewelcrafting_gem_42", "quality" => 4, "spell_id" => 66500, "craft_spell_id" => 66500, "effect_spell_id" => null],
        3535 => ["item_id" => 40122, "name" => "Stormy Majestic Zircon", "icon" => "inv_jewelcrafting_gem_42", "quality" => 4, "spell_id" => 28799, "craft_spell_id" => 66499, "effect_spell_id" => 28799],
        3536 => ["item_id" => 40129, "name" => "Sovereign Dreadstone", "icon" => "inv_jewelcrafting_gem_40", "quality" => 4, "spell_id" => 66554, "craft_spell_id" => 66554, "effect_spell_id" => null],
        3537 => ["item_id" => 40130, "name" => "Shifting Dreadstone", "icon" => "inv_jewelcrafting_gem_40", "quality" => 4, "spell_id" => 66557, "craft_spell_id" => 66557, "effect_spell_id" => null],
        3538 => ["item_id" => 40132, "name" => "Glowing Dreadstone", "icon" => "inv_jewelcrafting_gem_40", "quality" => 4, "spell_id" => 66555, "craft_spell_id" => 66555, "effect_spell_id" => null],
        3539 => ["item_id" => 40136, "name" => "Balanced Dreadstone", "icon" => "inv_jewelcrafting_gem_40", "quality" => 4, "spell_id" => 66553, "craft_spell_id" => 66553, "effect_spell_id" => null],
        3540 => ["item_id" => 40138, "name" => "Regal Dreadstone", "icon" => "inv_jewelcrafting_gem_40", "quality" => 4, "spell_id" => 66559, "craft_spell_id" => 66559, "effect_spell_id" => null],
        3541 => ["item_id" => 40139, "name" => "Defender\'s Dreadstone", "icon" => "inv_jewelcrafting_gem_40", "quality" => 4, "spell_id" => 66560, "craft_spell_id" => 66560, "effect_spell_id" => null],
        3542 => ["item_id" => 40141, "name" => "Guardian\'s Dreadstone", "icon" => "inv_jewelcrafting_gem_40", "quality" => 4, "spell_id" => 66561, "craft_spell_id" => 66561, "effect_spell_id" => null],
        3543 => ["item_id" => 40140, "name" => "Puissant Dreadstone", "icon" => "inv_jewelcrafting_gem_40", "quality" => 4, "spell_id" => 66563, "craft_spell_id" => 66563, "effect_spell_id" => null],
        3544 => ["item_id" => 40131, "name" => "Tenuous Dreadstone", "icon" => "inv_jewelcrafting_gem_40", "quality" => 4, "spell_id" => 66565, "craft_spell_id" => 66565, "effect_spell_id" => null],
        3545 => ["item_id" => 40133, "name" => "Purified Dreadstone", "icon" => "inv_jewelcrafting_gem_40", "quality" => 4, "spell_id" => 66556, "craft_spell_id" => 66556, "effect_spell_id" => null],
        3546 => ["item_id" => 40134, "name" => "Royal Dreadstone", "icon" => "inv_jewelcrafting_gem_40", "quality" => 4, "spell_id" => 66558, "craft_spell_id" => 66558, "effect_spell_id" => null],
        3547 => ["item_id" => 40137, "name" => "Infused Dreadstone", "icon" => "inv_jewelcrafting_gem_40", "quality" => 4, "spell_id" => 66564, "craft_spell_id" => 66564, "effect_spell_id" => null],
        3548 => ["item_id" => 40135, "name" => "Mysterious Dreadstone", "icon" => "inv_jewelcrafting_gem_40", "quality" => 4, "spell_id" => 28680, "craft_spell_id" => 66562, "effect_spell_id" => 28680],
        3549 => ["item_id" => 40142, "name" => "Inscribed Ametrine", "icon" => "inv_jewelcrafting_gem_39", "quality" => 4, "spell_id" => 66567, "craft_spell_id" => 66567, "effect_spell_id" => null],
        3550 => ["item_id" => 40143, "name" => "Etched Ametrine", "icon" => "inv_jewelcrafting_gem_39", "quality" => 4, "spell_id" => 66572, "craft_spell_id" => 66572, "effect_spell_id" => null],
        3551 => ["item_id" => 40144, "name" => "Champion\'s Ametrine", "icon" => "inv_jewelcrafting_gem_39", "quality" => 4, "spell_id" => 66579, "craft_spell_id" => 66579, "effect_spell_id" => null],
        3552 => ["item_id" => 40145, "name" => "Resplendent Ametrine", "icon" => "inv_jewelcrafting_gem_39", "quality" => 4, "spell_id" => 66582, "craft_spell_id" => 66582, "effect_spell_id" => null],
        3553 => ["item_id" => 40146, "name" => "Fierce Ametrine", "icon" => "inv_jewelcrafting_gem_39", "quality" => 4, "spell_id" => 66583, "craft_spell_id" => 66583, "effect_spell_id" => null],
        3554 => ["item_id" => 40147, "name" => "Deadly Ametrine", "icon" => "inv_jewelcrafting_gem_39", "quality" => 4, "spell_id" => 66568, "craft_spell_id" => 66568, "effect_spell_id" => null],
        3555 => ["item_id" => 40148, "name" => "Glinting Ametrine", "icon" => "inv_jewelcrafting_gem_39", "quality" => 4, "spell_id" => 66575, "craft_spell_id" => 66575, "effect_spell_id" => null],
        3556 => ["item_id" => 40149, "name" => "Lucent Ametrine", "icon" => "inv_jewelcrafting_gem_39", "quality" => 4, "spell_id" => 66585, "craft_spell_id" => 66585, "effect_spell_id" => null],
        3557 => ["item_id" => 40150, "name" => "Deft Ametrine", "icon" => "inv_jewelcrafting_gem_39", "quality" => 4, "spell_id" => 66584, "craft_spell_id" => 66584, "effect_spell_id" => null],
        3558 => ["item_id" => 40151, "name" => "Luminous Ametrine", "icon" => "inv_jewelcrafting_gem_39", "quality" => 4, "spell_id" => 66566, "craft_spell_id" => 66566, "effect_spell_id" => null],
        3559 => ["item_id" => 40152, "name" => "Potent Ametrine", "icon" => "inv_jewelcrafting_gem_39", "quality" => 4, "spell_id" => 66569, "craft_spell_id" => 66569, "effect_spell_id" => null],
        3560 => ["item_id" => 40153, "name" => "Veiled Ametrine", "icon" => "inv_jewelcrafting_gem_39", "quality" => 4, "spell_id" => 66570, "craft_spell_id" => 66570, "effect_spell_id" => null],
        3561 => ["item_id" => 40154, "name" => "Durable Ametrine", "icon" => "inv_jewelcrafting_gem_39", "quality" => 4, "spell_id" => 66571, "craft_spell_id" => 66571, "effect_spell_id" => null],
        3563 => ["item_id" => 40155, "name" => "Reckless Ametrine", "icon" => "inv_jewelcrafting_gem_39", "quality" => 4, "spell_id" => 66574, "craft_spell_id" => 66574, "effect_spell_id" => null],
        3564 => ["item_id" => 40156, "name" => "Wicked Ametrine", "icon" => "inv_jewelcrafting_gem_39", "quality" => 4, "spell_id" => 66577, "craft_spell_id" => 66577, "effect_spell_id" => null],
        3565 => ["item_id" => 40157, "name" => "Pristine Ametrine", "icon" => "inv_jewelcrafting_gem_39", "quality" => 4, "spell_id" => 66573, "craft_spell_id" => 66573, "effect_spell_id" => null],
        3566 => ["item_id" => 40158, "name" => "Empowered Ametrine", "icon" => "inv_jewelcrafting_gem_39", "quality" => 4, "spell_id" => 66580, "craft_spell_id" => 66580, "effect_spell_id" => null],
        3567 => ["item_id" => 40159, "name" => "Stark Ametrine", "icon" => "inv_jewelcrafting_gem_39", "quality" => 4, "spell_id" => 66587, "craft_spell_id" => 66587, "effect_spell_id" => null],
        3568 => ["item_id" => 40160, "name" => "Stalwart Ametrine", "icon" => "inv_jewelcrafting_gem_39", "quality" => 4, "spell_id" => 66581, "craft_spell_id" => 66581, "effect_spell_id" => null],
        3569 => ["item_id" => 40161, "name" => "Glimmering Ametrine", "icon" => "inv_jewelcrafting_gem_39", "quality" => 4, "spell_id" => 66578, "craft_spell_id" => 66578, "effect_spell_id" => null],
        3570 => ["item_id" => 40162, "name" => "Accurate Ametrine", "icon" => "inv_jewelcrafting_gem_39", "quality" => 4, "spell_id" => 66576, "craft_spell_id" => 66576, "effect_spell_id" => null],
        3571 => ["item_id" => 40163, "name" => "Resolute Ametrine", "icon" => "inv_jewelcrafting_gem_39", "quality" => 4, "spell_id" => 66586, "craft_spell_id" => 66586, "effect_spell_id" => null],
        3572 => ["item_id" => 40164, "name" => "Timeless Eye of Zul", "icon" => "inv_jewelcrafting_gem_41", "quality" => 4, "spell_id" => 66432, "craft_spell_id" => 66432, "effect_spell_id" => null],
        3573 => ["item_id" => 40165, "name" => "Jagged Eye of Zul", "icon" => "inv_jewelcrafting_gem_41", "quality" => 4, "spell_id" => 66431, "craft_spell_id" => 66431, "effect_spell_id" => null],
        3574 => ["item_id" => 40166, "name" => "Vivid Eye of Zul", "icon" => "inv_jewelcrafting_gem_41", "quality" => 4, "spell_id" => 66429, "craft_spell_id" => 66429, "effect_spell_id" => null],
        3575 => ["item_id" => 40167, "name" => "Enduring Eye of Zul", "icon" => "inv_jewelcrafting_gem_41", "quality" => 4, "spell_id" => 66338, "craft_spell_id" => 66338, "effect_spell_id" => null],
        3576 => ["item_id" => 40168, "name" => "Steady Eye of Zul", "icon" => "inv_jewelcrafting_gem_41", "quality" => 4, "spell_id" => 66428, "craft_spell_id" => 66428, "effect_spell_id" => null],
        3577 => ["item_id" => 40169, "name" => "Forceful Eye of Zul", "icon" => "inv_jewelcrafting_gem_41", "quality" => 4, "spell_id" => 66434, "craft_spell_id" => 66434, "effect_spell_id" => null],
        3578 => ["item_id" => 40170, "name" => "Seer\'s Eye of Zul", "icon" => "inv_jewelcrafting_gem_41", "quality" => 4, "spell_id" => 66433, "craft_spell_id" => 66433, "effect_spell_id" => null],
        3579 => ["item_id" => 40171, "name" => "Misty Eye of Zul", "icon" => "inv_jewelcrafting_gem_41", "quality" => 4, "spell_id" => 66435, "craft_spell_id" => 66435, "effect_spell_id" => null],
        3580 => ["item_id" => 40172, "name" => "Shining Eye of Zul", "icon" => "inv_jewelcrafting_gem_41", "quality" => 4, "spell_id" => 66437, "craft_spell_id" => 66437, "effect_spell_id" => null],
        3581 => ["item_id" => 40173, "name" => "Turbid Eye of Zul", "icon" => "inv_jewelcrafting_gem_41", "quality" => 4, "spell_id" => 66445, "craft_spell_id" => 66445, "effect_spell_id" => null],
        2827 => ["item_id" => 25890, "name" => "Destructive Skyfire Diamond", "icon" => "inv_jewelcrafting_noblegem_02", "quality" => 3, "spell_id" => 11818, "craft_spell_id" => 32871, "effect_spell_id" => 11818],
        2828 => ["item_id" => 25893, "name" => "Mystical Skyfire Diamond", "icon" => "inv_jewelcrafting_noblegem_02", "quality" => 3, "spell_id" => 32837, "craft_spell_id" => 32872, "effect_spell_id" => 32837],
        2829 => ["item_id" => 25894, "name" => "Swift Skyfire Diamond", "icon" => "inv_jewelcrafting_noblegem_02", "quality" => 3, "spell_id" => 23990, "craft_spell_id" => 32873, "effect_spell_id" => 23990],
        2830 => ["item_id" => 25895, "name" => "Enigmatic Skyfire Diamond", "icon" => "inv_jewelcrafting_noblegem_02", "quality" => 3, "spell_id" => 55378, "craft_spell_id" => 32874, "effect_spell_id" => 55378],
        2831 => ["item_id" => 25896, "name" => "Powerful Earthstorm Diamond", "icon" => "inv_jewelcrafting_noblegem_01", "quality" => 3, "spell_id" => 55358, "craft_spell_id" => 32866, "effect_spell_id" => 55358],
        2832 => ["item_id" => 25897, "name" => "Bracing Earthstorm Diamond", "icon" => "inv_jewelcrafting_noblegem_01", "quality" => 3, "spell_id" => 32842, "craft_spell_id" => 32867, "effect_spell_id" => 32842],
        2833 => ["item_id" => 25898, "name" => "Tenacious Earthstorm Diamond", "icon" => "inv_jewelcrafting_noblegem_01", "quality" => 3, "spell_id" => 32844, "craft_spell_id" => 32868, "effect_spell_id" => 32844],
        2834 => ["item_id" => 25899, "name" => "Brutal Earthstorm Diamond", "icon" => "inv_jewelcrafting_noblegem_01", "quality" => 3, "spell_id" => 37982, "craft_spell_id" => 32869, "effect_spell_id" => 37982],
        2835 => ["item_id" => 25901, "name" => "Insightful Earthstorm Diamond", "icon" => "inv_jewelcrafting_noblegem_01", "quality" => 3, "spell_id" => 27521, "craft_spell_id" => 32870, "effect_spell_id" => 27521],
        2969 => ["item_id" => 28556, "name" => "Swift Windfire Diamond", "icon" => "inv_jewelcrafting_noblegem_02", "quality" => 3, "spell_id" => 23990, "craft_spell_id" => null, "effect_spell_id" => 23990],
        2970 => ["item_id" => 28557, "name" => "Swift Starfire Diamond", "icon" => "inv_jewelcrafting_noblegem_02", "quality" => 3, "spell_id" => 23990, "craft_spell_id" => null, "effect_spell_id" => 23990],
        3154 => ["item_id" => 32409, "name" => "Relentless Earthstorm Diamond", "icon" => "inv_jewelcrafting_noblegem_01", "quality" => 3, "spell_id" => 39957, "craft_spell_id" => 39961, "effect_spell_id" => 39957],
        3155 => ["item_id" => 32410, "name" => "Thundering Skyfire Diamond", "icon" => "inv_jewelcrafting_noblegem_02", "quality" => 3, "spell_id" => 39958, "craft_spell_id" => 39963, "effect_spell_id" => 39958],
        3162 => ["item_id" => 32640, "name" => "Potent Unstable Diamond", "icon" => "inv_jewelcrafting_noblegem_01", "quality" => 3, "spell_id" => 40691, "craft_spell_id" => null, "effect_spell_id" => 40691],
        3163 => ["item_id" => 32641, "name" => "Imbued Unstable Diamond", "icon" => "inv_jewelcrafting_noblegem_02", "quality" => 3, "spell_id" => 40706, "craft_spell_id" => null, "effect_spell_id" => 40706],
        3261 => ["item_id" => 34220, "name" => "Chaotic Skyfire Diamond", "icon" => "inv_jewelcrafting_noblegem_02", "quality" => 3, "spell_id" => 44797, "craft_spell_id" => 44794, "effect_spell_id" => 44797],
        3274 => ["item_id" => 35501, "name" => "Eternal Earthstorm Diamond", "icon" => "inv_jewelcrafting_noblegem_01", "quality" => 3, "spell_id" => 55283, "craft_spell_id" => 46597, "effect_spell_id" => 55283],
        3275 => ["item_id" => 35503, "name" => "Ember Skyfire Diamond", "icon" => "inv_jewelcrafting_noblegem_02", "quality" => 3, "spell_id" => 46600, "craft_spell_id" => 46601, "effect_spell_id" => 46600],
        3621 => ["item_id" => 41285, "name" => "Chaotic Skyflare Diamond", "icon" => "inv_jewelcrafting_icediamond_02", "quality" => 3, "spell_id" => 44797, "craft_spell_id" => 55389, "effect_spell_id" => 44797],
        3622 => ["item_id" => 41307, "name" => "Destructive Skyflare Diamond", "icon" => "inv_jewelcrafting_icediamond_02", "quality" => 3, "spell_id" => 11818, "craft_spell_id" => 55390, "effect_spell_id" => 11818],
        3623 => ["item_id" => 41333, "name" => "Ember Skyflare Diamond", "icon" => "inv_jewelcrafting_icediamond_02", "quality" => 3, "spell_id" => 55275, "craft_spell_id" => 55392, "effect_spell_id" => 55275],
        3624 => ["item_id" => 41335, "name" => "Enigmatic Skyflare Diamond", "icon" => "inv_jewelcrafting_icediamond_02", "quality" => 3, "spell_id" => 55378, "craft_spell_id" => 55393, "effect_spell_id" => 55378],
        3625 => ["item_id" => 41339, "name" => "Swift Skyflare Diamond", "icon" => "inv_jewelcrafting_icediamond_02", "quality" => 3, "spell_id" => 23990, "craft_spell_id" => 55394, "effect_spell_id" => 23990],
        3626 => ["item_id" => 41395, "name" => "Bracing Earthsiege Diamond", "icon" => "inv_jewelcrafting_shadowspirit_02", "quality" => 3, "spell_id" => 32842, "craft_spell_id" => 55397, "effect_spell_id" => 32842],
        3627 => ["item_id" => 41401, "name" => "Insightful Earthsiege Diamond", "icon" => "inv_jewelcrafting_shadowspirit_02", "quality" => 3, "spell_id" => 55381, "craft_spell_id" => 55396, "effect_spell_id" => 55381],
        3628 => ["item_id" => 41398, "name" => "Relentless Earthsiege Diamond", "icon" => "inv_jewelcrafting_shadowspirit_02", "quality" => 3, "spell_id" => 39957, "craft_spell_id" => 55400, "effect_spell_id" => 39957],
        3629 => ["item_id" => 41335, "name" => "Enigmatic Skyflare Diamond", "icon" => "inv_jewelcrafting_icediamond_02", "quality" => 3, "spell_id" => 55393, "craft_spell_id" => 55393, "effect_spell_id" => null],
        3631 => ["item_id" => 41396, "name" => "Eternal Earthsiege Diamond", "icon" => "inv_jewelcrafting_shadowspirit_02", "quality" => 3, "spell_id" => 55283, "craft_spell_id" => 55398, "effect_spell_id" => 55283],
        3632 => ["item_id" => 41375, "name" => "Tireless Skyflare Diamond", "icon" => "inv_jewelcrafting_icediamond_02", "quality" => 3, "spell_id" => 23990, "craft_spell_id" => 55386, "effect_spell_id" => 23990],
        3633 => ["item_id" => 41376, "name" => "Revitalizing Skyflare Diamond", "icon" => "inv_jewelcrafting_icediamond_02", "quality" => 3, "spell_id" => 55256, "craft_spell_id" => 55407, "effect_spell_id" => 55256],
        3634 => ["item_id" => 41377, "name" => "Effulgent Skyflare Diamond", "icon" => "inv_jewelcrafting_icediamond_02", "quality" => 3, "spell_id" => 55345, "craft_spell_id" => 55384, "effect_spell_id" => 55345],
        3635 => ["item_id" => 41378, "name" => "Forlorn Skyflare Diamond", "icon" => "inv_jewelcrafting_icediamond_02", "quality" => 3, "spell_id" => 55366, "craft_spell_id" => 55387, "effect_spell_id" => 55366],
        3636 => ["item_id" => 41379, "name" => "Impassive Skyflare Diamond", "icon" => "inv_jewelcrafting_icediamond_02", "quality" => 3, "spell_id" => 55357, "craft_spell_id" => 55388, "effect_spell_id" => 55357],
        3637 => ["item_id" => 41380, "name" => "Austere Earthsiege Diamond", "icon" => "inv_jewelcrafting_shadowspirit_02", "quality" => 3, "spell_id" => 55344, "craft_spell_id" => 55401, "effect_spell_id" => 55344],
        3638 => ["item_id" => 41381, "name" => "Persistent Earthsiege Diamond", "icon" => "inv_jewelcrafting_shadowspirit_02", "quality" => 3, "spell_id" => 55358, "craft_spell_id" => 55402, "effect_spell_id" => 55358],
        3639 => ["item_id" => 41382, "name" => "Trenchant Earthsiege Diamond", "icon" => "inv_jewelcrafting_shadowspirit_02", "quality" => 3, "spell_id" => 55358, "craft_spell_id" => 55403, "effect_spell_id" => 55358],
        3640 => ["item_id" => 41385, "name" => "Invigorating Earthsiege Diamond", "icon" => "inv_jewelcrafting_shadowspirit_02", "quality" => 3, "spell_id" => 61356, "craft_spell_id" => 55404, "effect_spell_id" => 61356],
        3641 => ["item_id" => 41389, "name" => "Beaming Earthsiege Diamond", "icon" => "inv_jewelcrafting_shadowspirit_02", "quality" => 3, "spell_id" => 55337, "craft_spell_id" => 55405, "effect_spell_id" => 55337],
        3642 => ["item_id" => 41397, "name" => "Powerful Earthsiege Diamond", "icon" => "inv_jewelcrafting_shadowspirit_02", "quality" => 3, "spell_id" => 55358, "craft_spell_id" => 55399, "effect_spell_id" => 55358],
        3643 => ["item_id" => 41400, "name" => "Thundering Skyflare Diamond", "icon" => "inv_jewelcrafting_icediamond_02", "quality" => 3, "spell_id" => 55380, "craft_spell_id" => 55395, "effect_spell_id" => 55380],
        3798 => ["item_id" => 44076, "name" => "Swift Starflare Diamond", "icon" => "inv_jewelcrafting_icediamond_02", "quality" => 3, "spell_id" => 23990, "craft_spell_id" => null, "effect_spell_id" => 23990],
        3799 => ["item_id" => 44078, "name" => "Tireless Starflare Diamond", "icon" => "inv_jewelcrafting_icediamond_02", "quality" => 3, "spell_id" => 23990, "craft_spell_id" => null, "effect_spell_id" => 23990],
        3800 => ["item_id" => 44082, "name" => "Impassive Starflare Diamond", "icon" => "inv_jewelcrafting_icediamond_02", "quality" => 3, "spell_id" => 55357, "craft_spell_id" => null, "effect_spell_id" => 55357],
        3801 => ["item_id" => 44081, "name" => "Enigmatic Starflare Diamond", "icon" => "inv_jewelcrafting_icediamond_02", "quality" => 3, "spell_id" => 55378, "craft_spell_id" => null, "effect_spell_id" => 55378],
        3802 => ["item_id" => 44084, "name" => "Forlorn Starflare Diamond", "icon" => "inv_jewelcrafting_icediamond_02", "quality" => 3, "spell_id" => 55366, "craft_spell_id" => null, "effect_spell_id" => 55366],
        3803 => ["item_id" => 44087, "name" => "Persistent Earthshatter Diamond", "icon" => "inv_jewelcrafting_shadowspirit_02", "quality" => 3, "spell_id" => 55358, "craft_spell_id" => null, "effect_spell_id" => 55358],
        3804 => ["item_id" => 44088, "name" => "Powerful Earthshatter Diamond", "icon" => "inv_jewelcrafting_shadowspirit_02", "quality" => 3, "spell_id" => 55358, "craft_spell_id" => null, "effect_spell_id" => 55358],
        3805 => ["item_id" => 44089, "name" => "Trenchant Earthshatter Diamond", "icon" => "inv_jewelcrafting_shadowspirit_02", "quality" => 3, "spell_id" => 55358, "craft_spell_id" => null, "effect_spell_id" => 55358],
        3732 => ["item_id" => 42142, "name" => "Bold Dragon\'s Eye", "icon" => "inv_jewelcrafting_dragonseye05", "quality" => 4, "spell_id" => 56049, "craft_spell_id" => 56049, "effect_spell_id" => null],
        3733 => ["item_id" => 42143, "name" => "Delicate Dragon\'s Eye", "icon" => "inv_jewelcrafting_dragonseye05", "quality" => 4, "spell_id" => 56052, "craft_spell_id" => 56052, "effect_spell_id" => null],
        3734 => ["item_id" => 42144, "name" => "Runed Dragon\'s Eye", "icon" => "inv_jewelcrafting_dragonseye05", "quality" => 4, "spell_id" => 56053, "craft_spell_id" => 56053, "effect_spell_id" => null],
        3735 => ["item_id" => 42145, "name" => "Sparkling Dragon\'s Eye", "icon" => "inv_jewelcrafting_dragonseye04", "quality" => 4, "spell_id" => 56087, "craft_spell_id" => 56087, "effect_spell_id" => null],
        3736 => ["item_id" => 42146, "name" => "Lustrous Dragon\'s Eye", "icon" => "inv_jewelcrafting_dragonseye04", "quality" => 4, "spell_id" => 56077, "craft_spell_id" => 56077, "effect_spell_id" => null],
        3737 => ["item_id" => 42148, "name" => "Brilliant Dragon\'s Eye", "icon" => "inv_jewelcrafting_dragonseye03", "quality" => 4, "spell_id" => 56074, "craft_spell_id" => 56074, "effect_spell_id" => null],
        3738 => ["item_id" => 42149, "name" => "Smooth Dragon\'s Eye", "icon" => "inv_jewelcrafting_dragonseye03", "quality" => 4, "spell_id" => 56085, "craft_spell_id" => 56085, "effect_spell_id" => null],
        3739 => ["item_id" => 42150, "name" => "Quick Dragon\'s Eye", "icon" => "inv_jewelcrafting_dragonseye03", "quality" => 4, "spell_id" => 56083, "craft_spell_id" => 56083, "effect_spell_id" => null],
        3740 => ["item_id" => 42151, "name" => "Subtle Dragon\'s Eye", "icon" => "inv_jewelcrafting_dragonseye05", "quality" => 4, "spell_id" => 56055, "craft_spell_id" => 56055, "effect_spell_id" => null],
        3741 => ["item_id" => 42152, "name" => "Flashing Dragon\'s Eye", "icon" => "inv_jewelcrafting_dragonseye05", "quality" => 4, "spell_id" => 56056, "craft_spell_id" => 56056, "effect_spell_id" => null],
        3742 => ["item_id" => 42156, "name" => "Rigid Dragon\'s Eye", "icon" => "inv_jewelcrafting_dragonseye03", "quality" => 4, "spell_id" => 56084, "craft_spell_id" => 56084, "effect_spell_id" => null],
        3743 => ["item_id" => 42157, "name" => "Thick Dragon\'s Eye", "icon" => "inv_jewelcrafting_dragonseye03", "quality" => 4, "spell_id" => 56089, "craft_spell_id" => 56089, "effect_spell_id" => null],
        3744 => ["item_id" => 42158, "name" => "Mystic Dragon\'s Eye", "icon" => "inv_jewelcrafting_dragonseye03", "quality" => 4, "spell_id" => 56079, "craft_spell_id" => 56079, "effect_spell_id" => null],
        3745 => ["item_id" => 42153, "name" => "Fractured Dragon\'s Eye", "icon" => "inv_jewelcrafting_dragonseye05", "quality" => 4, "spell_id" => 56076, "craft_spell_id" => 56076, "effect_spell_id" => null],
        3746 => ["item_id" => 42154, "name" => "Precise Dragon\'s Eye", "icon" => "inv_jewelcrafting_dragonseye05", "quality" => 4, "spell_id" => 56081, "craft_spell_id" => 56081, "effect_spell_id" => null],
        3747 => ["item_id" => 42155, "name" => "Stormy Dragon\'s Eye", "icon" => "inv_jewelcrafting_dragonseye04", "quality" => 4, "spell_id" => 46055, "craft_spell_id" => 56088, "effect_spell_id" => 46055],
        3879 => ["item_id" => 49110, "name" => "Nightmare Tear", "icon" => "inv_misc_gem_pearl_12", "quality" => 4, "spell_id" => 68251, "craft_spell_id" => 68253, "effect_spell_id" => 68251],
    ];

    /**
     * Returns all gems indexed by item_id.
     * If an item has multiple enchantment IDs (e.g. meta gems with effect vs craft),
     * the entry with effect_spell_id is preferred.
     *
     * @return array<int, array{item_id: int, enchant_id: int, name: string, icon: string, quality: int, spell_id: ?int, craft_spell_id: ?int, effect_spell_id: ?int}>
     */
    public static function getAllByItemId(): array
    {
        $result = [];
        foreach (self::GEMS as $enchantId => $gem) {
            $itemId = $gem['item_id'];
            if (!isset($result[$itemId]) || ($gem['effect_spell_id'] !== null && $result[$itemId]['effect_spell_id'] === null)) {
                $gem['enchant_id'] = $enchantId;
                $result[$itemId] = $gem;
            }
        }

        return $result;
    }

    /**
     * Returns all gems indexed by enchant_id.
     *
     * @return array<int, array{item_id: int, enchant_id: int, name: string, icon: string, quality: int, spell_id: ?int, craft_spell_id: ?int, effect_spell_id: ?int}>
     */
    public static function getAllByEnchantId(): array
    {
        $result = [];
        foreach (self::GEMS as $enchantId => $gem) {
            $gem['enchant_id'] = $enchantId;
            $result[$enchantId] = $gem;
        }

        return $result;
    }
}
