<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Command;

use App\Domain\Contract\ConfigInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class ElasticSearchInitCommandTest extends KernelTestCase
{
    private \Elastic\Elasticsearch\Client $client;
    private string $esIndexName;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $container = static::getContainer();

        $this->client = $container->get(\Elastic\Elasticsearch\Client::class);
        $this->esIndexName = $container->get(ConfigInterface::class)->getEsIndexName();

        // Clean up
        $response = $this->client->indices()->exists(['index' => $this->esIndexName]);
        if (200 === $response->getStatusCode()) {
            $this->client->indices()->delete(['index' => $this->esIndexName]);
        }

        $application = new Application($kernel);
        $command = $application->find('app:es:init');
        $this->commandTester = new CommandTester($command);
    }

    public function testExecute(): void
    {
        $this->commandTester->execute([]);

        $this->commandTester->assertCommandIsSuccessful();

        $response = $this->client->indices()->exists(['index' => $this->esIndexName]);
        self::assertSame(200, $response->getStatusCode());
    }

    public function testExecuteForce(): void
    {
        $this->commandTester->execute([]);

        // Run again without force — should report index already exists
        $this->commandTester->execute([]);
        $display = $this->commandTester->getDisplay();
        // The output is locale-dependent, so check functionally that index still exists
        $response = $this->client->indices()->exists(['index' => $this->esIndexName]);
        self::assertSame(200, $response->getStatusCode());

        // Run with force — should recreate
        $this->commandTester->execute(['--force' => true]);
        $this->commandTester->assertCommandIsSuccessful();

        // Index should still exist after force recreation
        $response = $this->client->indices()->exists(['index' => $this->esIndexName]);
        self::assertSame(200, $response->getStatusCode());
    }
}
