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
use MatesOfMate\DatabaseExtension\Service\ConnectionResolver;
use MatesOfMate\DatabaseExtension\Service\DatabaseSchemaService;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;

class DatabaseSchemaTool
{
    private const DESCRIPTION = <<<DESCRIPTION
Inspect schema for a Doctrine DBAL connection.
Prefer this over raw information_schema/system catalog queries for metadata and definitions.

Detail levels:
- summary (default): matching table/view names only.
- columns: tables with column names, types, nullability, defaults.
- full: columns plus indexes, foreign keys, constraints, trigger and routine definitions.

Include flags:
- includeViews=true adds view names (summary/columns) or view SQL definitions (full).
- includeRoutines=true adds procedures, functions, sequences, trigger names (summary/columns) or their definitions (full).

Use detail="columns" or detail="full" with a narrow filter whenever possible.
filter matches object names; omit or use filter="" to include all objects.
Connection names come from the application's Doctrine DBAL configuration.
If `connection` is omitted, the default Doctrine DBAL connection is used.
DESCRIPTION;

    public function __construct(
        private readonly DatabaseSchemaService $databaseSchemaService,
        private readonly ConnectionResolver $connectionResolver,
    ) {
    }

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

            $resolvedConnection = $this->connectionResolver->resolve($connection);

            $payload = $this->databaseSchemaService->getSchemaStructure(
                $resolvedConnection['name'],
                $resolvedConnection['connection'],
                $resolvedConnection['metadata']['platform'] ?? 'unknown',
                $filter,
                $normalizedDetail,
                $normalizedMatchMode,
                $includeViews,
                $includeRoutines,
            );

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
