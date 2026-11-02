<?php

namespace App\Service\ItemTooltipProvider;

use RuntimeException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class CavernOfTimeSpellTooltipProvider
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly SpellTooltipHtmlParser $parser,
    ) {
    }

    /** @return array{name: string, spell_rank: int|null, description: string, source_url: string}|null */
    public function fetch(int $spellId): ?array
    {
        $url = sprintf('https://wotlk.cavernoftime.com/spell=%d', $spellId);
        $response = $this->httpClient->request('GET', $url, [
            'timeout' => 8,
            'headers' => ['User-Agent' => 'Armorystuff spell tooltip enricher/1.0'],
        ]);
        $status = $response->getStatusCode();
        if ($status === 404) {
            return null;
        }
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException(sprintf('Cavern of Time returned HTTP %d.', $status));
        }

        $tooltip = $this->parser->parse($spellId, $response->getContent(false));
        if ($tooltip === null) {
            return null;
        }

        return [...$tooltip, 'source_url' => $url];
    }
}
