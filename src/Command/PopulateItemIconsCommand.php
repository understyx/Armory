<?php

namespace App\Command;

use App\Repository\WowItemRepository;
use App\Service\ItemIconResolverService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:populate-item-icons',
    description: 'Fetch and populate missing WoW item icon names in the wow_items table.',
)]
class PopulateItemIconsCommand extends Command
{
    public function __construct(
        private readonly WowItemRepository $wowItemRepository,
        private readonly ItemIconResolverService $iconResolverService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'limit',
            'l',
            InputOption::VALUE_OPTIONAL,
            'Maximum number of items to process in this run',
            100
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $limit = (int) $input->getOption('limit');

        $io->title('Populating WoW Item Icons');

        // Find items where icon is NULL
        $qb = $this->wowItemRepository->createQueryBuilder('i')
            ->where('i.icon IS NULL')
            ->setMaxResults($limit);

        /** @var \App\Entity\WowItem[] $itemsWithNoIcon */
        $itemsWithNoIcon = $qb->getQuery()->getResult();
        $total = count($itemsWithNoIcon);

        if ($total === 0) {
            $io->success('All items already have icon names populated!');
            return Command::SUCCESS;
        }

        $io->text(sprintf('Found %d items missing icons (processing limit: %d)...', $total, $limit));
        $io->progressStart($total);

        $itemIds = array_map(fn($item) => (int)$item->getItemId(), $itemsWithNoIcon);

        $batchSize = 10;
        $chunks = array_chunk($itemIds, $batchSize);
        $resolvedCount = 0;

        foreach ($chunks as $chunk) {
            $resolved = $this->iconResolverService->resolveIconsBulk($chunk);
            foreach ($resolved as $icon) {
                if (!empty($icon)) {
                    $resolvedCount++;
                }
            }
            $io->progressAdvance(count($chunk));
            usleep(200000); // 200ms delay to respect rate limits
        }

        $io->progressFinish();
        $io->success(sprintf('Successfully processed %d items! Resolved icons for %d items.', $total, $resolvedCount));

        return Command::SUCCESS;
    }
}
