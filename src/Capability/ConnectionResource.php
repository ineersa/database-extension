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
use Mcp\Capability\Attribute\McpResourceTemplate;

class ConnectionResource
{
    private const DEFAULT_CONNECTION_NAME = 'default';

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
        $normalizedConnection = trim($connection);
        $isDefaultConnection = self::DEFAULT_CONNECTION_NAME === $normalizedConnection;

        return [
            'uri' => 'db://'.$normalizedConnection,
            'mimeType' => 'text/plain',
            'text' => Toon::encode([
                'metadata' => [
                    'connection' => $normalizedConnection,
                    'default_connection' => self::DEFAULT_CONNECTION_NAME,
                    'is_default_connection' => $isDefaultConnection,
                    'driver' => null,
                    'platform' => null,
                ],
                'tables' => [],
                'views' => [],
                'routines' => [
                    'stored_procedures' => [],
                    'functions' => [],
                    'sequences' => [],
                    'triggers' => [],
                ],
            ]),
        ];
    }
}
