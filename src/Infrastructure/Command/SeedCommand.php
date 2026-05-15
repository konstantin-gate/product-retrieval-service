<?php

declare(strict_types=1);

namespace App\Infrastructure\Command;

use App\Infrastructure\Seeder\ProductSeeder;
use App\Infrastructure\Service\DataSeederService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:seed', description: 'Generate fake product data')]
final class SeedCommand extends Command
{
    public function __construct(
        private ProductSeeder $seeder,
        private DataSeederService $dataSeeder,
        private int $defaultSeedCount,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('count', 'c', InputOption::VALUE_REQUIRED, 'Number of products', (string) $this->defaultSeedCount);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $count = (int) $input->getOption('count');

        $progressBar = new ProgressBar($output, $count);
        $progressBar->start();

        $products = $this->seeder->generate($count);
        $this->dataSeeder->seed($products, function (int $chunkSize) use ($progressBar): void {
            $progressBar->advance($chunkSize);
        });

        $progressBar->finish();
        $output->writeln('');
        $output->writeln("Successfully seeded {$count} products.");

        return Command::SUCCESS;
    }
}
