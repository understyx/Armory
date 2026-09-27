<?php

namespace App\Tests\Controller;

use App\Controller\ReferenceDataApiController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

class ReferenceDataApiControllerTest extends TestCase
{
    private ReferenceDataApiController $controller;

    protected function setUp(): void
    {
        $this->controller = new ReferenceDataApiController();
    }

    public function testEnchantsEndpointReturnsDictionaryWithCacheAndCorsHeaders(): void
    {
        $response = $this->controller->enchants();
        $payload = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertGreaterThanOrEqual(2600, count($payload));
        self::assertSame('+20 Strength', $payload['3518']);
        self::assertSame('Rockbiter 3', $payload['1']);

        $cacheControl = (string) $response->headers->get('Cache-Control');
        self::assertStringContainsString('public', $cacheControl);
        self::assertStringContainsString('max-age=3600', $cacheControl);
        self::assertSame('*', $response->headers->get('Access-Control-Allow-Origin'));
    }

    public function testGemsEndpointReturnsItemsWithEnchantAndSpellIds(): void
    {
        $response = $this->controller->gems();
        $payload = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertGreaterThanOrEqual(190, count($payload));

        // Test normal stat gem: Bold Cardinal Ruby (item 40111, enchant 3518)
        self::assertArrayHasKey('40111', $payload);
        $ruby = $payload['40111'];
        self::assertSame(40111, $ruby['item_id']);
        self::assertSame(3518, $ruby['enchant_id']);
        self::assertSame('Bold Cardinal Ruby', $ruby['name']);
        self::assertSame(66447, $ruby['spell_id']);
        self::assertSame(66447, $ruby['craft_spell_id']);
        self::assertNull($ruby['effect_spell_id']);
        self::assertSame(4, $ruby['quality']);
        self::assertSame('inv_jewelcrafting_gem_37', $ruby['icon']);

        // Test meta gem: Chaotic Skyflare Diamond (item 41285, enchant 3621)
        self::assertArrayHasKey('41285', $payload);
        $diamond = $payload['41285'];
        self::assertSame(41285, $diamond['item_id']);
        self::assertSame(3621, $diamond['enchant_id']);
        self::assertSame('Chaotic Skyflare Diamond', $diamond['name']);
        self::assertSame(44797, $diamond['spell_id']);
        self::assertSame(55389, $diamond['craft_spell_id']);
        self::assertSame(44797, $diamond['effect_spell_id']);

        $cacheControl = (string) $response->headers->get('Cache-Control');
        self::assertStringContainsString('public', $cacheControl);
        self::assertStringContainsString('max-age=3600', $cacheControl);
        self::assertSame('*', $response->headers->get('Access-Control-Allow-Origin'));
    }

    public function testGlyphsEndpointReturnsAllGlyphsWithActiveSpellIds(): void
    {
        $response = $this->controller->glyphs();
        $payload = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertCount(350, $payload);

        // Test Glyph of Maul (item 40897)
        self::assertArrayHasKey('40897', $payload);
        $maul = $payload['40897'];
        self::assertSame(40897, $maul['item_id']);
        self::assertSame(54811, $maul['spell_id']); // active glyph aura spell
        self::assertSame(54858, $maul['item_spell_id']); // item learn spell
        self::assertSame(162, $maul['glyph_id']);
        self::assertSame('Glyph of Maul', $maul['name']);
        self::assertSame('major', $maul['type']);
        self::assertSame(11, $maul['class_id']);
        self::assertSame('Druid', $maul['class_name']);
        self::assertSame('inv_glyph_majordruid', $maul['icon']);
        self::assertSame(1, $maul['quality']);
        self::assertSame(10, $maul['item_level']);

        $cacheControl = (string) $response->headers->get('Cache-Control');
        self::assertStringContainsString('public', $cacheControl);
        self::assertStringContainsString('max-age=3600', $cacheControl);
        self::assertSame('*', $response->headers->get('Access-Control-Allow-Origin'));
    }
}
