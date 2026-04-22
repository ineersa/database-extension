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
use HelgeSverre\Toon\Toon;
use MatesOfMate\DatabaseExtension\Capability\DatabaseQueryTool;
use MatesOfMate\DatabaseExtension\Capability\DatabaseSchemaTool;
use MatesOfMate\DatabaseExtension\Tests\Fixtures\App\TestKernel;
use MatesOfMate\DatabaseExtension\Tests\Fixtures\Database\RichDatabaseFixtures;
use MatesOfMate\DatabaseExtension\Tests\Support\RequiresDatabaseEnginesTrait;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * End-to-end database-query and database-schema checks against real DBAL connections
 * (parity with mysql-server inspector scenarios, without subprocess MCP).
 */
class DatabaseCapabilitiesIntegrationTest extends KernelTestCase
{
    use RequiresDatabaseEnginesTrait;

    public function testQueryToolRejectsInsert(): void
    {
        self::bootKernel();

        $connection = $this->connectOrSkip('default');
        RichDatabaseFixtures::reset($connection);

        /** @var DatabaseQueryTool $tool */
        $tool = self::getContainer()->get(DatabaseQueryTool::class);
        $result = $tool->execute("INSERT INTO users (id, name, email) VALUES (99, 'x', 'x@example.com')");

        $this->assertQueryToolErrorContains($result, 'Only read-only queries are allowed.');
    }

    public function testQueryToolRejectsDelete(): void
    {
        self::bootKernel();

        $connection = $this->connectOrSkip('default');
        RichDatabaseFixtures::reset($connection);

        /** @var DatabaseQueryTool $tool */
        $tool = self::getContainer()->get(DatabaseQueryTool::class);
        $result = $tool->execute('DELETE FROM users WHERE id = 1');

        $this->assertQueryToolErrorContains($result, 'Only read-only queries are allowed.');
    }

    public function testQueryToolRejectsSelectWithoutLimitOrWhere(): void
    {
        self::bootKernel();

        $connection = $this->connectOrSkip('default');
        RichDatabaseFixtures::reset($connection);

        /** @var DatabaseQueryTool $tool */
        $tool = self::getContainer()->get(DatabaseQueryTool::class);
        $result = $tool->execute('SELECT * FROM users');

        $this->assertQueryToolErrorContains($result, 'LIMIT');
    }

    public function testQueryToolTruncatesLongTextWhenMultipleRows(): void
    {
        self::bootKernel();

        $connection = $this->connectOrSkip('default');
        RichDatabaseFixtures::reset($connection);

        /** @var DatabaseQueryTool $tool */
        $tool = self::getContainer()->get(DatabaseQueryTool::class);
        $result = $tool->execute('SELECT * FROM pii_samples ORDER BY id LIMIT 10');

        $this->assertFalse($result->isError);
        $rows = $this->extractToonPayload($result);
        $this->assertIsList($rows);
        $this->assertGreaterThan(1, \count($rows));

        foreach ($rows as $row) {
            $this->assertIsArray($row);
            if (\array_key_exists('notes', $row)) {
                $this->assertSame('<TEXT>', $row['notes']);
            }
        }
    }

    public function testQueryToolPreservesLongTextWhenSingleRow(): void
    {
        self::bootKernel();

        $connection = $this->connectOrSkip('default');
        RichDatabaseFixtures::reset($connection);

        $expectedNotes = RichDatabaseFixtures::getExpectedPiiSamples()[0]['notes'];

        /** @var DatabaseQueryTool $tool */
        $tool = self::getContainer()->get(DatabaseQueryTool::class);
        $result = $tool->execute('SELECT * FROM pii_samples WHERE id = 1 LIMIT 1');

        $this->assertFalse($result->isError);
        $rows = $this->extractToonPayload($result);
        $this->assertIsList($rows);
        $this->assertCount(1, $rows);
        $this->assertSame($expectedNotes, $rows[0]['notes']);
    }

    public function testSchemaToolGlobFilterMatchesTables(): void
    {
        self::bootKernel();

        $connection = $this->connectOrSkip('default');
        RichDatabaseFixtures::reset($connection);

        /** @var DatabaseSchemaTool $tool */
        $tool = self::getContainer()->get(DatabaseSchemaTool::class);
        $result = $tool->execute(
            connection: null,
            filter: 'prod*',
            detail: 'summary',
            matchMode: 'glob',
            includeViews: false,
            includeRoutines: false,
        );

        $this->assertFalse($result->isError);
        $payload = $this->extractToonPayload($result);
        $this->assertIsArray($payload['tables'] ?? null);

        $normalized = array_map(
            static fn (string $name): string => trim($name, '"\'` '),
            $payload['tables'],
        );

        $this->assertContains('products', $normalized);
        $this->assertNotContains('users', $normalized);
    }

