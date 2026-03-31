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
use MatesOfMate\DatabaseExtension\Service\DatabaseSchemaService;
use Mcp\Capability\Attribute\McpResourceTemplate;

class ConnectionResource
{
    private const DEFAULT_CONNECTION_NAME = 'default';

    public function __construct(
        private readonly ConnectionResolver $connectionResolver,
        private readonly DatabaseSchemaService $databaseSchemaService,
    ) {
    }

    /**
     * @return array{uri: string, mimeType: string, text: string}
     */
    #[McpResourceTemplate(
        uriTemplate: 'db://{connection}',
        name: 'database_connection_summary',
        description: 'Schema discovery summary for one database connection: tables, views, routines, and connection metadata.',
        mimeType: 'text/plain'
    )]
    public function getConnectionSummary(string $connection): array
    {
        $requestedConnection = trim($connection);
        $normalizedRequestedConnection = '' === $requestedConnection
            ? self::DEFAULT_CONNECTION_NAME
            : $requestedConnection;

        try {
            $resolvedConnection = $this->connectionResolver->resolve($connection);
            $schemaManager = $resolvedConnection['connection']->createSchemaManager();

            $tables = [];
            foreach ($schemaManager->introspectTables() as $table) {
                $tables[] = $table->getObjectName()->toString();
            }

            return [
                'uri' => 'db://'.$resolvedConnection['name'],
                'mimeType' => 'text/plain',
                'text' => Toon::encode([
                    'metadata' => [
                        'connection' => $resolvedConnection['name'],
                        'default_connection' => $resolvedConnection['default_name'],
                        'is_default_connection' => $resolvedConnection['name'] === $resolvedConnection['default_name'],
                        'driver' => $resolvedConnection['metadata']['driver'],
                        'platform' => $resolvedConnection['metadata']['platform'],
                        'server_version' => $resolvedConnection['metadata']['server_version'],
                    ],
                    'tables' => $tables,
                    'views' => $this->databaseSchemaService->getViewsList($resolvedConnection['connection']),
                    'routines' => $this->databaseSchemaService->getRoutinesList($resolvedConnection['connection']),
                ]),
            ];
        } catch (\Throwable $throwable) {
            $toolError = $this->mapThrowableToToolUsageError($throwable);

            return [
                'uri' => 'db://'.$normalizedRequestedConnection,
                'mimeType' => 'text/plain',
                'text' => Toon::encode([
                    'error' => $toolError->getMessage(),
                    'hint' => $toolError->getHint() ?? 'Verify Doctrine connection availability and retry with an existing connection name.',
                ]),
            ];
        }
    }

    private function mapThrowableToToolUsageError(\Throwable $throwable): ToolUsageError
    {
        if ($throwable instanceof ToolUsageError) {
            return $throwable;
        }

        return new ToolUsageError(
            message: $throwable->getMessage(),
            hint: 'Connection summary extraction failed. Verify connection health and retry.',
            previous: $throwable,
        );
    }
}
