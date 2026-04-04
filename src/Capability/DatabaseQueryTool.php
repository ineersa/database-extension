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
use MatesOfMate\DatabaseExtension\Exception\ToolUsageError;
use MatesOfMate\DatabaseExtension\Service\ConnectionResolver;
use MatesOfMate\DatabaseExtension\Service\SafeQueryExecutor;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;

class DatabaseQueryTool
{
    private const DESCRIPTION = <<<DESCRIPTION
Runs read-only SQL queries against a Doctrine DBAL connection.
Only SELECT and WITH (CTE) queries are allowed. Writes, DDL, and transaction control are blocked.
One statement per call — semicolons are rejected; split into separate calls.

ROW LIMIT: SELECT without WHERE must include LIMIT 10. Always default to LIMIT 10.
Large text columns (>200 chars) are truncated to "<TEXT>" in multi-row results. To see full text, query must return exactly 1 row.
Aggregates without GROUP BY (e.g. SELECT COUNT(*)) are exempt from the LIMIT requirement.

Before writing SQL, use database-schema to discover table/column names and avoid errors.
Connection names come from the application's Doctrine DBAL configuration.
If `connection` is omitted, the default Doctrine DBAL connection is used.
DESCRIPTION;

    public function __construct(
        private readonly SafeQueryExecutor $safeQueryExecutor,
        private readonly ConnectionResolver $connectionResolver,
    ) {
    }

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
        $trimmedQuery = trim($query);

        try {
            $resolvedConnection = $this->connectionResolver->resolve($connection);
            $rows = $this->safeQueryExecutor->execute($resolvedConnection['connection'], $trimmedQuery);

            return CallToolResult::success([
                new TextContent(Toon::encode($rows)),
            ]);
        } catch (\Throwable $throwable) {
            $toolError = $this->mapThrowableToToolUsageError($throwable);

            return $this->errorResult(
                $toolError->getMessage(),
                $toolError->getHint() ?? 'Retry with a read-only SELECT query and check connection configuration.'
            );
        }
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

    private function mapThrowableToToolUsageError(\Throwable $throwable): ToolUsageError
    {
        if ($throwable instanceof ToolUsageError) {
            return $throwable;
        }

        return new ToolUsageError(
            message: $throwable->getMessage(),
            hint: 'Query failed in the database. Verify table/column names and SQL syntax, then retry.',
            previous: $throwable,
        );
    }
}
