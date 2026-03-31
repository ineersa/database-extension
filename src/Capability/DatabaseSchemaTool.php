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
use MatesOfMate\DatabaseExtension\Enum\SchemaDetail;
use MatesOfMate\DatabaseExtension\Enum\SchemaMatchMode;
use MatesOfMate\DatabaseExtension\Exception\ToolUsageError;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;

class DatabaseSchemaTool
{
    private const DEFAULT_CONNECTION_NAME = 'default';

    private const DESCRIPTION = <<<DESCRIPTION
Inspect database schema objects in summary, columns, or full detail.
Available connections: default plus any configured Doctrine DBAL named connections.
If `connection` is omitted, the default Doctrine connection is used.
DESCRIPTION;

    /**
     * @param string|null $connection      Optional Doctrine DBAL connection name
     * @param string      $filter          Optional object-name filter
     * @param string      $detail          One of: summary, columns, full
     * @param string      $matchMode       One of: contains, prefix, exact, glob
     * @param bool        $includeViews    Include views in the response
     * @param bool        $includeRoutines Include procedures/functions/sequences/triggers in the response
     */
    #[McpTool(
        name: 'database-schema',
        description: self::DESCRIPTION
    )]
    public function execute(
        ?string $connection = null,
        string $filter = '',
        string $detail = 'summary',
        string $matchMode = 'contains',
        bool $includeViews = false,
        bool $includeRoutines = false,
    ): CallToolResult {
        try {
            $normalizedDetail = $this->normalizeDetail($detail);
            $normalizedMatchMode = $this->normalizeMatchMode($matchMode);
            $normalizedConnection = $this->normalizeConnectionName($connection);

            $payload = [
                'connection' => $normalizedConnection,
                'default_connection_used' => null === $connection || '' === trim($connection),
                'detail' => $normalizedDetail,
                'match_mode' => $normalizedMatchMode,
                'filter' => $filter,
                'tables' => [],
            ];

            if ($includeViews) {
                $payload['views'] = [];
            }

            if ($includeRoutines) {
                $payload['routines'] = [
                    'stored_procedures' => [],
                    'functions' => [],
                    'sequences' => [],
                    'triggers' => [],
                ];
            }

            return CallToolResult::success([
                new TextContent(Toon::encode($payload)),
            ]);
        } catch (\Throwable $throwable) {
            $toolError = $this->mapThrowableToToolUsageError($throwable);

            return $this->errorResult(
                $toolError->getMessage(),
                $toolError->getHint() ?? 'Adjust detail or matchMode values and retry.',
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
        $matchModeEnum = SchemaMatchMode::tryFromInput($matchMode);
        if (!$matchModeEnum instanceof SchemaMatchMode) {
            throw new ToolUsageError(message: \sprintf('Invalid matchMode value "%s".', $matchMode), hint: \sprintf('Use one of: %s.', implode(', ', SchemaMatchMode::values())));
        }

        return $matchModeEnum->value;
    }

    private function mapThrowableToToolUsageError(\Throwable $throwable): ToolUsageError
    {
        if ($throwable instanceof ToolUsageError) {
            return $throwable;
        }

        return new ToolUsageError(
            message: $throwable->getMessage(),
            hint: 'Schema extraction failed. Verify connection health and retry.',
            previous: $throwable,
        );
    }
}
