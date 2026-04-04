<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\DatabaseExtension\Tests\Support;

use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Helpers for integration tests that iterate Doctrine connections (default, mysql, pgsql).
 * Unreachable engines are skipped so local runs without Docker databases still pass.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
trait RequiresDatabaseEnginesTrait
{
    /**
     * Use in foreach (['default', 'mysql', 'pgsql']) loops; returns null when the connection is unreachable.
     */
    protected function connectionForMultiEngineLoop(string $connectionName): ?Connection
    {
        return $this->tryConnectDoctrineRegistry($connectionName);
    }

    protected function tryConnectDoctrineRegistry(string $connectionName): ?Connection
    {
        /** @var ManagerRegistry $registry */
        $registry = static::getContainer()->get('doctrine');

        try {
            $resolvedConnection = $registry->getConnection($connectionName);
            if (!$resolvedConnection instanceof Connection) {
                return null;
            }

            $resolvedConnection->executeQuery('SELECT 1')->fetchOne();

            return $resolvedConnection;
        } catch (\Throwable) {
            return null;
        }
    }

    protected function assertAtLeastOneMultiEngineConnectionTested(int $testedCount): void
    {
        $this->assertGreaterThan(0, $testedCount, 'No reachable database connection was available.');
    }
}
