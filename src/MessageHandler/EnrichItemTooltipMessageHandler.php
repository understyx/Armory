<?php

namespace App\MessageHandler;

use App\Message\EnrichItemTooltipMessage;
use App\Service\TooltipEnrichmentService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class EnrichItemTooltipMessageHandler
{
    public function __construct(private TooltipEnrichmentService $enrichmentService)
    {
    }

    public function __invoke(EnrichItemTooltipMessage $message): void
    {
        $this->enrichmentService->enrichItem($message->itemId, $message->setId, $message->locale);
    }
}
