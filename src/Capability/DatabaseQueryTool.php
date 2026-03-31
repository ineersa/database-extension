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
use MatesOfMate\DatabaseExtension\Service\SafeQueryExecutor;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;

class DatabaseQueryTool
{
    private const DEFAULT_CONNECTION_NAME = 'default';

    private const DESCRIPTION = <<<DESCRIPTION
Run read-only SQL queries for debugging and data inspection.
Available connections: default plus any configured Doctrine DBAL named connections.
If `connection` is omitted, the default Doctrine connection is used.
DESCRIPTION;

    public function __construct(
        private readonly SafeQueryExecutor $safeQueryExecutor,
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
        $normalizedConnection = $this->normalizeConnectionName($connection);
        $trimmedQuery = trim($query);

        try {
            $this->safeQueryExecutor->validateReadOnlyQuery($trimmedQuery);

            return CallToolResult::success([
                new TextContent(Toon::encode([
                    'connection' => $normalizedConnection,
                    'default_connection_used' => null === $connection || '' === trim($connection),
                    'query' => $trimmedQuery,
                    'rows' => [],
                    'row_count' => 0,
                ])),
            ]);
        } catch (\Throwable $throwable) {
            $toolError = $this->mapThrowableToToolUsageError($throwable);

            return $this->errorResult(
                $toolError->getMessage(),
                $toolError->getHint() ?? 'Retry with a read-only SELECT query and check connection configuration.'
            );
        }
    }

    private function normalizeConnectionName(?string $connection): string
    {
        if (null === $connection || '' === trim($connection)) {
            return self::DEFAULT_CONNECTION_NAME;
        }

        return trim($connection);
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
