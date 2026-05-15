<?php

declare(strict_types=1);

namespace App\Infrastructure\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:migrate', description: 'Create MySQL products table')]
final class MigrateCommand extends Command
{
    private const CREATE_TABLE_SQL = <<<'SQL'
CREATE TABLE IF NOT EXISTS products (
    id CHAR(36) NOT NULL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    price BIGINT NOT NULL,
    description TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

    public function __construct(private \PDO $pdo)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->pdo->exec(self::CREATE_TABLE_SQL);
            $output->writeln('Table "products" ensured.');

            return Command::SUCCESS;
        } catch (\PDOException $e) {
            $output->writeln('<error>Migration failed: '.$e->getMessage().'</error>');

            return Command::FAILURE;
        }
    }
}