    public function testSchemaSummaryListsTriggerWhenIncludeRoutines(): void
    {
        self::bootKernel();

        $connection = $this->connectOrSkip('default');
        RichDatabaseFixtures::reset($connection);

        /** @var DatabaseSchemaTool $tool */
        $tool = self::getContainer()->get(DatabaseSchemaTool::class);
        $result = $tool->execute(
            connection: null,
            filter: 'trg_users_insert',
            detail: 'summary',
            matchMode: 'contains',
            includeViews: false,
            includeRoutines: true,
        );

        $this->assertFalse($result->isError);
        $payload = $this->extractToonPayload($result);
        $this->assertIsArray($payload['routines'] ?? null);
        $this->assertIsArray($payload['routines']['triggers'] ?? null);

        $normalized = array_map(
            static fn (string $name): string => trim($name, '"\'` '),
            $payload['routines']['triggers'],
        );

        $hasTrigger = \in_array('trg_users_insert', $normalized, true);
        if (!$hasTrigger) {
            foreach ($normalized as $name) {
                if (str_ends_with($name, 'trg_users_insert')) {
                    $hasTrigger = true;

                    break;
                }
            }
        }

        $this->assertTrue($hasTrigger, 'Expected trigger trg_users_insert in routines list.');
    }

    public function testSchemaToolColumnsDetailOnReachableConnections(): void
    {
        self::bootKernel();

        $tested = 0;

        foreach (['default', 'mysql', 'pgsql'] as $connectionName) {
            $connection = $this->connectionForMultiEngineLoop($connectionName);
            if (!$connection instanceof Connection) {
                continue;
            }

            RichDatabaseFixtures::reset($connection);

            /** @var DatabaseSchemaTool $tool */
            $tool = self::getContainer()->get(DatabaseSchemaTool::class);
            $result = $tool->execute(
                connection: $connectionName,
                filter: 'users',
                detail: 'columns',
                matchMode: 'contains',
                includeViews: false,
                includeRoutines: false,
            );

            $this->assertFalse($result->isError, \sprintf('Schema columns failed for "%s".', $connectionName));
            $payload = $this->extractToonPayload($result);
            $this->assertSame('columns', $payload['detail']);
            $this->assertIsArray($payload['tables'] ?? null);

            $usersTable = $this->findTablePayload($payload['tables'], 'users');
            $this->assertNotNull($usersTable, \sprintf('Expected users table in columns payload for "%s".', $connectionName));
            $this->assertIsArray($usersTable['columns'] ?? null);
            $this->assertArrayHasKey('email', $usersTable['columns']);
            $this->assertArrayHasKey('name', $usersTable['columns']);
            $this->assertIsString($usersTable['columns']['email']);

            ++$tested;
        }

        $this->assertAtLeastOneMultiEngineConnectionTested($tested);
    }

    public function testSchemaToolFullDetailWithViewsAndRoutinesOnReachableConnections(): void
    {
        self::bootKernel();

        $tested = 0;

        foreach (['default', 'mysql', 'pgsql'] as $connectionName) {
            $connection = $this->connectionForMultiEngineLoop($connectionName);
            if (!$connection instanceof Connection) {
                continue;
            }

            RichDatabaseFixtures::reset($connection);

            /** @var DatabaseSchemaTool $tool */
            $tool = self::getContainer()->get(DatabaseSchemaTool::class);
            $result = $tool->execute(
                connection: $connectionName,
                filter: '',
                detail: 'full',
                matchMode: 'contains',
                includeViews: true,
                includeRoutines: true,
            );

            $this->assertFalse($result->isError, \sprintf('Schema full failed for "%s".', $connectionName));
            $payload = $this->extractToonPayload($result);
            $this->assertSame('full', $payload['detail']);

            $usersTable = $this->findTablePayload($payload['tables'], 'users');
            $this->assertNotNull($usersTable, \sprintf('Expected users table in full payload for "%s".', $connectionName));
            $this->assertIsArray($usersTable['columns'] ?? null);
            $this->assertIsArray($usersTable['indexes'] ?? null);
            $this->assertNotSame([], $usersTable['indexes']);
            $this->assertIsArray($usersTable['foreign_keys'] ?? null);
            $this->assertIsArray($usersTable['triggers'] ?? null);

            $this->assertIsArray($payload['views'] ?? null);
            $activeView = $this->findTablePayload($payload['views'], 'active_users');
            $this->assertNotNull($activeView, \sprintf('Expected active_users view for "%s".', $connectionName));
            $this->assertArrayHasKey('definition', $activeView);
            $this->assertIsString($activeView['definition']);
            $this->assertNotSame('', trim($activeView['definition']));

            $this->assertIsArray($payload['routines'] ?? null);
            $this->assertIsArray($payload['routines']['stored_procedures'] ?? null);
            $this->assertIsArray($payload['routines']['functions'] ?? null);
            $this->assertIsArray($payload['routines']['sequences'] ?? null);
            $this->assertArrayNotHasKey('triggers', $payload['routines'], 'Full detail lists triggers per table, not under routines.');

            $this->assertNotSame(
                [],
                $usersTable['triggers'],
                \sprintf('Expected trigger metadata on users for "%s" (full detail).', $connectionName),
            );

            ++$tested;
        }

        $this->assertAtLeastOneMultiEngineConnectionTested($tested);
    }

