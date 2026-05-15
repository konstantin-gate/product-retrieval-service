<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Command;

use App\Infrastructure\Command\MigrateCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class MigrateCommandTest extends TestCase
{
    private \PDO $pdo;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', 'mysql', 3306, 'products');
        $this->pdo = new \PDO($dsn, 'root', 'secret');
        $this->pdo->exec('DROP TABLE IF EXISTS products');

        $application = new Application();
        $application->addCommand(new MigrateCommand($this->pdo));

        $command = $application->find('app:migrate');
        $this->commandTester = new CommandTester($command);
    }

    public function testExecute(): void
    {
        $this->commandTester->execute([]);

        $this->commandTester->assertCommandIsSuccessful();
        self::assertStringContainsString('Table "products" ensured.', $this->commandTester->getDisplay());

        // Check table exists
        $stmt = $this->pdo->query("SHOW TABLES LIKE 'products'");
        self::assertNotFalse($stmt->fetch());
    }

    public function testExecuteIdempotent(): void
    {
        $this->commandTester->execute([]);
        $this->commandTester->execute([]);

        $this->commandTester->assertCommandIsSuccessful();
        self::assertStringContainsString('Table "products" ensured.', $this->commandTester->getDisplay());
    }
}
