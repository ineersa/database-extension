<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\DatabaseExtension\Tests\Fixtures\Database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;

class DatabaseTestFixtures
{
    public static function reset(Connection $connection): void
    {
        $writableConnection = self::createWritableConnection($connection);

        self::executeSilently($writableConnection, 'DROP VIEW IF EXISTS active_users');
        self::executeSilently($writableConnection, 'DROP TABLE IF EXISTS users');

        $writableConnection->executeStatement('CREATE TABLE users (id INTEGER PRIMARY KEY, email VARCHAR(255) NOT NULL, status VARCHAR(32) NOT NULL)');

        $writableConnection->insert('users', ['id' => 1, 'email' => 'alice@example.com', 'status' => 'active']);
        $writableConnection->insert('users', ['id' => 2, 'email' => 'bob@example.com', 'status' => 'active']);
        $writableConnection->insert('users', ['id' => 3, 'email' => 'charlie@example.com', 'status' => 'inactive']);

        $writableConnection->executeStatement("CREATE VIEW active_users AS SELECT id, email FROM users WHERE status = 'active'");
        $writableConnection->close();
    }

    private static function executeSilently(Connection $connection, string $sql): void
    {
        try {
            $connection->executeStatement($sql);
        } catch (\Throwable) {
            // Best effort teardown for cross-database fixture reset.
        }
    }

    private static function createWritableConnection(Connection $connection): Connection
    {
        $params = $connection->getParams();

        $configuration = clone $connection->getConfiguration();
        $configuration->setMiddlewares([]);

        return DriverManager::getConnection($params, $configuration);
    }
}
