<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\DatabaseExtension\ReadOnly;

use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;

class ReadOnlyDriver extends AbstractDriverMiddleware
{
    public function connect(
        #[\SensitiveParameter]
        array $params,
    ): DriverConnection {
        $driver = isset($params['driver']) && \is_string($params['driver'])
            ? strtolower($params['driver'])
            : '';

        $connection = parent::connect($params);

        $readOnlyQuery = $this->getReadOnlyQuery($driver);
        if (null !== $readOnlyQuery) {
            try {
                $connection->exec($readOnlyQuery);
            } catch (\Throwable) {
                // Connection-level read-only commands are best-effort.
            }
        }

        return new ReadOnlyConnection($connection);
    }

    private function getReadOnlyQuery(string $driver): ?string
    {
        return match (true) {
            str_contains($driver, 'mysql'), str_contains($driver, 'mariadb') => 'SET SESSION transaction_read_only = 1',
            str_contains($driver, 'pgsql'), str_contains($driver, 'postgres') => 'SET default_transaction_read_only = on',
            str_contains($driver, 'sqlite') => 'PRAGMA query_only = ON',
            default => null,
        };
    }
}