    public function testSchemaToolEngineMetadataMatchesConnectionOnReachableConnections(): void
    {
        self::bootKernel();

        $tested = 0;

        foreach (['default', 'mysql', 'pgsql'] as $connectionName) {
            $connection = $this->connectionForMultiEngineLoop($connectionName);
            if (!$connection instanceof Connection) {
                continue;
            }

            RichDatabaseFixtures::reset($connection);

            $expectedEngine = match ($connectionName) {
                'mysql' => 'mysql',
                'pgsql' => 'postgresql',
                default => 'sqlite',
            };

            /** @var DatabaseSchemaTool $tool */
            $tool = self::getContainer()->get(DatabaseSchemaTool::class);
            $result = $tool->execute(
                connection: $connectionName,
                filter: 'users',
                detail: 'summary',
                matchMode: 'contains',
                includeViews: false,
                includeRoutines: false,
            );

            $this->assertFalse($result->isError, \sprintf('Schema summary failed for "%s".', $connectionName));
            $payload = $this->extractToonPayload($result);
            $this->assertSame($expectedEngine, $payload['engine'], \sprintf('Engine mismatch for "%s".', $connectionName));
            $this->assertSame($connectionName, $payload['connection']);

            ++$tested;
        }

        $this->assertAtLeastOneMultiEngineConnectionTested($tested);
    }

    public function testSelectWithLimitReturnsSeededUsersOnReachableConnections(): void
    {
        self::bootKernel();

        $tested = 0;

        foreach (['default', 'mysql', 'pgsql'] as $connectionName) {
            $connection = $this->connectionForMultiEngineLoop($connectionName);
            if (!$connection instanceof Connection) {
                continue;
            }

            RichDatabaseFixtures::reset($connection);

            $driver = $this->resolveDriverKey($connection);
            $expectedFirstEmail = RichDatabaseFixtures::getExpectedUsers($driver)[0]['email'];

            /** @var DatabaseQueryTool $tool */
            $tool = self::getContainer()->get(DatabaseQueryTool::class);
            $result = $tool->execute(
                'SELECT id, name, email FROM users ORDER BY id LIMIT 10',
                $connectionName,
            );

            $this->assertFalse($result->isError, \sprintf('Query failed for connection "%s".', $connectionName));
            $rows = $this->extractToonPayload($result);
            $this->assertIsList($rows);
            $this->assertGreaterThan(0, \count($rows));
            $this->assertSame($expectedFirstEmail, $rows[0]['email']);

            ++$tested;
        }

        $this->assertAtLeastOneMultiEngineConnectionTested($tested);
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
        $connection = $this->tryConnectDoctrineRegistry($connectionName);
        if (!$connection instanceof Connection) {
            $this->markTestSkipped(\sprintf('Connection "%s" is not reachable in this environment.', $connectionName));
        }

        return $connection;
    }

    /**
     * @param array<string, mixed> $entries keyed by quoted or raw identifiers
     *
     * @return array<string, mixed>|null
     */
    private function findTablePayload(array $entries, string $logicalName): ?array
    {
        foreach ($entries as $key => $value) {
            if (!\is_array($value)) {
                continue;
            }

            $trimmedKey = trim((string) $key, '"\'` ');
            if ($trimmedKey === $logicalName) {
                return $value;
            }

            // PostgreSQL/SQL Server often use schema-qualified names (e.g. public.active_users).
            $parts = preg_split('/\s*\.\s*/', $trimmedKey);
            if (false !== $parts && [] !== $parts) {
                $leaf = trim(end($parts), '"\'` ');
                if ($leaf === $logicalName) {
                    return $value;
                }
            }
        }

        return null;
    }

    private function resolveDriverKey(Connection $connection): string
    {
        /** @var array{driver?: string, url?: string} $params */
        $params = $connection->getParams();

        if (isset($params['driver']) && \is_string($params['driver'])) {
            return $params['driver'];
        }

        if (isset($params['url']) && \is_string($params['url'])) {
            if (str_starts_with($params['url'], 'mysql://') || str_starts_with($params['url'], 'pdo-mysql://')) {
                return 'pdo_mysql';
            }

            if (
                str_starts_with($params['url'], 'postgresql://')
                || str_starts_with($params['url'], 'postgres://')
                || str_starts_with($params['url'], 'pdo-pgsql://')
            ) {
                return 'pdo_pgsql';
            }
        }

        return 'pdo_sqlite';
    }

    /**
     * @return array<string, mixed>|list<array<string, mixed>>
     */
    private function extractToonPayload(CallToolResult $result): array
    {
        $this->assertCount(1, $result->content);
        $this->assertInstanceOf(TextContent::class, $result->content[0]);

        $content = $result->content[0];

        $decoded = Toon::decode((string) $content->text);
        $this->assertIsArray($decoded);

        return $decoded;
    }

    private function assertQueryToolErrorContains(CallToolResult $result, string $needle): void
    {
        $this->assertTrue($result->isError);

        $payload = $this->extractToonPayload($result);
        $this->assertIsArray($payload);
        $this->assertArrayHasKey('error', $payload);
        $this->assertStringContainsString($needle, (string) $payload['error']);
    }
}
