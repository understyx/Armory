<?php

namespace App\Service\ItemTooltipProvider;

use JsonException;
use RuntimeException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class WowheadItemTooltipProvider implements ItemTooltipProviderInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ItemTooltipHtmlParser $parser,
    ) {
    }

    public function getName(): string
    {
        return 'wowhead';
    }

    public function getPriority(): int
    {
        // WotLK Classic changed some item effects. This provider must remain a
        // fallback behind the 3.3.5-oriented Cavern of Time data.
        return 50;
    }

    public function fetch(int $itemId, string $locale = 'enUS'): array
    {
        if ($locale !== 'enUS') {
            throw new RuntimeException('Wowhead enrichment currently supports enUS only.');
        }

        $url = sprintf('https://nether.wowhead.com/wotlk/tooltip/item/%d', $itemId);
        $response = $this->httpClient->request('GET', $url, [
            'timeout' => 8,
            'headers' => ['User-Agent' => 'Armorystuff tooltip enricher/1.0'],
        ]);
        if ($response->getStatusCode() !== 200) {
            throw new RuntimeException(sprintf('Wowhead returned HTTP %d.', $response->getStatusCode()));
        }

        $raw = $response->getContent(false);
        try {
            $payload = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Wowhead returned invalid JSON.', previous: $exception);
        }
        if (!is_array($payload) || !is_string($payload['tooltip'] ?? null)) {
            throw new RuntimeException('Wowhead response did not contain an item tooltip.');
        }

        return [
            'provider' => $this->getName(),
            'source_url' => $url,
            'raw_response' => $raw,
            ...$this->parser->parse($payload['tooltip']),
        ];
    }
}
