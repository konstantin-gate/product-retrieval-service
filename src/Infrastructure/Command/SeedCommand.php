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
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsCommand(name: 'app:seed', description: 'Generate fake product data')]
final class SeedCommand extends Command
{
    public function __construct(
        private ProductSeeder $seeder,
        private DataSeederService $dataSeeder,
        private int $defaultSeedCount,
        private ?TranslatorInterface $translator = null,
    ) {
        parent::__construct();
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function trans(string $id, array $parameters = []): string
    {
        if (null === $this->translator) {
            return $id;
        }

        return $this->translator->trans($id, $parameters);
    }

    protected function configure(): void
    {
        $this->setDescription($this->trans('cli.seed.description'));
        $this->addOption('count', 'c', InputOption::VALUE_REQUIRED, $this->trans('cli.seed.option_count'), (string) $this->defaultSeedCount);
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
        $output->writeln($this->trans('cli.seed.success', ['count' => $count]));

        return Command::SUCCESS;
    }
}
