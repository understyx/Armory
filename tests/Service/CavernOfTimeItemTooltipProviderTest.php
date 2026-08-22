<?php

namespace App\Tests\Service;

use App\Service\ItemTooltipProvider\CavernOfTimeItemTooltipProvider;
use App\Service\ItemTooltipProvider\ItemTooltipHtmlParser;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class CavernOfTimeItemTooltipProviderTest extends TestCase
{
    public function testExtractsTooltipFromCavernItemPage(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getContent')->with(false)->willReturn(<<<'HTML'
            <html><body><div id="tooltip50363-generic" class="tooltip">
            <span class="q2">Equip: <a href="spell=71562">Original 3.3.5 trinket effect.</a></span>
            </div></body></html>
            HTML);

        $client = $this->createMock(HttpClientInterface::class);
        $client->expects(self::once())
            ->method('request')
            ->with('GET', 'https://wotlk.cavernoftime.com/item=50363', self::isArray())
            ->willReturn($response);

        $result = (new CavernOfTimeItemTooltipProvider($client, new ItemTooltipHtmlParser()))->fetch(50363);

        self::assertSame('cavern_of_time', $result['provider']);
        self::assertSame(100, (new CavernOfTimeItemTooltipProvider($client, new ItemTooltipHtmlParser()))->getPriority());
        self::assertSame(71562, $result['effects'][0]['spell_id']);
        self::assertSame('Original 3.3.5 trinket effect.', $result['effects'][0]['description']);
    }
}
