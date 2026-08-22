<?php

namespace App\Service;

use App\Repository\TooltipEnrichmentRepository;
use App\Service\ItemTooltipProvider\ItemTooltipProviderInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Throwable;

class TooltipEnrichmentService
{
    /** @var ItemTooltipProviderInterface[] */
    private array $providers;

    public function __construct(
        private readonly TooltipEnrichmentRepository $repository,
        #[AutowireIterator('app.item_tooltip_provider')]
        iterable $providers,
        private readonly ?LoggerInterface $logger = null,
    ) {
        $this->providers = iterator_to_array($providers);
        usort(
            $this->providers,
            static fn (ItemTooltipProviderInterface $left, ItemTooltipProviderInterface $right): int => $right->getPriority() <=> $left->getPriority()
        );
    }

    public function enrichItem(int $itemId, ?int $setId = null, string $locale = 'enUS', bool $force = false): bool
    {
        if (!$force && $this->repository->hasSuccessfulEnrichment($itemId, $locale)) {
            return true;
        }

        $context = $this->repository->findItemContext($itemId);
        if ($context === null) {
            return false;
        }

        $rawSpells = [];
        foreach ($context['tooltip']['spells'] ?? [] as $spell) {
            $spellId = (int) ($spell['id'] ?? 0);
            if ($spellId > 0) {
                $rawSpells[$spellId] = (int) ($spell['trigger'] ?? 1);
            }
        }
        $setId ??= $context['set_id'];
        $needsEffects = $rawSpells !== [];
        $needsSet = $setId > 0 && !$this->repository->hasItemSet($setId, $locale);
        $effects = [];
        $set = null;

        if (!$needsEffects && !$needsSet) {
            $this->repository->recordAttempt($itemId, 'enrichment', 200, null, null, $locale);

            return true;
        }

        foreach ($this->providers as $provider) {
            try {
                $result = $provider->fetch($itemId, $locale);
                $this->repository->recordAttempt(
                    $itemId,
                    $provider->getName(),
                    200,
                    $result['raw_response'],
                    null,
                    $locale
                );

                if ($effects === [] && $needsEffects) {
                    $effects = $this->normalizeEffects(
                        $result['effects'],
                        $rawSpells,
                        $provider->getName(),
                        $result['source_url']
                    );
                }
                if ($set === null && $needsSet && is_array($result['set'])) {
                    $set = [
                        ...$result['set'],
                        'source' => $provider->getName(),
                        'source_url' => $result['source_url'],
                    ];
                }

                if ((!$needsEffects || $effects !== []) && (!$needsSet || $set !== null)) {
                    break;
                }
            } catch (Throwable $exception) {
                $this->repository->recordAttempt(
                    $itemId,
                    $provider->getName(),
                    599,
                    null,
                    $exception->getMessage(),
                    $locale
                );
                $this->logger?->warning(sprintf(
                    'Tooltip enrichment failed for item #%d using %s: %s',
                    $itemId,
                    $provider->getName(),
                    $exception->getMessage()
                ));
            }
        }

        $complete = (!$needsEffects || $effects !== []) && (!$needsSet || $set !== null);
        if ($effects !== [] || $set !== null || (!$needsEffects && !$needsSet)) {
            $this->repository->saveEnrichment($itemId, $effects, $set, $setId, $locale);
        }
        $this->repository->recordAttempt(
            $itemId,
            'enrichment',
            $complete ? 200 : 204,
            null,
            $complete ? null : 'One or more requested tooltip sections could not be resolved.',
            $locale
        );

        return $complete;
    }

    /**
     * @param array<int, array{spell_id: int|null, trigger_type: string, description: string}> $effects
     * @param array<int, int> $rawSpells
     * @return array<int, array{spell_id: int|null, trigger_type: string, description: string, source: string, source_url: string}>
     */
    private function normalizeEffects(array $effects, array $rawSpells, string $source, string $sourceUrl): array
    {
        $normalized = [];
        foreach ($effects as $effect) {
            $spellId = $effect['spell_id'];
            if ($spellId === null || $spellId <= 0 || !isset($rawSpells[$spellId])) {
                continue;
            }
            $normalized[] = [
                'spell_id' => $spellId,
                'trigger_type' => $this->triggerType($rawSpells[$spellId]),
                'description' => $effect['description'],
                'source' => $source,
                'source_url' => $sourceUrl,
            ];
        }

        return $normalized;
    }

    private function triggerType(int $trigger): string
    {
        return match ($trigger) {
            0, 5 => 'use',
            2 => 'chance_on_hit',
            default => 'equip',
        };
    }
}
