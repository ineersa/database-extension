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

use MatesOfMate\DatabaseExtension\Capability\DatabaseSchemaTool;
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
        $tool = new DatabaseSchemaTool();

        $result = $tool->execute();

        $this->assertFalse($result->isError);
        $this->assertCount(1, $result->content);
        $this->assertInstanceOf(TextContent::class, $result->content[0]);

        /** @var TextContent $content */
        $content = $result->content[0];
        $payload = (string) $content->text;

        $this->assertStringContainsString('connection: default', $payload);
        $this->assertStringContainsString('detail: summary', $payload);
    }

    public function testReturnsStructuredErrorForInvalidDetail(): void
    {
        $tool = new DatabaseSchemaTool();

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
