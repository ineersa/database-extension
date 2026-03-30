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

use MatesOfMate\DatabaseExtension\Capability\DatabaseQueryTool;
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
        $tool = new DatabaseQueryTool();

        $result = $tool->execute('SELECT id, email FROM users LIMIT 10');

        $this->assertFalse($result->isError);
        $this->assertCount(1, $result->content);
        $this->assertInstanceOf(TextContent::class, $result->content[0]);

        /** @var TextContent $content */
        $content = $result->content[0];
        $payload = (string) $content->text;
        $this->assertStringContainsString('connection: default', $payload);
        $this->assertStringContainsString('rows[0]:', $payload);
    }

    public function testReturnsStructuredErrorPayloadForWriteOperation(): void
    {
        $tool = new DatabaseQueryTool();

        $result = $tool->execute('DELETE FROM users WHERE id = 1');

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
