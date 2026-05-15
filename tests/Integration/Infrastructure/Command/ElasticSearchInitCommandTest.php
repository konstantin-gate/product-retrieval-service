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

        $application = new Application();
        $application->addCommand(new ElasticSearchInitCommand($this->client));

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
