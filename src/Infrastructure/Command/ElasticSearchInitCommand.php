<?php

declare(strict_types=1);

namespace App\Infrastructure\Command;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Response\Elasticsearch;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsCommand(name: 'app:es:init', description: 'Initialize ElasticSearch products index')]
final class ElasticSearchInitCommand extends Command
{
    public function __construct(
        private Client $client,
        private string $esIndexName,
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
        $this->setDescription($this->trans('cli.es_init.description'));
        $this->addOption('force', null, InputOption::VALUE_NONE, $this->trans('cli.es_init.option_force'));
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $response = $this->client->indices()->exists(['index' => $this->esIndexName]);
            if (!$response instanceof Elasticsearch) {
                throw new \RuntimeException('Unexpected response type from ElasticSearch');
            }
            $exists = 200 === $response->getStatusCode();

            if ($exists) {
                if (!(bool) $input->getOption('force')) {
                    $output->writeln($this->trans('cli.es_init.exists'));

                    return Command::SUCCESS;
                }
                $this->client->indices()->delete(['index' => $this->esIndexName]);
                $output->writeln($this->trans('cli.es_init.deleted'));
            }

            $this->client->indices()->create([
                'index' => $this->esIndexName,
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

            $output->writeln($this->trans('cli.es_init.success', ['name' => $this->esIndexName]));

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('<error>'.$this->trans('cli.es_init.error', ['message' => $e->getMessage()]).'</error>');

            return Command::FAILURE;
        }
    }
}
