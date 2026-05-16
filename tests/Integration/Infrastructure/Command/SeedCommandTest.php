<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Command;

use App\Domain\Contract\ConfigInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class SeedCommandTest extends KernelTestCase
{
    private \PDO $pdo;
    private \Elastic\Elasticsearch\Client $client;
    private string $esIndexName;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $container = static::getContainer();

        $this->pdo = $container->get(\PDO::class);
        $this->client = $container->get(\Elastic\Elasticsearch\Client::class);
        $this->esIndexName = $container->get(ConfigInterface::class)->getEsIndexName();

        $this->pdo->exec('DELETE FROM products');

        // Ensure ES index exists
        $response = $this->client->indices()->exists(['index' => $this->esIndexName]);
        if (200 !== $response->getStatusCode()) {
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
        }
        $this->client->deleteByQuery([
            'index' => $this->esIndexName,
            'body' => ['query' => ['match_all' => (object) []]],
            'refresh' => true,
        ]);

        $application = new Application($kernel);
        $command = $application->find('app:seed');
        $this->commandTester = new CommandTester($command);
    }

    public function testExecute(): void
    {
        $this->commandTester->execute(['--count' => 20]);

        $this->commandTester->assertCommandIsSuccessful();

        // Check MySQL
        $stmt = $this->pdo->query('SELECT COUNT(*) FROM products');
        self::assertEquals(20, $stmt->fetchColumn());

        // Check ES
        $this->client->indices()->refresh(['index' => $this->esIndexName]);
        $response = $this->client->count(['index' => $this->esIndexName]);
        self::assertEquals(20, $response['count']);
    }
}
