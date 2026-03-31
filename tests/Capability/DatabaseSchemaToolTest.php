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
use MatesOfMate\DatabaseExtension\Capability\DatabaseSchemaTool;
use MatesOfMate\DatabaseExtension\Service\ConnectionResolver;
use MatesOfMate\DatabaseExtension\Service\DatabaseSchemaService;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Schema\Content\TextContent;
use PHPUnit\Framework\TestCase;

class DatabaseSchemaToolTest extends TestCase
{
    public function testUsesMateNativeToolName(): void
    {
        $reflectionMethod = new \ReflectionMethod(DatabaseSchemaTool::class, 'execute');
        $attributes = $reflectionMethod->getAttributes(McpTool::class);

        $this->assertCount(1, $attributes);

        /** @var McpTool $attribute */
        $attribute = $attributes[0]->newInstance();
        $this->assertSame('database-schema', $attribute->name);
    }

    public function testReturnsSummaryPayloadWithDefaultConnectionFallback(): void
    {
        $connection = $this->createMock(Connection::class);

        $databaseSchemaService = $this->createMock(DatabaseSchemaService::class);
        $databaseSchemaService
            ->expects($this->once())
            ->method('getSchemaStructure')
            ->with(
                'default',
                $connection,
                'sqlite',
                '',
                'summary',
                'contains',
                false,
                false,
            )
            ->willReturn([
                'connection' => 'default',
                'engine' => 'sqlite',
                'detail' => 'summary',
                'match_mode' => 'contains',
                'tables' => ['users'],
            ]);

        $connectionResolver = $this->createMock(ConnectionResolver::class);
        $connectionResolver
            ->expects($this->once())
            ->method('resolve')
            ->with(null)
            ->willReturn([
                'name' => 'default',
                'default_name' => 'default',
                'default_used' => true,
                'metadata' => [
                    'driver' => 'pdo_sqlite',
                    'platform' => 'sqlite',
                    'server_version' => null,
                ],
                'connection' => $connection,
            ]);

        $tool = new DatabaseSchemaTool($databaseSchemaService, $connectionResolver);

        $result = $tool->execute();

        $this->assertFalse($result->isError);
        $this->assertCount(1, $result->content);
        $this->assertInstanceOf(TextContent::class, $result->content[0]);

        /** @var TextContent $content */
        $content = $result->content[0];
        $payload = (string) $content->text;

        $this->assertStringContainsString('connection: default', $payload);
        $this->assertStringContainsString('detail: summary', $payload);
        $this->assertStringNotContainsString('default_connection:', $payload);
        $this->assertStringNotContainsString('available_connections', $payload);
    }

    public function testReturnsStructuredErrorForInvalidDetail(): void
    {
        $databaseSchemaService = $this->createMock(DatabaseSchemaService::class);
        $databaseSchemaService
            ->expects($this->never())
            ->method('getSchemaStructure');

        $connectionResolver = $this->createMock(ConnectionResolver::class);
        $connectionResolver
            ->expects($this->never())
            ->method('resolve');

        $tool = new DatabaseSchemaTool($databaseSchemaService, $connectionResolver);

        $result = $tool->execute(detail: 'deep');

        $this->assertTrue($result->isError);
        $this->assertCount(1, $result->content);
        $this->assertInstanceOf(TextContent::class, $result->content[0]);

        /** @var TextContent $content */
        $content = $result->content[0];
        $payload = (string) $content->text;

        $this->assertStringContainsString('error:', $payload);
        $this->assertStringContainsString('hint:', $payload);
        $this->assertStringNotContainsString('retryable', $payload);
    }
}
