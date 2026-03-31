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

interface DriverSchemaInspectorInterface
{
    /** @return list<string> */
    public function getStoredProcedures(Connection $connection): array;

    /** @return list<string> */
    public function getFunctions(Connection $connection): array;

    /** @return list<string> */
    public function getTriggers(Connection $connection): array;

    /** @return list<array<string, mixed>> */
    public function getTableTriggers(Connection $connection, string $tableName): array;

    /** @return list<array<string, mixed>> */
    public function getTableCheckConstraints(Connection $connection, string $tableName): array;

    public function getStoredProcedureDefinition(Connection $connection, string $procedureName): ?string;

    public function getFunctionDefinition(Connection $connection, string $functionName): ?string;

    public function getTriggerDefinition(Connection $connection, string $triggerName): ?string;
}
