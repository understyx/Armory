<?php

namespace App\Command;

use App\Repository\TooltipEnrichmentRepository;
use App\Service\TooltipEnrichmentService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:enrich-item-tooltips',
    description: 'Cache missing WotLK item effects and item-set descriptions from external tooltip providers.',
)]
class EnrichItemTooltipsCommand extends Command
{
    public function __construct(
        private readonly TooltipEnrichmentRepository $repository,
        private readonly TooltipEnrichmentService $enrichmentService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('item-id', InputArgument::OPTIONAL, 'Only enrich this item ID')
            ->addOption('all', null, InputOption::VALUE_NONE, 'Explicitly enrich every candidate item')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum number of candidate items (0 means all)', '0')
            ->addOption('locale', null, InputOption::VALUE_REQUIRED, 'Tooltip locale', 'enUS')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Refetch items that already have a successful cache entry');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $itemId = $input->getArgument('item-id');
        $itemId = $itemId !== null ? (int) $itemId : null;
        $all = (bool) $input->getOption('all');
        $limit = max(0, (int) $input->getOption('limit'));
        $locale = (string) $input->getOption('locale');
        $force = (bool) $input->getOption('force');

        if ($itemId === null && !$all) {
            $io->note('No items were changed. Supply an item ID for one item, or use --all explicitly for bulk enrichment.');

            return Command::SUCCESS;
        }
        if ($itemId !== null && $all) {
            $io->error('Use either an item ID or --all, not both.');

            return Command::INVALID;
        }

        $candidates = $this->repository->findCandidateItems($itemId, $limit);

        if ($candidates === []) {
            $io->warning('No item tooltip candidates were found.');

            return Command::SUCCESS;
        }

        $successful = 0;
        foreach ($candidates as $index => $candidate) {
            if ($this->enrichmentService->enrichItem(
                $candidate['item_id'],
                $candidate['set_id'],
                $locale,
                $force
            )) {
                $successful++;
            }
            $io->write(sprintf("\rProcessed %d/%d items", $index + 1, count($candidates)));
        }
        $io->newLine(2);
        $io->success(sprintf('Enriched or retained %d of %d item tooltips.', $successful, count($candidates)));

        return $successful === count($candidates) ? Command::SUCCESS : Command::FAILURE;
    }
}
