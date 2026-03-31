<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\DatabaseExtension\Tests\Capability;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use MatesOfMate\DatabaseExtension\Capability\ConnectionResource;
use MatesOfMate\DatabaseExtension\Service\ConnectionResolver;
use MatesOfMate\DatabaseExtension\Service\DatabaseSchemaService;
use Mcp\Capability\Attribute\McpResourceTemplate;
use PHPUnit\Framework\TestCase;

class ConnectionResourceTest extends TestCase
{
    public function testUsesDbUriTemplate(): void
    {
        $reflectionMethod = new \ReflectionMethod(ConnectionResource::class, 'getConnectionSummary');
        $attributes = $reflectionMethod->getAttributes(McpResourceTemplate::class);

        $this->assertCount(1, $attributes);

        /** @var McpResourceTemplate $attribute */
        $attribute = $attributes[0]->newInstance();
        $this->assertSame('db://{connection}', $attribute->uriTemplate);
    }

    public function testReturnsValidResourceStructure(): void
    {
        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaManager
            ->expects($this->once())
            ->method('introspectTables')
            ->willReturn([]);

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('createSchemaManager')
            ->willReturn($schemaManager);

        $connectionResolver = $this->createMock(ConnectionResolver::class);
        $connectionResolver
            ->expects($this->once())
            ->method('resolve')
            ->with('default')
            ->willReturn([
                'name' => 'default',
                'default_name' => 'default',
                'default_used' => false,
                'metadata' => [
                    'driver' => 'pdo_sqlite',
                    'platform' => 'sqlite',
                    'server_version' => null,
                ],
                'connection' => $connection,
            ]);

        $databaseSchemaService = $this->createMock(DatabaseSchemaService::class);
        $databaseSchemaService
            ->expects($this->once())
            ->method('getViewsList')
            ->with($connection)
            ->willReturn([]);
        $databaseSchemaService
            ->expects($this->once())
            ->method('getRoutinesList')
            ->with($connection)
            ->willReturn([
                'stored_procedures' => [],
                'functions' => [],
                'sequences' => [],
                'triggers' => [],
            ]);

        $resource = new ConnectionResource($connectionResolver, $databaseSchemaService);

        $result = $resource->getConnectionSummary('default');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('uri', $result);
        $this->assertArrayHasKey('mimeType', $result);
        $this->assertArrayHasKey('text', $result);
        $this->assertSame('db://default', $result['uri']);
        $this->assertSame('text/plain', $result['mimeType']);
    }

    public function testReturnsSummaryDiscoveryPayload(): void
    {
        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaManager
            ->expects($this->once())
            ->method('introspectTables')
            ->willReturn([]);

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('createSchemaManager')
            ->willReturn($schemaManager);

        $connectionResolver = $this->createMock(ConnectionResolver::class);
        $connectionResolver
            ->expects($this->once())
            ->method('resolve')
            ->with('analytics')
            ->willReturn([
                'name' => 'analytics',
                'default_name' => 'default',
                'default_used' => false,
                'metadata' => [
                    'driver' => 'pdo_pgsql',
                    'platform' => 'postgresql',
                    'server_version' => '16',
                ],
                'connection' => $connection,
            ]);

        $databaseSchemaService = $this->createMock(DatabaseSchemaService::class);
        $databaseSchemaService
            ->expects($this->once())
            ->method('getViewsList')
            ->with($connection)
            ->willReturn([]);
        $databaseSchemaService
            ->expects($this->once())
            ->method('getRoutinesList')
            ->with($connection)
            ->willReturn([
                'stored_procedures' => [],
                'functions' => [],
                'sequences' => [],
                'triggers' => [],
            ]);

        $resource = new ConnectionResource($connectionResolver, $databaseSchemaService);

        $result = $resource->getConnectionSummary('analytics');

        $this->assertStringContainsString('metadata:', (string) $result['text']);
        $this->assertStringContainsString('connection: analytics', (string) $result['text']);
        $this->assertStringNotContainsString('available_connections', (string) $result['text']);
        $this->assertStringContainsString('tables[0]:', (string) $result['text']);
        $this->assertStringContainsString('views[0]:', (string) $result['text']);
        $this->assertStringContainsString('routines:', (string) $result['text']);
    }
}
