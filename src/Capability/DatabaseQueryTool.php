<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\DatabaseExtension\Capability;

use HelgeSverre\Toon\Toon;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;

class DatabaseQueryTool
{
    private const string DEFAULT_CONNECTION_NAME = 'default';

    private const string DESCRIPTION = <<<DESCRIPTION
Run read-only SQL queries for debugging and data inspection.
Available connections: default plus any configured Doctrine DBAL named connections.
If `connection` is omitted, the default Doctrine connection is used.
DESCRIPTION;

    /**
     * @param string      $query      SQL query to validate and execute in read-only mode
     * @param string|null $connection Optional Doctrine DBAL connection name
     */
    #[McpTool(
        name: 'database-query',
        description: self::DESCRIPTION
    )]
    public function execute(string $query, ?string $connection = null): CallToolResult
    {
        $normalizedConnection = $this->normalizeConnectionName($connection);
        $trimmedQuery = trim($query);

        if ('' === $trimmedQuery) {
            return $this->errorResult(
                'Query must not be empty.',
                'Provide a read-only SELECT query and include LIMIT 10 when no WHERE clause is present.'
            );
        }

        if ($this->containsMultipleStatements($trimmedQuery)) {
            return $this->errorResult(
                'Only one SQL statement is allowed per call.',
                'Split multi-statement requests into separate database-query calls.'
            );
        }

        $normalizedQuery = strtoupper($trimmedQuery);

        if (!$this->isReadStatement($normalizedQuery)) {
            return $this->errorResult(
                'Only read-only SELECT and WITH queries are allowed.',
                'Use database-schema first, then run a SELECT query.'
            );
        }

        if ($this->containsWriteKeyword($normalizedQuery)) {
            return $this->errorResult(
                'Write operations are not allowed by database-query.',
                'Rewrite the SQL as a read-only SELECT query.'
            );
        }

        if ($this->selectWithoutLimitOrWhere($normalizedQuery)) {
            return $this->errorResult(
                'SELECT queries without WHERE require LIMIT or TOP.',
                'Add LIMIT 10 (MySQL/PostgreSQL/SQLite) or TOP 10 (SQL Server), or add a WHERE clause.'
            );
        }

        return CallToolResult::success([
            new TextContent(Toon::encode([
                'connection' => $normalizedConnection,
                'default_connection_used' => null === $connection || '' === trim($connection),
                'query' => $trimmedQuery,
                'rows' => [],
                'row_count' => 0,
            ])),
        ]);
    }

    private function normalizeConnectionName(?string $connection): string
    {
        if (null === $connection || '' === trim($connection)) {
            return self::DEFAULT_CONNECTION_NAME;
        }

        return trim($connection);
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

    private function containsWriteKeyword(string $query): bool
    {
        return 1 === preg_match('/\b(INSERT|UPDATE|DELETE|DROP|ALTER|CREATE|TRUNCATE|REPLACE|MERGE|GRANT|REVOKE|CALL|EXEC|EXECUTE)\b/', $query);
    }

    private function selectWithoutLimitOrWhere(string $query): bool
    {
        if (!str_starts_with($query, 'SELECT')) {
            return false;
        }

        if (str_contains($query, 'WHERE ')) {
            return false;
        }

        if (str_contains($query, 'LIMIT ') || str_contains($query, 'TOP ') || str_contains($query, 'FETCH NEXT')) {
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

    private function errorResult(string $error, string $hint): CallToolResult
    {
        return CallToolResult::error([
            new TextContent(Toon::encode([
                'error' => $error,
                'hint' => $hint,
            ])),
        ]);
    }
}
