<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\DatabaseExtension\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;
use HelgeSverre\Toon\Toon;
use MatesOfMate\DatabaseExtension\Capability\ConnectionResource;
use MatesOfMate\DatabaseExtension\Capability\DatabaseQueryTool;
use MatesOfMate\DatabaseExtension\Capability\DatabaseSchemaTool;
use MatesOfMate\DatabaseExtension\Service\ConnectionResolver;
use MatesOfMate\DatabaseExtension\Tests\Fixtures\App\TestKernel;
use MatesOfMate\DatabaseExtension\Tests\Fixtures\Database\DatabaseTestFixtures;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

class DoctrineIntegrationTest extends KernelTestCase
{
    public function testRegistersDatabaseCapabilitiesWhenDoctrineBundleIsEnabled(): void
    {
        self::bootKernel();

        $container = self::getContainer();

        $this->assertTrue($container->has(ConnectionResolver::class));
        $this->assertTrue($container->has(DatabaseQueryTool::class));
        $this->assertTrue($container->has(DatabaseSchemaTool::class));
        $this->assertTrue($container->has(ConnectionResource::class));
    }

    public function testDatabaseQueryUsesDefaultConnectionFallback(): void
    {
        self::bootKernel();

        $defaultConnection = $this->connectOrSkip('default');
        DatabaseTestFixtures::reset($defaultConnection);

        /** @var DatabaseQueryTool $databaseQueryTool */
        $databaseQueryTool = self::getContainer()->get(DatabaseQueryTool::class);
        $result = $databaseQueryTool->execute('SELECT id, email FROM users ORDER BY id LIMIT 2');

        $this->assertFalse($result->isError);
        $payload = $this->extractToonPayload($result);

        $this->assertCount(2, $payload);
        $this->assertSame('alice@example.com', $payload[0]['email']);
        $this->assertSame('bob@example.com', $payload[1]['email']);
    }

    public function testQueryToolReturnsStructuredErrorForUnknownConnection(): void
    {
        self::bootKernel();

        /** @var DatabaseQueryTool $databaseQueryTool */
        $databaseQueryTool = self::getContainer()->get(DatabaseQueryTool::class);
        $result = $databaseQueryTool->execute('SELECT id FROM users WHERE id = 1', 'missing');

        $this->assertTrue($result->isError);

        $payload = $this->extractToonPayload($result);
        $this->assertIsArray($payload);
        $this->assertSame(['error', 'hint'], array_keys($payload));
        $this->assertStringContainsString('Connection "missing" is not configured.', $payload['error']);
    }

    public function testReadOnlyConnectionRejectsWriteQueries(): void
    {
        self::bootKernel();

        /** @var ConnectionResolver $connectionResolver */
        $connectionResolver = self::getContainer()->get(ConnectionResolver::class);

        $testedConnections = 0;
        foreach (['default', 'mysql', 'pgsql'] as $connectionName) {
            $writableConnection = $this->tryConnect($connectionName);
            if (!$writableConnection instanceof Connection) {
                continue;
            }

            DatabaseTestFixtures::reset($writableConnection);

            $resolved = $connectionResolver->resolve($connectionName);
            $readOnlyConnection = $resolved['connection'];

            $writeBlocked = false;

            try {
                $readOnlyConnection->executeStatement("INSERT INTO users (id, email, status) VALUES (99, 'blocked@example.com', 'active')");
            } catch (\Throwable) {
                $writeBlocked = true;
            }

            $this->assertTrue($writeBlocked, \sprintf('Expected writes to be blocked for connection "%s".', $connectionName));
            $this->assertSame(3, (int) $writableConnection->executeQuery('SELECT COUNT(*) FROM users')->fetchOne());

            ++$testedConnections;
        }

        $this->assertGreaterThan(0, $testedConnections, 'No reachable test database connection was available.');
    }

    public function testSchemaToolExtractsTablesAndViewsFromRealConnection(): void
    {
        self::bootKernel();

        $defaultConnection = $this->connectOrSkip('default');
        DatabaseTestFixtures::reset($defaultConnection);

        /** @var DatabaseSchemaTool $databaseSchemaTool */
        $databaseSchemaTool = self::getContainer()->get(DatabaseSchemaTool::class);
        $result = $databaseSchemaTool->execute(
            detail: 'summary',
            includeViews: true,
        );

        $this->assertFalse($result->isError);
        $payload = $this->extractToonPayload($result);

        $this->assertIsArray($payload['tables'] ?? null);

        $normalizedTableNames = array_map(
            static fn (string $tableName): string => trim($tableName, '"\'` '),
            $payload['tables'],
        );

        $this->assertContains('users', $normalizedTableNames);
        $this->assertIsArray($payload['views'] ?? null);

        $normalizedViewNames = array_map(
            static fn (string $viewName): string => trim($viewName, '"\'` '),
            $payload['views'],
        );

        $this->assertContains('active_users', $normalizedViewNames);
    }

    public function testConnectionResourceReturnsRealSchemaSummary(): void
    {
        self::bootKernel();

        $defaultConnection = $this->connectOrSkip('default');
        DatabaseTestFixtures::reset($defaultConnection);

        /** @var ConnectionResource $connectionResource */
        $connectionResource = self::getContainer()->get(ConnectionResource::class);
        $resource = $connectionResource->getConnectionSummary('default');

        $this->assertSame('db://default', $resource['uri']);
        $this->assertSame('text/plain', $resource['mimeType']);

        $payload = Toon::decode((string) $resource['text']);
        $this->assertIsArray($payload);
        $this->assertSame('default', $payload['metadata']['connection']);

        $normalizedTableNames = array_map(
            static fn (string $tableName): string => trim($tableName, '"\'` '),
            $payload['tables'],
        );

        $normalizedViewNames = array_map(
            static fn (string $viewName): string => trim($viewName, '"\'` '),
            $payload['views'],
        );

        $this->assertContains('users', $normalizedTableNames);
        $this->assertContains('active_users', $normalizedViewNames);
    }

    /**
     * @param array<string, mixed> $options
     */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel('test', true);
    }

    private function connectOrSkip(string $connectionName): Connection
    {
        $connection = $this->tryConnect($connectionName);
        if (!$connection instanceof Connection) {
            $this->markTestSkipped(\sprintf('Connection "%s" is not reachable in this environment.', $connectionName));
        }

        return $connection;
    }

    private function tryConnect(string $connectionName): ?Connection
    {
        /** @var ManagerRegistry $registry */
        $registry = self::getContainer()->get('doctrine');

        try {
            $resolvedConnection = $registry->getConnection($connectionName);
            if (!$resolvedConnection instanceof Connection) {
                return null;
            }

            $resolvedConnection->executeQuery('SELECT 1')->fetchOne();

            return $resolvedConnection;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>|list<array<string, mixed>>
     */
    private function extractToonPayload(CallToolResult $result): array
    {
        $this->assertCount(1, $result->content);
        $this->assertInstanceOf(TextContent::class, $result->content[0]);

        /** @var TextContent $content */
        $content = $result->content[0];

        $decoded = Toon::decode((string) $content->text);
        $this->assertIsArray($decoded);

        return $decoded;
    }
}
