<?php

namespace App\Command;

use App\Service\CatalogSync;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:catalog:sync',
    description: 'Met à jour le catalogue local des sets LEGO depuis Rebrickable (à lancer une fois par semaine)',
)]
class CatalogSyncCommand extends Command
{
    public function __construct(private readonly CatalogSync $sync)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->text('Téléchargement du catalogue Rebrickable…');

        $result = $this->sync->sync();

        $io->success(sprintf('%d sets importés, dont %d véhicules.', $result['total'], $result['vehicles']));

        return Command::SUCCESS;
    }
}
