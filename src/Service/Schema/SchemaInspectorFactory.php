<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\DatabaseExtension\Service\Schema;

use Doctrine\DBAL\Connection;
use MatesOfMate\DatabaseExtension\Exception\ToolUsageError;

class SchemaInspectorFactory
{
    public function __construct(
        private readonly MysqlSchemaInspector $mysqlSchemaInspector,
        private readonly PostgreSqlSchemaInspector $postgreSqlSchemaInspector,
        private readonly SqliteSchemaInspector $sqliteSchemaInspector,
    ) {
    }

    public function create(Connection $connection): DriverSchemaInspectorInterface
    {
        $params = $connection->getParams();
        $driver = isset($params['driver']) && \is_string($params['driver'])
            ? strtolower($params['driver'])
            : strtolower(basename(str_replace('\\', '/', $connection->getDatabasePlatform()::class)));

        return match (true) {
            str_contains($driver, 'mysql'), str_contains($driver, 'mariadb') => $this->mysqlSchemaInspector,
            str_contains($driver, 'pgsql'), str_contains($driver, 'postgres') => $this->postgreSqlSchemaInspector,
            str_contains($driver, 'sqlite') => $this->sqliteSchemaInspector,
            default => throw new ToolUsageError(message: \sprintf('Unsupported database driver "%s" for schema inspection.', $driver), hint: 'Use one of the supported v1 engines: MySQL, PostgreSQL, or SQLite.'),
        };
    }
}
