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
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class SqliteSchemaInspector implements DriverSchemaInspectorInterface
{
    use SchemaObjectNameExtractorTrait;

    public function __construct(private LoggerInterface $logger = new NullLogger())
    {
    }

    /** @return list<string> */
    public function getStoredProcedures(Connection $connection): array
    {
        return [];
    }

    /** @return list<string> */
    public function getFunctions(Connection $connection): array
    {
        return [];
    }

    /** @return list<string> */
    public function getTriggers(Connection $connection): array
    {
        try {
            $rows = $connection->executeQuery("SELECT name FROM sqlite_master WHERE type = 'trigger'")->fetchAllAssociative();

            return $this->extractObjectNames($rows, 'name');
        } catch (\Throwable $exception) {
            $this->logger->warning('Failed to get triggers list', ['error' => $exception->getMessage()]);

            return [];
        }
    }

    /** @return list<array<string, mixed>> */
    public function getTableTriggers(Connection $connection, string $tableName): array
    {
        try {
            return $connection->executeQuery(
                "SELECT name, sql AS statement FROM sqlite_master WHERE type = 'trigger' AND tbl_name = ?",
                [$tableName]
            )->fetchAllAssociative();
        } catch (\Throwable $exception) {
            $this->logger->warning('Failed to get triggers', ['table' => $tableName, 'error' => $exception->getMessage()]);

            return [];
        }
    }

    /** @return list<array<string, mixed>> */
    public function getTableCheckConstraints(Connection $connection, string $tableName): array
    {
        return [];
    }

    public function getStoredProcedureDefinition(Connection $connection, string $procedureName): ?string
    {
        return null;
    }

    public function getFunctionDefinition(Connection $connection, string $functionName): ?string
    {
        return null;
    }

    public function getTriggerDefinition(Connection $connection, string $triggerName): ?string
    {
        try {
            $definition = $connection->executeQuery(
                "SELECT sql FROM sqlite_master WHERE type = 'trigger' AND name = ?",
                [$triggerName]
            )->fetchOne();

            return \is_string($definition) ? $definition : null;
        } catch (\Throwable $exception) {
            $this->logger->warning('Failed to get trigger definition', ['trigger' => $triggerName, 'error' => $exception->getMessage()]);

            return null;
        }
    }
}
