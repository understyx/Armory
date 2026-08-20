<?php

namespace App\Command;

use App\Repository\CharacterSnapshotRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:purge-snapshots',
    description: 'Purge character snapshots older than 1 month.',
)]
class PurgeOldSnapshotsCommand extends Command
{
    public function __construct(
        private readonly CharacterSnapshotRepository $snapshotRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $cutoff = (new \DateTimeImmutable())->modify('-1 month');

        $deletedCount = $this->snapshotRepository->purgeOlderThan($cutoff);

        $io->success(sprintf('Purged %d snapshot(s) older than %s.', $deletedCount, $cutoff->format('Y-m-d H:i:s')));

        return Command::SUCCESS;
    }
}
