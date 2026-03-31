<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\DatabaseExtension\Tests\Unit;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use MatesOfMate\DatabaseExtension\Exception\ToolUsageError;
use MatesOfMate\DatabaseExtension\Service\DatabaseSchemaService;
use MatesOfMate\DatabaseExtension\Service\Schema\MysqlSchemaInspector;
use MatesOfMate\DatabaseExtension\Service\Schema\PostgreSqlSchemaInspector;
use MatesOfMate\DatabaseExtension\Service\Schema\SchemaInspectorFactory;
use MatesOfMate\DatabaseExtension\Service\Schema\SqliteSchemaInspector;
use PHPUnit\Framework\TestCase;

class DatabaseSchemaServiceTest extends TestCase
{
    public function testRejectsInvalidDetailValue(): void
    {
        $service = $this->createService();

        try {
            $service->getSchemaStructure(
                'default',
                $this->createSqliteConnection(),
                'sqlite',
                '',
                'deep',
                'contains',
                false,
                false,
            );

            $this->fail('Expected ToolUsageError was not thrown.');
        } catch (ToolUsageError $error) {
            $this->assertSame('Invalid detail value "deep".', $error->getMessage());
            $this->assertSame('Use one of: summary, columns, full.', $error->getHint());
        }
    }

    public function testRejectsInvalidMatchModeValue(): void
    {
        $service = $this->createService();

        try {
            $service->getSchemaStructure(
                'default',
                $this->createSqliteConnection(),
                'sqlite',
                '',
                'summary',
                'starts-with',
                false,
                false,
            );

            $this->fail('Expected ToolUsageError was not thrown.');
        } catch (ToolUsageError $error) {
            $this->assertSame('Invalid matchMode value "starts-with".', $error->getMessage());
            $this->assertSame('Use one of: contains, prefix, exact, glob.', $error->getHint());
        }
    }

    public function testReturnsSummaryTableNamesForSqliteConnection(): void
    {
        $connection = $this->createSqliteConnection();
        $connection->executeStatement('CREATE TABLE users (id INTEGER PRIMARY KEY, email TEXT NOT NULL)');

        $service = $this->createService();
        $result = $service->getSchemaStructure(
            'default',
            $connection,
            'sqlite',
            '',
            'summary',
            'contains',
            false,
            false,
        );

        $this->assertSame('default', $result['connection']);
        $this->assertSame('sqlite', $result['engine']);

        $normalizedTableNames = array_map(
            static fn (string $tableName): string => trim($tableName, '"\'` '),
            $result['tables'],
        );

        $this->assertContains('users', $normalizedTableNames);
    }

    private function createService(): DatabaseSchemaService
    {
        return new DatabaseSchemaService(
            new SchemaInspectorFactory(
                new MysqlSchemaInspector(),
                new PostgreSqlSchemaInspector(),
                new SqliteSchemaInspector(),
            ),
        );
    }

    private function createSqliteConnection(): Connection
    {
        return DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ], new Configuration());
    }
}
