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
     * Allowed first tokens for read-only execution (taken from Laravel Boost).
     *
     * @var list<string>
     */
    private const READ_ONLY_STATEMENT_FIRST_WORDS = [
        'SELECT',
        'SHOW',
        'EXPLAIN',
        'DESCRIBE',
        'DESC',
        'WITH',
        'VALUES',
        'TABLE',
    ];

    /**
     * Write / transaction keywords not allowed anywhere in the normalized query (word-boundary match).
     */
    private const FORBIDDEN_KEYWORDS_PATTERN = '/\b(COMMIT|ROLLBACK|TRANSACTION|INSERT|UPDATE|DELETE|DROP|ALTER|CREATE|TRUNCATE|REPLACE|MERGE|GRANT|REVOKE|CALL|EXEC|EXECUTE)\b/';

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
            throw new ToolUsageError(message: 'Only read-only queries are allowed.', hint: 'Use SELECT, SHOW, EXPLAIN, DESCRIBE, DESC, WITH … SELECT, VALUES, or TABLE (see database-schema first).');
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
        $firstWord = $this->firstSqlToken($query);
        if (null === $firstWord) {
            return false;
        }

        if (!\in_array($firstWord, self::READ_ONLY_STATEMENT_FIRST_WORDS, true)) {
            return false;
        }

        if ('WITH' === $firstWord) {
            return $this->withClauseLeadsToReadOnlySelect($query);
        }

        return true;
    }

    private function firstSqlToken(string $query): ?string
    {
        if (1 !== preg_match('/^([A-Z][A-Z0-9_]*)/', $query, $matches)) {
            return null;
        }

        return $matches[1];
    }

    /**
     * Require a trailing SELECT and reject obvious write statements after the CTE list (Boost parity).
     */
    private function withClauseLeadsToReadOnlySelect(string $query): bool
    {
        if (1 !== preg_match('/\)\s*SELECT\b/', $query)) {
            return false;
        }

        return 1 !== preg_match('/\)\s*(DELETE|UPDATE|INSERT|DROP|ALTER|TRUNCATE|REPLACE|RENAME|CREATE)\b/', $query);
    }

    private function containsMultipleStatements(string $query): bool
    {
        $parts = $this->splitOnStatementSemicolons($query);
        $nonEmptyStatements = array_filter($parts, static fn (string $item): bool => '' !== trim($item));

        return \count($nonEmptyStatements) > 1;
    }

    /**
     * Splits on semicolons that are statement terminators. Semicolons inside SQL string literals
     * (single quotes, with doubled quote for escape), double-quoted delimiters, and MySQL-style
     * backtick identifiers (doubled backtick for escape) are ignored.
     *
     * @return list<string>
     */
    private function splitOnStatementSemicolons(string $query): array
    {
        $segments = [];
        $current = '';
        $len = \strlen($query);
        $inSingle = false;
        $inDouble = false;
        $inBacktick = false;

        for ($i = 0; $i < $len; ++$i) {
            $c = $query[$i];

            if ($inSingle) {
                $current .= $c;
                if ('\'' === $c) {
                    if ($i + 1 < $len && '\'' === $query[$i + 1]) {
                        $current .= $query[++$i];
                    } else {
                        $inSingle = false;
                    }
                }

                continue;
            }

            if ($inDouble) {
                $current .= $c;
                if ('"' === $c) {
                    if ($i + 1 < $len && '"' === $query[$i + 1]) {
                        $current .= $query[++$i];
                    } else {
                        $inDouble = false;
                    }
                }

                continue;
            }

            if ($inBacktick) {
                $current .= $c;
                if ('`' === $c) {
                    if ($i + 1 < $len && '`' === $query[$i + 1]) {
                        $current .= $query[++$i];
                    } else {
                        $inBacktick = false;
                    }
                }

                continue;
            }

            if ('\'' === $c) {
                $inSingle = true;
                $current .= $c;

                continue;
            }

            if ('"' === $c) {
                $inDouble = true;
                $current .= $c;

                continue;
            }

            if ('`' === $c) {
                $inBacktick = true;
                $current .= $c;

                continue;
            }

            if (';' === $c) {
                $segments[] = $current;
                $current = '';

                continue;
            }

            $current .= $c;
        }

        $segments[] = $current;

        return $segments;
    }

    private function findForbiddenKeyword(string $query): ?string
    {
        if (1 !== preg_match(self::FORBIDDEN_KEYWORDS_PATTERN, $query, $matches)) {
            return null;
        }

        return $matches[1];
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
