<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Command;

use App\Infrastructure\Command\ElasticSearchInitCommand;
use Elastic\Elasticsearch\ClientBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class ElasticSearchInitCommandTest extends TestCase
{
    private \Elastic\Elasticsearch\Client $client;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->client = ClientBuilder::create()
            ->setHosts(['http://elasticsearch:9200'])
            ->build();

        // Clean up
        $response = $this->client->indices()->exists(['index' => 'products']);
        if (200 === $response->getStatusCode()) {
            $this->client->indices()->delete(['index' => 'products']);
        }

        $translator = $this->createMock(\Symfony\Contracts\Translation\TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(function (string $id, array $parameters = []): string {
            return match ($id) {
                'cli.es_init.exists' => 'Index already exists. Use --force to recreate.',
                'cli.es_init.deleted' => 'Existing index deleted.',
                'cli.es_init.success' => 'Index "'.($parameters['name'] ?? 'products').'" created successfully.',
                'cli.es_init.error' => 'ES init failed: '.($parameters['message'] ?? ''),
                'cli.es_init.description' => 'Initialize ElasticSearch products index',
                'cli.es_init.option_force' => 'Recreate index if it exists',
                default => $id,
            };
        });

        $application = new Application();
        $application->addCommand(new ElasticSearchInitCommand($this->client, $translator));

        $command = $application->find('app:es:init');
        $this->commandTester = new CommandTester($command);
    }

    public function testExecute(): void
    {
        $this->commandTester->execute([]);

        $this->commandTester->assertCommandIsSuccessful();
        self::assertStringContainsString('Index "products" created successfully.', $this->commandTester->getDisplay());

        $response = $this->client->indices()->exists(['index' => 'products']);
        self::assertSame(200, $response->getStatusCode());
    }

    public function testExecuteForce(): void
    {
        $this->commandTester->execute([]);

        // Run again without force
        $this->commandTester->execute([]);
        self::assertStringContainsString('Index already exists.', $this->commandTester->getDisplay());

        // Run with force
        $this->commandTester->execute(['--force' => true]);
        self::assertStringContainsString('Existing index deleted.', $this->commandTester->getDisplay());
        self::assertStringContainsString('Index "products" created successfully.', $this->commandTester->getDisplay());
    }
}
