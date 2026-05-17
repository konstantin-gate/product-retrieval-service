<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Command;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class MigrateCommandTest extends KernelTestCase
{
    private \PDO $pdo;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $pdo = static::getContainer()->get(\PDO::class);
        if (!$pdo instanceof \PDO) {
            throw new \RuntimeException('PDO service not found');
        }
        $this->pdo = $pdo;
        $this->pdo->exec('DROP TABLE IF EXISTS products');

        $application = new Application($kernel);
        $command = $application->find('app:migrate');
        $this->commandTester = new CommandTester($command);
    }

    public function testExecute(): void
    {
        $this->commandTester->execute([]);

        $this->commandTester->assertCommandIsSuccessful();

        // Check table exists
        $stmt = $this->pdo->query("SHOW TABLES LIKE 'products'");
        if (!$stmt instanceof \PDOStatement) {
            self::fail('Failed to query tables');
        }
        self::assertNotFalse($stmt->fetch());
    }

    public function testExecuteIdempotent(): void
    {
        $this->commandTester->execute([]);
        $this->commandTester->execute([]);

        $this->commandTester->assertCommandIsSuccessful();
    }
}
