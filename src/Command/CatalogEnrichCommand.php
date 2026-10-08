<?php

namespace App\Command;

use App\Repository\CatalogSetRepository;
use App\Repository\SetRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:catalog:enrich',
    description: 'Complète les sets de la collection (pièces, thème, année, image) depuis le catalogue local',
)]
class CatalogEnrichCommand extends Command
{
    public function __construct(
        private readonly SetRepository $setRepository,
        private readonly CatalogSetRepository $catalogRepository,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('force', null, InputOption::VALUE_NONE, 'Remplace aussi les valeurs déjà renseignées (sauf le nom)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Affiche ce qui changerait sans rien enregistrer');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = (bool) $input->getOption('force');

        if ($this->catalogRepository->isEmpty()) {
            $io->error('Catalogue vide : lance d\'abord php bin/console app:catalog:sync');

            return Command::FAILURE;
        }

        $updated = 0;
        $missing = [];
        $notVehicles = [];

        foreach ($this->setRepository->findAll() as $set) {
            $catalog = $this->catalogRepository->findOneByNumero($set->getNumeroSet());
            if (!$catalog) {
                $missing[] = $set->getNumeroSet().' – '.$set->getNom();
                continue;
            }
            if (!$catalog->isVehicle()) {
                $notVehicles[] = $set->getNumeroSet().' – '.$set->getNom();
            }

            $before = [$set->getPieces(), $set->getTheme(), $set->getAnnee(), $set->getImageUrl()];
            if ($force || $set->getPieces() === null) {
                $set->setPieces($catalog->getParts());
            }
            if ($force || !$set->getTheme()) {
                $set->setTheme($catalog->getTheme());
            }
            if ($force || !$set->getAnnee()) {
                $set->setAnnee($catalog->getYear());
            }
            if (($force || !$set->getImageUrl()) && $catalog->getImgUrl()) {
                $set->setImageUrl($catalog->getImgUrl());
            }
            if ($before !== [$set->getPieces(), $set->getTheme(), $set->getAnnee(), $set->getImageUrl()]) {
                ++$updated;
            }
        }

        if (!$input->getOption('dry-run')) {
            $this->em->flush();
        }

        $io->success(sprintf('%d set(s) complété(s)%s.', $updated, $input->getOption('dry-run') ? ' (simulation, rien enregistré)' : ''));
        if ($missing) {
            $io->warning('Absents du catalogue Rebrickable :');
            $io->listing($missing);
        }
        if ($notVehicles) {
            $io->note('Ces sets ne sont pas détectés comme véhicules (à vérifier) :');
            $io->listing($notVehicles);
        }

        return Command::SUCCESS;
    }
}
