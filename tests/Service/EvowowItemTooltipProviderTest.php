<?php

namespace App\Tests\Service;

use App\Service\ItemTooltipProvider\EvowowItemTooltipProvider;
use App\Service\ItemTooltipProvider\ItemTooltipHtmlParser;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class EvowowItemTooltipProviderTest extends TestCase
{
    public function testParsesPowerResponseAndJsonEscapedTooltipHtml(): void
    {
        $provider = $this->provider(200, <<<'JS'
            $WowheadPower.registerItem(45756, 0, {
                "name_enus": "Glyph of Penance",
                "tooltip_enus": "\u003Cspan class=\"q2\"\u003EUse: Reduces the cooldown of Penance by 2 sec.\u003C/span\u003E"
            });
            JS);

        $result = $provider->fetch(45756);

        self::assertSame('evowow', $result['provider']);
        self::assertSame(150, $provider->getPriority());
        self::assertSame('use', $result['effects'][0]['trigger_type']);
        self::assertSame('Reduces the cooldown of Penance by 2 sec.', $result['effects'][0]['description']);
        self::assertNull($result['effects'][0]['spell_id']);
    }

    public function testPreservesHisHerPlaceholderWithoutAngleBrackets(): void
    {
        foreach ([
            '<span class="q2">Use: Grants the caster 45% of <his/her> base mana.</span>',
            '<span class="q2">Use: Grants the caster 45% of &lt;his/her&gt; base mana.</span>',
        ] as $tooltip) {
            $escapedTooltip = json_encode($tooltip, JSON_THROW_ON_ERROR | JSON_HEX_TAG);
            $provider = $this->provider(200, '$WowheadPower.registerItem(42410, 0, {"tooltip_enus":'.$escapedTooltip.'});');

            self::assertSame(
                'Grants the caster 45% of his/her base mana.',
                $provider->fetch(42410)['effects'][0]['description']
            );
        }
    }

    public function testNon200ResponseThrowsRuntimeException(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('HTTP 403');

        $this->provider(403, 'blocked')->fetch(45756);
    }

    public function testGarbageResponseThrowsRuntimeException(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('did not contain an item tooltip');

        $this->provider(200, '<html>not an Evowow payload</html>')->fetch(45756);
    }

    public function testInvalidJsonInsidePowerResponseThrowsRuntimeException(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('invalid item data');

        $this->provider(200, '$WowheadPower.registerItem(45756, 0, {"tooltip_enus": });')->fetch(45756);
    }

    private function provider(int $status, string $body): EvowowItemTooltipProvider
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($status);
        $response->method('getContent')->with(false)->willReturn($body);

        $client = $this->createMock(HttpClientInterface::class);
        $client->expects(self::once())
            ->method('request')
            ->with(
                'GET',
                self::stringStartsWith('https://wotlk.evowow.com/?item='),
                self::callback(static fn (array $options): bool => ($options['headers']['User-Agent'] ?? null) === 'Armorystuff tooltip enricher/1.0')
            )
            ->willReturn($response);

        return new EvowowItemTooltipProvider($client, new ItemTooltipHtmlParser());
    }
}
