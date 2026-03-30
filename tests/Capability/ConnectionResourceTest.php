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

use MatesOfMate\DatabaseExtension\Capability\ConnectionResource;
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
        $resource = new ConnectionResource();

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
        $resource = new ConnectionResource();

        $result = $resource->getConnectionSummary('analytics');

        $this->assertStringContainsString('metadata:', (string) $result['text']);
        $this->assertStringContainsString('connection: analytics', (string) $result['text']);
        $this->assertStringContainsString('tables[0]:', (string) $result['text']);
        $this->assertStringContainsString('views[0]:', (string) $result['text']);
        $this->assertStringContainsString('routines:', (string) $result['text']);
    }
}
