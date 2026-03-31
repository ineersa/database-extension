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
use Doctrine\DBAL\Exception;
use MatesOfMate\DatabaseExtension\Exception\ToolUsageError;

class SafeQueryExecutor
{
    /**
     * @var list<string>
     */
    private const FORBIDDEN_KEYWORDS = [
        'COMMIT',
        'ROLLBACK',
        'TRANSACTION',
        'INSERT',
        'UPDATE',
        'DELETE',
        'DROP',
        'ALTER',
        'CREATE',
        'TRUNCATE',
        'REPLACE',
        'MERGE',
        'GRANT',
        'REVOKE',
        'CALL',
        'EXEC',
        'EXECUTE',
    ];

    /**
     * @return list<array<string, mixed>>
     *
     * @throws Exception
     */
    public function execute(Connection $connection, string $sql): array
    {
        $trimmedSql = trim($sql);
        $this->validateReadOnlyQuery($trimmedSql);

        $connection->beginTransaction();

        try {
            $statement = $connection->executeQuery($trimmedSql);
            $rows = $statement->fetchAllAssociative();
        } finally {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
        }

        return $this->truncateLongTextRows($rows);
    }

    public function validateReadOnlyQuery(string $query): void
    {
        $trimmedQuery = trim($query);
        if ('' === $trimmedQuery) {
            throw new ToolUsageError(message: 'Query must not be empty.', hint: 'Provide a read-only SELECT query and include LIMIT 10 when no WHERE clause is present.');
        }

        if ($this->containsMultipleStatements($trimmedQuery)) {
            throw new ToolUsageError(message: 'Only one SQL statement is allowed per call.', hint: 'Split multi-statement requests into separate database-query calls.');
        }

        $normalizedQuery = strtoupper($trimmedQuery);

        if (!$this->isReadStatement($normalizedQuery)) {
            throw new ToolUsageError(message: 'Only read-only SELECT and WITH queries are allowed.', hint: 'Use database-schema first, then run a SELECT query.');
        }

        $forbiddenKeyword = $this->findForbiddenKeyword($normalizedQuery);
        if (null !== $forbiddenKeyword) {
            throw new ToolUsageError(message: \sprintf('Security violation: Keyword "%s" is not allowed in read-only mode.', $forbiddenKeyword), hint: 'Use a read-only SELECT query. Writes and transaction commands are blocked.');
        }

        if ($this->selectWithoutLimitOrWhere($normalizedQuery)) {
            throw new ToolUsageError(message: 'SELECT queries without WHERE require LIMIT.', hint: 'Add LIMIT 10 (or FETCH NEXT), or add a WHERE clause.');
        }
    }

    private function isReadStatement(string $query): bool
    {
        return str_starts_with($query, 'SELECT') || str_starts_with($query, 'WITH');
    }

    private function containsMultipleStatements(string $query): bool
    {
        $parts = explode(';', $query);
        $nonEmptyStatements = array_filter($parts, static fn (string $item): bool => '' !== trim($item));

        return \count($nonEmptyStatements) > 1;
    }

    private function findForbiddenKeyword(string $query): ?string
    {
        foreach (self::FORBIDDEN_KEYWORDS as $keyword) {
            if (1 === preg_match('/\b'.$keyword.'\b/', $query)) {
                return $keyword;
            }
        }

        return null;
    }

    private function selectWithoutLimitOrWhere(string $query): bool
    {
        if (!str_starts_with($query, 'SELECT')) {
            return false;
        }

        if (str_contains($query, 'WHERE ')) {
            return false;
        }

        if (str_contains($query, 'LIMIT ') || str_contains($query, 'FETCH NEXT')) {
            return false;
        }

        return !$this->isAggregateOnlyQuery($query);
    }

    private function isAggregateOnlyQuery(string $query): bool
    {
        if (str_contains($query, 'GROUP BY')) {
            return false;
        }

        return 1 === preg_match('/\b(COUNT|SUM|AVG|MIN|MAX|GROUP_CONCAT|STRING_AGG)\s*\(/', $query);
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<array<string, mixed>>
     */
    private function truncateLongTextRows(array $rows): array
    {
        if (\count($rows) <= 1) {
            return $rows;
        }

        $longColumns = [];
        foreach ($rows as $row) {
            foreach ($row as $columnName => $value) {
                if (\is_string($value) && \strlen($value) > 200) {
                    $longColumns[$columnName] = true;
                }
            }
        }

        if ([] === $longColumns) {
            return $rows;
        }

        foreach ($rows as &$row) {
            foreach (array_keys($longColumns) as $columnName) {
                if (\array_key_exists($columnName, $row)) {
                    $row[$columnName] = '<TEXT>';
                }
            }
        }

        return $rows;
    }
}
