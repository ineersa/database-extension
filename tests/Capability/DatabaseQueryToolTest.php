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
use MatesOfMate\DatabaseExtension\Capability\DatabaseQueryTool;
use MatesOfMate\DatabaseExtension\Service\ConnectionResolver;
use MatesOfMate\DatabaseExtension\Service\SafeQueryExecutor;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Schema\Content\TextContent;
use PHPUnit\Framework\TestCase;

class DatabaseQueryToolTest extends TestCase
{
    public function testUsesMateNativeToolName(): void
    {
        $reflectionMethod = new \ReflectionMethod(DatabaseQueryTool::class, 'execute');
        $attributes = $reflectionMethod->getAttributes(McpTool::class);

        $this->assertCount(1, $attributes);

        /** @var McpTool $attribute */
        $attribute = $attributes[0]->newInstance();
        $this->assertSame('database-query', $attribute->name);
    }

    public function testReturnsSuccessPayloadForReadOnlyQuery(): void
    {
        $query = 'SELECT id, email FROM users LIMIT 10';
        $connection = $this->createMock(Connection::class);

        $safeQueryExecutor = $this->createMock(SafeQueryExecutor::class);
        $safeQueryExecutor
            ->expects($this->once())
            ->method('execute')
            ->with($connection, $query)
            ->willReturn([
                ['id' => 1, 'email' => 'johannes@sulu.io'],
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

        $tool = new DatabaseQueryTool($safeQueryExecutor, $connectionResolver);

        $result = $tool->execute($query);

        $this->assertFalse($result->isError);
        $this->assertCount(1, $result->content);
        $this->assertInstanceOf(TextContent::class, $result->content[0]);

        $content = $result->content[0];
        $payload = (string) $content->text;
        $this->assertStringContainsString('johannes@sulu.io', $payload);
        $this->assertStringNotContainsString('default_connection:', $payload);
        $this->assertStringNotContainsString('available_connections', $payload);
    }

    public function testReturnsStructuredErrorPayloadForWriteOperation(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('beginTransaction');

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

        $tool = new DatabaseQueryTool(new SafeQueryExecutor(), $connectionResolver);

        $result = $tool->execute('DELETE FROM users WHERE id = 1');

        $this->assertTrue($result->isError);
        $this->assertCount(1, $result->content);
        $this->assertInstanceOf(TextContent::class, $result->content[0]);

        $content = $result->content[0];
        $payload = (string) $content->text;
        $this->assertStringContainsString('error:', $payload);
        $this->assertStringContainsString('hint:', $payload);
        $this->assertStringNotContainsString('retryable', $payload);
    }
}
