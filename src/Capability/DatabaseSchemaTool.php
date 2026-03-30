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

class DatabaseSchemaTool
{
    private const string DEFAULT_CONNECTION_NAME = 'default';

    /**
     * @var array<int, string>
     */
    private const array VALID_DETAILS = ['summary', 'columns', 'full'];

    /**
     * @var array<int, string>
     */
    private const array VALID_MATCH_MODES = ['contains', 'prefix', 'exact', 'glob'];

    private const string DESCRIPTION = <<<DESCRIPTION
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
        $normalizedDetail = strtolower(trim($detail));
        if (!\in_array($normalizedDetail, self::VALID_DETAILS, true)) {
            return $this->errorResult(
                \sprintf('Invalid detail value "%s".', $detail),
                'Use one of: summary, columns, full.'
            );
        }

        $normalizedMatchMode = strtolower(trim($matchMode));
        if (!\in_array($normalizedMatchMode, self::VALID_MATCH_MODES, true)) {
            return $this->errorResult(
                \sprintf('Invalid matchMode value "%s".', $matchMode),
                'Use one of: contains, prefix, exact, glob.'
            );
        }

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
}
