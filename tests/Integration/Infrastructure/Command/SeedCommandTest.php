<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Command;

use App\Infrastructure\Command\SeedCommand;
use App\Infrastructure\Seeder\ProductSeeder;
use App\Infrastructure\Service\DataSeederService;
use Elastic\Elasticsearch\ClientBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class SeedCommandTest extends TestCase
{
    private \PDO $pdo;
    private \Elastic\Elasticsearch\Client $client;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', 'mysql', 3306, 'products');
        $this->pdo = new \PDO($dsn, 'root', 'secret');
        $this->pdo->exec('DELETE FROM products');

        $this->client = ClientBuilder::create()
            ->setHosts(['http://elasticsearch:9200'])
            ->build();

        // Ensure index exists
        $response = $this->client->indices()->exists(['index' => 'products']);
        if (200 !== $response->getStatusCode()) {
            $this->client->indices()->create([
                'index' => 'products',
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
            'index' => 'products',
            'body' => ['query' => ['match_all' => (object) []]],
            'refresh' => true,
        ]);

        $seeder = new ProductSeeder();
        $dataSeeder = new DataSeederService($this->pdo, $this->client);

        $translator = $this->createMock(\Symfony\Contracts\Translation\TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(function (string $id, array $parameters = []): string {
            return str_replace(['{count}'], (string) ($parameters['count'] ?? '?'), match ($id) {
                'cli.seed.success' => 'Successfully seeded {count} products.',
                'cli.seed.option_count' => 'Number of products',
                'cli.seed.description' => 'Generate fake product data',
                default => $id,
            });
        });

        $application = new Application();
        $application->addCommand(new SeedCommand($seeder, $dataSeeder, 10, $translator));

        $command = $application->find('app:seed');
        $this->commandTester = new CommandTester($command);
    }

    public function testExecute(): void
    {
        $this->commandTester->execute(['--count' => 20]);

        $this->commandTester->assertCommandIsSuccessful();
        self::assertStringContainsString('Successfully seeded 20 products.', $this->commandTester->getDisplay());

        // Check MySQL
        $stmt = $this->pdo->query('SELECT COUNT(*) FROM products');
        self::assertEquals(20, $stmt->fetchColumn());

        // Check ES
        $this->client->indices()->refresh(['index' => 'products']);
        $response = $this->client->count(['index' => 'products']);
        self::assertEquals(20, $response['count']);
    }
}
