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
use MatesOfMate\DatabaseExtension\Capability\DatabaseSchemaTool;
use MatesOfMate\DatabaseExtension\Tests\Fixtures\App\TestKernel;
use MatesOfMate\DatabaseExtension\Tests\Fixtures\Database\RichDatabaseFixtures;
use MatesOfMate\DatabaseExtension\Tests\Support\RequiresDatabaseEnginesTrait;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Targets MySQL- and PostgreSQL-specific schema inspector code paths (global trigger lists,
 * function vs procedure definitions) when those engines are reachable (e.g. Docker coverage).
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class SchemaInspectorEnginesIntegrationTest extends KernelTestCase
{
    use RequiresDatabaseEnginesTrait;

    public function testMysqlSummaryIncludeRoutinesListsGlobalTriggers(): void
    {
        self::bootKernel();

        $connection = $this->tryConnectDoctrineRegistry('mysql');
        if (!$connection instanceof Connection) {
            $this->markTestSkipped('MySQL connection is not reachable.');
        }

        RichDatabaseFixtures::reset($connection);

        /** @var DatabaseSchemaTool $tool */
        $tool = self::getContainer()->get(DatabaseSchemaTool::class);
        $result = $tool->execute(
            connection: 'mysql',
            filter: '',
            detail: 'summary',
            matchMode: 'contains',
            includeViews: false,
            includeRoutines: true,
        );

        $this->assertFalse($result->isError);
        $payload = $this->extractToonPayload($result);
        $this->assertIsArray($payload['routines']['triggers'] ?? null);
        $this->assertObjectNameInList($payload['routines']['triggers'], 'trg_users_insert');
    }

    public function testPgsqlSummaryIncludeRoutinesListsGlobalTriggers(): void
    {
        self::bootKernel();

        $connection = $this->tryConnectDoctrineRegistry('pgsql');
        if (!$connection instanceof Connection) {
            $this->markTestSkipped('PostgreSQL connection is not reachable.');
        }

        RichDatabaseFixtures::reset($connection);

        /** @var DatabaseSchemaTool $tool */
        $tool = self::getContainer()->get(DatabaseSchemaTool::class);
        $result = $tool->execute(
            connection: 'pgsql',
            filter: '',
            detail: 'summary',
            matchMode: 'contains',
            includeViews: false,
            includeRoutines: true,
        );

        $this->assertFalse($result->isError);
        $payload = $this->extractToonPayload($result);
        $this->assertIsArray($payload['routines']['triggers'] ?? null);
        $this->assertObjectNameInList($payload['routines']['triggers'], 'trg_users_insert');
    }

    public function testMysqlFullIncludeRoutinesLoadsFunctionDefinition(): void
    {
        self::bootKernel();

        $connection = $this->tryConnectDoctrineRegistry('mysql');
        if (!$connection instanceof Connection) {
            $this->markTestSkipped('MySQL connection is not reachable.');
        }

        RichDatabaseFixtures::reset($connection);

        /** @var DatabaseSchemaTool $tool */
        $tool = self::getContainer()->get(DatabaseSchemaTool::class);
        $result = $tool->execute(
            connection: 'mysql',
            filter: '',
            detail: 'full',
            matchMode: 'contains',
            includeViews: false,
            includeRoutines: true,
        );

        $this->assertFalse($result->isError);
        $payload = $this->extractToonPayload($result);
        $fn = $this->findNamedStructureEntry($payload['routines']['functions'] ?? [], 'fixture_schema_double');
        $this->assertNotNull($fn, 'Expected fixture_schema_double in MySQL functions payload.');
        $this->assertSame('function', $fn['type'] ?? null);
        $this->assertArrayHasKey('definition', $fn);
        $this->assertIsString($fn['definition']);
        $this->assertTrue(
            str_contains(strtolower($fn['definition']), 'fixture_schema_double'),
            'Expected function definition to mention fixture_schema_double.',
        );
    }

    public function testPgsqlFullIncludeRoutinesLoadsProcedureDefinition(): void
    {
        self::bootKernel();

        $connection = $this->tryConnectDoctrineRegistry('pgsql');
        if (!$connection instanceof Connection) {
            $this->markTestSkipped('PostgreSQL connection is not reachable.');
        }

        RichDatabaseFixtures::reset($connection);

        /** @var DatabaseSchemaTool $tool */
        $tool = self::getContainer()->get(DatabaseSchemaTool::class);
        $result = $tool->execute(
            connection: 'pgsql',
            filter: '',
            detail: 'full',
            matchMode: 'contains',
            includeViews: false,
            includeRoutines: true,
        );

        $this->assertFalse($result->isError);
        $payload = $this->extractToonPayload($result);
        $proc = $this->findNamedStructureEntry($payload['routines']['stored_procedures'] ?? [], 'fixture_schema_noop');
        $this->assertNotNull($proc, 'Expected fixture_schema_noop in PostgreSQL stored_procedures payload.');
        $this->assertSame('procedure', $proc['type'] ?? null);
        $this->assertArrayHasKey('definition', $proc);
        $this->assertIsString($proc['definition']);
        $this->assertTrue(
            str_contains(strtolower($proc['definition']), 'fixture_schema_noop'),
            'Expected procedure definition to mention fixture_schema_noop.',
        );
    }

    /**
     * @param array<string, mixed> $options
     */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel('test', true);
    }

    /**
     * @param array<mixed> $names
     */
    private function assertObjectNameInList(array $names, string $logicalName): void
    {
        foreach ($names as $name) {
            if (!\is_string($name)) {
                continue;
            }

            $trimmed = trim($name, '"\'` ');
            if ($trimmed === $logicalName) {
                return;
            }

            $parts = preg_split('/\s*\.\s*/', $trimmed);
            if (false !== $parts && [] !== $parts) {
                $leaf = trim(end($parts), '"\'` ');
                if ($leaf === $logicalName) {
                    return;
                }
            }
        }

        $this->fail(\sprintf('Expected object name "%s" in list: %s', $logicalName, json_encode($names, \JSON_THROW_ON_ERROR)));
    }

    /**
     * @param array<string, mixed> $entries
     *
     * @return array<string, mixed>|null
     */
    private function findNamedStructureEntry(array $entries, string $logicalName): ?array
    {
        foreach ($entries as $key => $value) {
            if (!\is_array($value)) {
                continue;
            }

            $trimmedKey = trim((string) $key, '"\'` ');
            if ($trimmedKey === $logicalName) {
                return $value;
            }

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
}
