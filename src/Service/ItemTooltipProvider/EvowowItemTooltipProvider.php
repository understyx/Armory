<?php

namespace App\Service\ItemTooltipProvider;

use JsonException;
use RuntimeException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class EvowowItemTooltipProvider implements ItemTooltipProviderInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ItemTooltipHtmlParser $parser,
    ) {
    }

    public function getName(): string
    {
        return 'evowow';
    }

    public function getPriority(): int
    {
        return 150;
    }

    public function fetch(int $itemId, string $locale = 'enUS'): array
    {
        if ($locale !== 'enUS') {
            throw new RuntimeException('Evowow enrichment currently supports enUS only.');
        }

        $url = sprintf('https://wotlk.evowow.com/?item=%d&power', $itemId);
        $response = $this->httpClient->request('GET', $url, [
            'timeout' => 8,
            'headers' => ['User-Agent' => 'Armorystuff tooltip enricher/1.0'],
        ]);
        if ($response->getStatusCode() !== 200) {
            throw new RuntimeException(sprintf('Evowow returned HTTP %d.', $response->getStatusCode()));
        }

        $raw = $response->getContent(false);
        if (!preg_match('/^\s*\$WowheadPower\.registerItem\(\s*(\d+)\s*,\s*\d+\s*,\s*(\{.*\})\s*\)\s*;?\s*$/s', $raw, $matches)) {
            throw new RuntimeException('Evowow response did not contain an item tooltip.');
        }
        if ((int) $matches[1] !== $itemId) {
            throw new RuntimeException('Evowow response contained a different item ID.');
        }

        try {
            $payload = json_decode($matches[2], true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Evowow returned invalid item data.', previous: $exception);
        }
        if (!is_array($payload) || !is_string($payload['tooltip_enus'] ?? null)) {
            throw new RuntimeException('Evowow response did not contain an item tooltip.');
        }

        return [
            'provider' => $this->getName(),
            'source_url' => $url,
            'raw_response' => $raw,
            ...$this->parser->parse($payload['tooltip_enus'], true),
        ];
    }
}
