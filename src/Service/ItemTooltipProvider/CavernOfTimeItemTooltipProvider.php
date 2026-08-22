<?php

namespace App\Service\ItemTooltipProvider;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class CavernOfTimeItemTooltipProvider implements ItemTooltipProviderInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ItemTooltipHtmlParser $parser,
    ) {
    }

    public function getName(): string
    {
        return 'cavern_of_time';
    }

    public function getPriority(): int
    {
        return 100;
    }

    public function fetch(int $itemId, string $locale = 'enUS'): array
    {
        if ($locale !== 'enUS') {
            throw new RuntimeException('Cavern of Time enrichment currently supports enUS only.');
        }

        $url = sprintf('https://wotlk.cavernoftime.com/item=%d', $itemId);
        $response = $this->httpClient->request('GET', $url, [
            'timeout' => 8,
            'headers' => ['User-Agent' => 'Armorystuff tooltip enricher/1.0'],
        ]);
        if ($response->getStatusCode() !== 200) {
            throw new RuntimeException(sprintf('Cavern of Time returned HTTP %d.', $response->getStatusCode()));
        }

        $raw = $response->getContent(false);
        $tooltipHtml = $this->extractTooltipHtml($raw, $itemId);
        if ($tooltipHtml === null) {
            throw new RuntimeException('Cavern of Time response did not contain an item tooltip.');
        }

        return [
            'provider' => $this->getName(),
            'source_url' => $url,
            'raw_response' => $raw,
            ...$this->parser->parse($tooltipHtml),
        ];
    }

    private function extractTooltipHtml(string $html, int $itemId): ?string
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML($html);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $xpath = new DOMXPath($document);
        $nodes = $xpath->query(sprintf('//*[@id="tooltip%d-generic"]', $itemId));
        $node = $nodes !== false ? $nodes->item(0) : null;
        if (!$node instanceof DOMElement) {
            return null;
        }

        $result = '';
        foreach ($node->childNodes as $child) {
            $result .= $document->saveHTML($child);
        }

        return $result;
    }
}
