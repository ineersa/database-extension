<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\DatabaseExtension\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use MatesOfMate\DatabaseExtension\Enum\SchemaDetail;
use MatesOfMate\DatabaseExtension\Enum\SchemaMatchMode;
use MatesOfMate\DatabaseExtension\Exception\ToolUsageError;
use MatesOfMate\DatabaseExtension\Service\Schema\DriverSchemaInspectorInterface;
use MatesOfMate\DatabaseExtension\Service\Schema\SchemaInspectorFactory;

class DatabaseSchemaService
{
    public function __construct(
        private readonly SchemaInspectorFactory $schemaInspectorFactory,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getSchemaStructure(
        string $connectionName,
        Connection $connection,
        string $engineName,
        string $filter,
        string $detail,
        string $matchMode,
        bool $includeViews,
        bool $includeRoutines,
    ): array {
        $normalizedDetail = $this->normalizeDetail($detail);
        $normalizedMatchMode = $this->normalizeMatchMode($matchMode);
        $includeDefinitions = SchemaDetail::FULL->value === $normalizedDetail;
        $schemaInspector = $this->schemaInspectorFactory->create($connection);

        $result = [
            'connection' => $connectionName,
            'engine' => $engineName,
            'detail' => $normalizedDetail,
            'match_mode' => $normalizedMatchMode,
            'tables' => match ($normalizedDetail) {
                SchemaDetail::SUMMARY->value => $this->getAllTableNames($connection, $filter, $normalizedMatchMode),
                SchemaDetail::COLUMNS->value => $this->getAllTableColumnsStructure($connection, $filter, $normalizedMatchMode),
                default => $this->getAllTablesStructure($connection, $schemaInspector, $filter, $normalizedMatchMode, $includeDefinitions),
            },
        ];

        if ($includeViews) {
            $result['views'] = SchemaDetail::FULL->value === $normalizedDetail
                ? $this->getViewsStructure($connection, $filter, $normalizedMatchMode, $includeDefinitions)
                : $this->getViewNames($connection, $filter, $normalizedMatchMode);
        }

        if ($includeRoutines) {
            $routines = [
                'stored_procedures' => SchemaDetail::FULL->value === $normalizedDetail
                    ? $this->getStoredProceduresStructure($connection, $schemaInspector, $filter, $normalizedMatchMode, $includeDefinitions)
                    : $this->getStoredProceduresNames($connection, $schemaInspector, $filter, $normalizedMatchMode),
                'functions' => SchemaDetail::FULL->value === $normalizedDetail
                    ? $this->getFunctionsStructure($connection, $schemaInspector, $filter, $normalizedMatchMode, $includeDefinitions)
                    : $this->getFunctionsNames($connection, $schemaInspector, $filter, $normalizedMatchMode),
                'sequences' => SchemaDetail::FULL->value === $normalizedDetail
                    ? $this->getSequencesStructure($connection, $filter, $normalizedMatchMode)
                    : $this->getSequencesNames($connection, $filter, $normalizedMatchMode),
            ];

            if (SchemaDetail::FULL->value !== $normalizedDetail) {
                $routines['triggers'] = $this->getTriggersNames($connection, $schemaInspector, $filter, $normalizedMatchMode);
            }

            $result['routines'] = $routines;
        }

        return $result;
    }

    /**
     * @return list<string>
     */
    public function getViewsList(Connection $connection): array
    {
        $views = [];
        foreach ($connection->createSchemaManager()->introspectViews() as $view) {
            $views[] = $view->getObjectName()->toString();
        }

        return $views;
    }

    /**
     * @return array{stored_procedures: list<string>, functions: list<string>, sequences: list<string>, triggers: list<string>}
     */
    public function getRoutinesList(Connection $connection): array
    {
        $schemaInspector = $this->schemaInspectorFactory->create($connection);

        return [
            'stored_procedures' => $schemaInspector->getStoredProcedures($connection),
            'functions' => $schemaInspector->getFunctions($connection),
            'sequences' => $this->getSequencesNames($connection, '', SchemaMatchMode::CONTAINS->value),
            'triggers' => $schemaInspector->getTriggers($connection),
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function getAllTablesStructure(Connection $connection, DriverSchemaInspectorInterface $schemaInspector, string $filter, string $matchMode, bool $includeDefinitions): array
    {
        $schemaManager = $connection->createSchemaManager();
        $structures = [];

        foreach ($schemaManager->introspectTables() as $table) {
            $tableName = $table->getObjectName()->toString();
            $tableNameMatchesFilter = $this->matchesFilter($tableName, $filter, $matchMode);

            $columns = [];
            foreach ($table->getColumns() as $column) {
                $details = [
                    'type' => $this->getTypeLabel($column->getType()),
                    'nullable' => !$column->getNotnull(),
                    'default' => $column->getDefault(),
                    'auto_increment' => $column->getAutoincrement(),
                ];

                $comment = $column->getComment();
                if (null !== $comment && '' !== $comment) {
                    $details['comment'] = $comment;
                }

                $columns[$this->unquoteIdentifier($column->getObjectName()->toString())] = $details;
            }

            $primaryKey = $table->getPrimaryKeyConstraint();

            $indexes = [];
            foreach ($table->getIndexes() as $index) {
                $indexName = $index->getObjectName()->toString();
                $indexType = $index->getType();
                $indexedColumns = array_map(
                    static fn ($column): string => $column->getColumnName()->toString(),
                    $index->getIndexedColumns()
                );

                $indexes[$indexName] = [
                    'columns' => $indexedColumns,
                    'is_unique' => IndexType::UNIQUE === $indexType,
                    'is_primary' => $this->isPrimaryIndex($indexedColumns, $primaryKey),
                ];
            }

            $foreignKeys = [];
            foreach ($table->getForeignKeys() as $foreignKey) {
                $foreignKeyName = $foreignKey->getObjectName()?->toString() ?? '';
                $foreignKeys[$foreignKeyName] = [
                    'local_columns' => array_map(
                        static fn ($columnName): string => $columnName->toString(),
                        $foreignKey->getReferencingColumnNames()
                    ),
                    'foreign_table' => $foreignKey->getReferencedTableName()->toString(),
                    'foreign_columns' => array_map(
                        static fn ($columnName): string => $columnName->toString(),
                        $foreignKey->getReferencedColumnNames()
                    ),
                ];
            }

            $rawTableName = trim($tableName, '"\'` ');
            $tableTriggers = $schemaInspector->getTableTriggers($connection, $rawTableName);
            $matchingTriggers = $this->filterTableTriggers($tableTriggers, $filter, $matchMode);

            if (!$tableNameMatchesFilter && '' !== trim($filter) && [] === $matchingTriggers) {
                continue;
            }

            $triggersToReturn = $tableNameMatchesFilter || '' === trim($filter)
                ? $tableTriggers
                : $matchingTriggers;

            if ($includeDefinitions) {
                $triggersToReturn = $this->enrichTriggersWithDefinitions($connection, $schemaInspector, $triggersToReturn);
            }

            $structures[$tableName] = [
                'columns' => $columns,
                'indexes' => $indexes,
                'foreign_keys' => $foreignKeys,
                'triggers' => $triggersToReturn,
                'check_constraints' => $schemaInspector->getTableCheckConstraints($connection, $rawTableName),
            ];
        }

        return $structures;
    }

    /**
     * @return list<string>
     */
    private function getAllTableNames(Connection $connection, string $filter, string $matchMode): array
    {
        $tableNames = [];

        foreach ($connection->createSchemaManager()->introspectTables() as $table) {
            $tableName = $table->getObjectName()->toString();
            if (!$this->matchesFilter($tableName, $filter, $matchMode)) {
                continue;
            }

            $tableNames[] = $tableName;
        }

        return $tableNames;
    }

    /**
     * @return array<string, array{columns: array<string, string>}>
     */
    private function getAllTableColumnsStructure(Connection $connection, string $filter, string $matchMode): array
    {
        $tables = [];

        foreach ($connection->createSchemaManager()->introspectTables() as $table) {
            $tableName = $table->getObjectName()->toString();

            if (!$this->matchesFilter($tableName, $filter, $matchMode)) {
                continue;
            }

            $columns = [];
            foreach ($table->getColumns() as $column) {
                $columns[$this->unquoteIdentifier($column->getObjectName()->toString())] = $this->getTypeLabel($column->getType());
            }

            $tables[$tableName] = [
                'columns' => $columns,
            ];
        }

        return $tables;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function getViewsStructure(Connection $connection, string $filter, string $matchMode, bool $includeDefinitions): array
    {
        $views = [];

        foreach ($connection->createSchemaManager()->introspectViews() as $view) {
            $viewName = $view->getObjectName()->toString();
            if (!$this->matchesFilter($viewName, $filter, $matchMode)) {
                continue;
            }

            $views[$viewName] = [
                'type' => 'view',
            ];

            if ($includeDefinitions) {
                $views[$viewName]['definition'] = $view->getSql();
            }
        }

        return $views;
    }

    /**
     * @return list<string>
     */
    private function getViewNames(Connection $connection, string $filter, string $matchMode): array
    {
        $viewNames = [];

        foreach ($connection->createSchemaManager()->introspectViews() as $view) {
            $viewName = $view->getObjectName()->toString();
            if (!$this->matchesFilter($viewName, $filter, $matchMode)) {
                continue;
            }

            $viewNames[] = $viewName;
        }

        return $viewNames;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function getStoredProceduresStructure(Connection $connection, DriverSchemaInspectorInterface $schemaInspector, string $filter, string $matchMode, bool $includeDefinitions): array
    {
        $structures = [];

        foreach ($schemaInspector->getStoredProcedures($connection) as $procedureName) {
            if (!$this->matchesFilter($procedureName, $filter, $matchMode)) {
                continue;
            }

            $details = ['type' => 'procedure'];
            if ($includeDefinitions) {
                $definition = $schemaInspector->getStoredProcedureDefinition($connection, $procedureName);
                if (null !== $definition && '' !== trim($definition)) {
                    $details['definition'] = $definition;
                }
            }

            $structures[$procedureName] = $details;
        }

        return $structures;
    }

    /**
     * @return list<string>
     */
    private function getStoredProceduresNames(Connection $connection, DriverSchemaInspectorInterface $schemaInspector, string $filter, string $matchMode): array
    {
        $names = [];

        foreach ($schemaInspector->getStoredProcedures($connection) as $procedureName) {
            if (!$this->matchesFilter($procedureName, $filter, $matchMode)) {
                continue;
            }

            $names[] = $procedureName;
        }

        return $names;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function getFunctionsStructure(Connection $connection, DriverSchemaInspectorInterface $schemaInspector, string $filter, string $matchMode, bool $includeDefinitions): array
    {
        $structures = [];

        foreach ($schemaInspector->getFunctions($connection) as $functionName) {
            if (!$this->matchesFilter($functionName, $filter, $matchMode)) {
                continue;
            }

            $details = ['type' => 'function'];
            if ($includeDefinitions) {
                $definition = $schemaInspector->getFunctionDefinition($connection, $functionName);
                if (null !== $definition && '' !== trim($definition)) {
                    $details['definition'] = $definition;
                }
            }

            $structures[$functionName] = $details;
        }

        return $structures;
    }

    /**
     * @return list<string>
     */
    private function getFunctionsNames(Connection $connection, DriverSchemaInspectorInterface $schemaInspector, string $filter, string $matchMode): array
    {
        $names = [];

        foreach ($schemaInspector->getFunctions($connection) as $functionName) {
            if (!$this->matchesFilter($functionName, $filter, $matchMode)) {
                continue;
            }

            $names[] = $functionName;
        }

        return $names;
    }

    /**
     * @return list<string>
     */
    private function getTriggersNames(Connection $connection, DriverSchemaInspectorInterface $schemaInspector, string $filter, string $matchMode): array
    {
        $names = [];

        foreach ($schemaInspector->getTriggers($connection) as $triggerName) {
            if (!$this->matchesFilter($triggerName, $filter, $matchMode)) {
                continue;
            }

            $names[] = $triggerName;
        }

        return $names;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function getSequencesStructure(Connection $connection, string $filter, string $matchMode): array
    {
        $sequences = [];

        try {
            foreach ($connection->createSchemaManager()->introspectSequences() as $sequence) {
                $sequenceName = $sequence->getObjectName()->toString();
                if (!$this->matchesFilter($sequenceName, $filter, $matchMode)) {
                    continue;
                }

                $sequences[$sequenceName] = [
                    'allocation_size' => $sequence->getAllocationSize(),
                    'initial_value' => $sequence->getInitialValue(),
                ];
            }
        } catch (\Exception) {
            // Platform might not support sequences.
        }

        return $sequences;
    }

    /**
     * @return list<string>
     */
    private function getSequencesNames(Connection $connection, string $filter, string $matchMode): array
    {
        $names = [];

        try {
            foreach ($connection->createSchemaManager()->introspectSequences() as $sequence) {
                $sequenceName = $sequence->getObjectName()->toString();
                if (!$this->matchesFilter($sequenceName, $filter, $matchMode)) {
                    continue;
                }

                $names[] = $sequenceName;
            }
        } catch (\Exception) {
            // Platform might not support sequences.
        }

        return $names;
    }

    private function normalizeDetail(string $detail): string
    {
        $detailEnum = SchemaDetail::tryFromInput($detail);
        if (!$detailEnum instanceof SchemaDetail) {
            throw new ToolUsageError(message: \sprintf('Invalid detail value "%s".', $detail), hint: \sprintf('Use one of: %s.', implode(', ', SchemaDetail::values())));
        }

        return $detailEnum->value;
    }

    private function normalizeMatchMode(string $matchMode): string
    {
        $modeEnum = SchemaMatchMode::tryFromInput($matchMode);
        if (!$modeEnum instanceof SchemaMatchMode) {
            throw new ToolUsageError(message: \sprintf('Invalid matchMode value "%s".', $matchMode), hint: \sprintf('Use one of: %s.', implode(', ', SchemaMatchMode::values())));
        }

        return $modeEnum->value;
    }

    private function matchesFilter(string $objectName, string $filter, string $matchMode): bool
    {
        if ('' === trim($filter)) {
            return true;
        }

        $normalizedName = $this->normalizeFilterTarget($objectName);
        $normalizedFilter = $this->normalizeFilterTarget($filter);
        $normalizedMode = $this->normalizeMatchMode($matchMode);

        return match ($normalizedMode) {
            SchemaMatchMode::PREFIX->value => $this->matchesPrefix($normalizedName, $normalizedFilter),
            SchemaMatchMode::EXACT->value => $this->matchesExact($normalizedName, $normalizedFilter),
            SchemaMatchMode::GLOB->value => $this->matchesGlob($normalizedName, $normalizedFilter),
            default => $this->matchesContains($normalizedName, $normalizedFilter),
        };
    }

    /**
     * @param array{canonical: string, leaf: string} $name
     * @param array{canonical: string, leaf: string} $filter
     */
    private function matchesPrefix(array $name, array $filter): bool
    {
        return str_starts_with($name['canonical'], $filter['canonical'])
            || str_starts_with($name['leaf'], $filter['leaf']);
    }

    /**
     * @param array{canonical: string, leaf: string} $name
     * @param array{canonical: string, leaf: string} $filter
     */
    private function matchesExact(array $name, array $filter): bool
    {
        return $name['canonical'] === $filter['canonical']
            || $name['leaf'] === $filter['leaf'];
    }

    /**
     * @param array{canonical: string, leaf: string} $name
     * @param array{canonical: string, leaf: string} $filter
     */
    private function matchesGlob(array $name, array $filter): bool
    {
        return fnmatch($filter['canonical'], $name['canonical'])
            || fnmatch($filter['leaf'], $name['leaf']);
    }

    /**
     * @param array{canonical: string, leaf: string} $name
     * @param array{canonical: string, leaf: string} $filter
     */
    private function matchesContains(array $name, array $filter): bool
    {
        return str_contains($name['canonical'], $filter['canonical'])
            || str_contains($name['leaf'], $filter['leaf']);
    }

    /**
     * @return array{canonical: string, leaf: string}
     */
    private function normalizeFilterTarget(string $value): array
    {
        $trimmedValue = trim($value);
        $parts = preg_split('/\s*\.\s*/', $trimmedValue);
        if (false === $parts || [] === $parts) {
            $normalized = $this->normalizeIdentifier($trimmedValue);

            return [
                'canonical' => $normalized,
                'leaf' => $normalized,
            ];
        }

        $normalizedParts = [];
        foreach ($parts as $part) {
            $normalizedPart = $this->normalizeIdentifier($part);
            if ('' === $normalizedPart) {
                continue;
            }

            $normalizedParts[] = $normalizedPart;
        }

        if ([] === $normalizedParts) {
            $normalized = $this->normalizeIdentifier($trimmedValue);

            return [
                'canonical' => $normalized,
                'leaf' => $normalized,
            ];
        }

        $canonical = implode('.', $normalizedParts);

        return [
            'canonical' => $canonical,
            'leaf' => end($normalizedParts) ?: $canonical,
        ];
    }

    /**
     * @param list<string> $indexColumns
     */
    private function isPrimaryIndex(array $indexColumns, ?PrimaryKeyConstraint $primaryKey): bool
    {
        if (!$primaryKey instanceof PrimaryKeyConstraint) {
            return false;
        }

        $normalizedIndexColumns = array_map($this->normalizeIdentifier(...), $indexColumns);
        sort($normalizedIndexColumns);

        $primaryColumns = array_map(
            fn ($columnName): string => $this->normalizeIdentifier($columnName->toString()),
            $primaryKey->getColumnNames()
        );
        sort($primaryColumns);

        return $normalizedIndexColumns === $primaryColumns;
    }

    private function normalizeIdentifier(string $identifier): string
    {
        return strtolower(trim($identifier, '"\'`[] '));
    }

    private function unquoteIdentifier(string $identifier): string
    {
        return trim($identifier, '"\'`[] ');
    }

    private function getTypeLabel(object $type): string
    {
        $typeClass = $type::class;
        $typeParts = explode('\\', $typeClass);

        return str_replace('Type', '', end($typeParts));
    }

    /**
     * @param list<array<string, mixed>> $triggers
     *
     * @return list<array<string, mixed>>
     */
    private function filterTableTriggers(array $triggers, string $filter, string $matchMode): array
    {
        if ('' === trim($filter)) {
            return $triggers;
        }

        return array_values(array_filter(
            $triggers,
            fn (array $trigger): bool => $this->matchesFilter((string) ($trigger['name'] ?? ''), $filter, $matchMode),
        ));
    }

    /**
     * @param list<array<string, mixed>> $triggers
     *
     * @return list<array<string, mixed>>
     */
    private function enrichTriggersWithDefinitions(Connection $connection, DriverSchemaInspectorInterface $schemaInspector, array $triggers): array
    {
        return array_map(static function (array $trigger) use ($connection, $schemaInspector): array {
            $triggerName = $trigger['name'] ?? null;
            if (!\is_string($triggerName) || '' === trim($triggerName)) {
                return $trigger;
            }

            $definition = $schemaInspector->getTriggerDefinition($connection, $triggerName);
            if (null === $definition || '' === trim($definition)) {
                return $trigger;
            }

            $trigger['definition'] = $definition;

            return $trigger;
        }, $triggers);
    }
}
