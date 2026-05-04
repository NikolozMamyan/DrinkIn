<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\DemoCatalogService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:seed-demo-data', description: 'Seed catalogue demo data into the database.')]
final class SeedDemoDataCommand extends Command
{
    public function __construct(
        private readonly DemoCatalogService $catalogService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $this->catalogService->seedIfEmpty();
        $io->success('Catalogue data seeded if the database was empty.');

        return Command::SUCCESS;
    }
}
