<?php

declare(strict_types=1);

namespace App\Infrastructure\Command;

use Elastic\Elasticsearch\Client;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:es:init', description: 'Initialize ElasticSearch products index')]
final class ElasticSearchInitCommand extends Command
{
    private const INDEX_NAME = 'products';

    public function __construct(private Client $client)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Recreate index if it exists');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $exists = 200 === $this->client->indices()->exists(['index' => self::INDEX_NAME])->getStatusCode();

            if ($exists) {
                if (!(bool) $input->getOption('force')) {
                    $output->writeln('Index already exists. Use --force to recreate.');

                    return Command::SUCCESS;
                }
                $this->client->indices()->delete(['index' => self::INDEX_NAME]);
                $output->writeln('Existing index deleted.');
            }

            $this->client->indices()->create([
                'index' => self::INDEX_NAME,
                'body' => [
                    'mappings' => [
                        'properties' => [
                            'id' => ['type' => 'keyword'],
                            'name' => ['type' => 'text'],
                            'price' => ['type' => 'long'],
                            'description' => ['type' => 'text'],
                        ],
                    ],
                ],
            ]);

            $output->writeln('Index "'.self::INDEX_NAME.'" created successfully.');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('<error>ES init failed: '.$e->getMessage().'</error>');

            return Command::FAILURE;
        }
    }
}
