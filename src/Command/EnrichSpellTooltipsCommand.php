<?php

namespace App\Command;

use App\Repository\WowSpellTooltipRepository;
use App\Service\SpellTooltipEnrichmentService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

#[AsCommand(
    name: 'app:enrich-spell-tooltips',
    description: 'Fetch missing WotLK talent spell tooltip text for stored character snapshots.',
)]
final class EnrichSpellTooltipsCommand extends Command
{
    private const REQUEST_GAP_MICROSECONDS = 500_000;

    private readonly \Closure $pause;

    public function __construct(
        private readonly WowSpellTooltipRepository $repository,
        private readonly SpellTooltipEnrichmentService $enrichmentService,
        ?\Closure $pause = null,
    ) {
        parent::__construct();
        $this->pause = $pause ?? usleep(...);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Runs here rather than through Messenger: a backfill burst on the async
        // transport would hold up character refreshes behind it.
        $spellIds = $this->repository->findQueueableSpellIds($this->repository->findTalentSpellIdsFromSnapshots());
        $saved = 0;
        $notFound = 0;
        $failed = 0;

        foreach ($spellIds as $spellId) {
            try {
                $this->enrichmentService->enrich($spellId) ? $saved++ : $notFound++;
            } catch (Throwable $exception) {
                $failed++;
                $output->writeln(sprintf('<error>Spell #%d: %s</error>', $spellId, $exception->getMessage()));
            }
            ($this->pause)(self::REQUEST_GAP_MICROSECONDS);
        }

        $io = new SymfonyStyle($input, $output);
        $io->success(sprintf('Saved %d talent spell tooltips, %d not found, %d failed.', $saved, $notFound, $failed));

        return $failed === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
