<?php

namespace App\Command;

use App\Repository\CharacterSnapshotRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:purge-snapshots',
    description: 'Purge character snapshots that have not been updated for a number of days.',
)]
class PurgeOldSnapshotsCommand extends Command
{
    private const DEFAULT_DAYS = 60;

    public function __construct(
        private readonly CharacterSnapshotRepository $snapshotRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('days', null, InputOption::VALUE_REQUIRED, 'Delete snapshots not updated for this many days.', (string) self::DEFAULT_DAYS)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Only report how many snapshots would be deleted.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $days = filter_var($input->getOption('days'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($days === false) {
            $io->error('--days must be a positive whole number.');

            return Command::INVALID;
        }

        $cutoff = (new \DateTimeImmutable())->modify(sprintf('-%d days', $days));
        if ($input->getOption('dry-run')) {
            $io->note(sprintf(
                'Would purge %d snapshot(s) not updated since %s.',
                $this->snapshotRepository->countOlderThan($cutoff),
                $cutoff->format('Y-m-d H:i:s'),
            ));

            return Command::SUCCESS;
        }

        $deletedCount = $this->snapshotRepository->purgeOlderThan($cutoff);

        $io->success(sprintf('Purged %d snapshot(s) not updated since %s.', $deletedCount, $cutoff->format('Y-m-d H:i:s')));

        return Command::SUCCESS;
    }
}
