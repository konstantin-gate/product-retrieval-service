<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Command;

use App\Domain\Contract\ConfigInterface;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Response\Elasticsearch;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class SeedCommandTest extends KernelTestCase
{
    private \PDO $pdo;
    private Client $client;
    private string $esIndexName;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $container = static::getContainer();

        $pdo = $container->get(\PDO::class);
        if (!$pdo instanceof \PDO) {
            throw new \RuntimeException('PDO service not found');
        }
        $this->pdo = $pdo;

        $client = $container->get(Client::class);
        if (!$client instanceof Client) {
            throw new \RuntimeException('ElasticSearch client not found');
        }
        $this->client = $client;

        $config = $container->get(ConfigInterface::class);
        if (!$config instanceof ConfigInterface) {
            throw new \RuntimeException('Config service not found');
        }
        $this->esIndexName = $config->getEsIndexName();

        $this->pdo->exec('DELETE FROM products');

        // Ensure ES index exists
        $response = $this->client->indices()->exists(['index' => $this->esIndexName]);
        if (!$response instanceof Elasticsearch) {
            throw new \RuntimeException('Unexpected response type from ES');
        }

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
        if (!$stmt instanceof \PDOStatement) {
            self::fail('Failed to query MySQL');
        }
        self::assertEquals(20, $stmt->fetchColumn());

        // Check ES
        $this->client->indices()->refresh(['index' => $this->esIndexName]);
        $response = $this->client->count(['index' => $this->esIndexName]);
        if (!$response instanceof Elasticsearch) {
            throw new \RuntimeException('Unexpected response type from ES count');
        }
        self::assertEquals(20, $response['count']);
    }
}
