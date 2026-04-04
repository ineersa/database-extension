<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\DatabaseExtension\Tests\Unit;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use MatesOfMate\DatabaseExtension\Exception\ToolUsageError;
use MatesOfMate\DatabaseExtension\Service\SafeQueryExecutor;
use PHPUnit\Framework\TestCase;

class SafeQueryExecutorTest extends TestCase
{
    public function testAcceptsSelectWithLimit(): void
    {
        $executor = new SafeQueryExecutor();

        $executor->validateReadOnlyQuery('SELECT id, email FROM users LIMIT 10');

        $this->addToAssertionCount(1);
    }

    public function testRejectsEmptyQuery(): void
    {
        $executor = new SafeQueryExecutor();

        try {
            $executor->validateReadOnlyQuery(' ');
            $this->fail('Expected ToolUsageError was not thrown.');
        } catch (ToolUsageError $error) {
            $this->assertSame('Query must not be empty.', $error->getMessage());
            $this->assertSame('Provide a read-only SELECT query and include LIMIT 10 when no WHERE clause is present.', $error->getHint());
        }
    }

    public function testRejectsWriteStatement(): void
    {
        $executor = new SafeQueryExecutor();

        try {
            $executor->validateReadOnlyQuery('DELETE FROM users WHERE id = 1');
            $this->fail('Expected ToolUsageError was not thrown.');
        } catch (ToolUsageError $error) {
            $this->assertSame('Only read-only queries are allowed.', $error->getMessage());
            $this->assertSame('Use SELECT, SHOW, EXPLAIN, DESCRIBE, DESC, WITH … SELECT, VALUES, or TABLE (see database-schema first).', $error->getHint());
        }
    }

    public function testRejectsSelectWithoutWhereOrLimit(): void
    {
        $executor = new SafeQueryExecutor();

        try {
            $executor->validateReadOnlyQuery('SELECT id, email FROM users');
            $this->fail('Expected ToolUsageError was not thrown.');
        } catch (ToolUsageError $error) {
            $this->assertSame('SELECT queries without WHERE require LIMIT.', $error->getMessage());
            $this->assertSame('Add LIMIT 10 (or FETCH NEXT), or add a WHERE clause.', $error->getHint());
        }
    }

    public function testAllowsAggregateOnlyQueryWithoutLimit(): void
    {
        $executor = new SafeQueryExecutor();

        $executor->validateReadOnlyQuery('SELECT COUNT(*) FROM users');

        $this->addToAssertionCount(1);
    }

    public function testRejectsMultipleStatements(): void
    {
        $executor = new SafeQueryExecutor();

        try {
            $executor->validateReadOnlyQuery('SELECT id FROM users LIMIT 1; SELECT id FROM users LIMIT 1;');
            $this->fail('Expected ToolUsageError was not thrown.');
        } catch (ToolUsageError $error) {
            $this->assertSame('Only one SQL statement is allowed per call.', $error->getMessage());
            $this->assertSame('Split multi-statement requests into separate database-query calls.', $error->getHint());
        }
    }

    public function testAcceptsSingleStatementWithSemicolonInsideDoubleQuotedLiteral(): void
    {
        $executor = new SafeQueryExecutor();

        $executor->validateReadOnlyQuery('SELECT id FROM users WHERE code = "asd;asd" LIMIT 10');

        $this->addToAssertionCount(1);
    }

    public function testAcceptsSingleStatementWithSemicolonInsideSingleQuotedLiteral(): void
    {
        $executor = new SafeQueryExecutor();

        $executor->validateReadOnlyQuery('SELECT id FROM users WHERE code = \'a;b\' LIMIT 10');

        $this->addToAssertionCount(1);
    }

    public function testAcceptsSingleStatementWithSemicolonAfterEscapedSingleQuoteInLiteral(): void
    {
        $executor = new SafeQueryExecutor();

        $executor->validateReadOnlyQuery('SELECT id FROM users WHERE code = \'a\'\'b;c\' LIMIT 10');

        $this->addToAssertionCount(1);
    }

    public function testAcceptsWithFollowedBySelect(): void
    {
        $executor = new SafeQueryExecutor();

        $executor->validateReadOnlyQuery('WITH c AS (SELECT 1 AS n) SELECT n FROM c LIMIT 10');

        $this->addToAssertionCount(1);
    }

    public function testRejectsWithWithoutFollowingSelect(): void
    {
        $executor = new SafeQueryExecutor();

        try {
            $executor->validateReadOnlyQuery('WITH c AS (SELECT 1)');
            $this->fail('Expected ToolUsageError was not thrown.');
        } catch (ToolUsageError $error) {
            $this->assertSame('Only read-only queries are allowed.', $error->getMessage());
        }
    }

    public function testAcceptsExplainSelect(): void
    {
        $executor = new SafeQueryExecutor();

        $executor->validateReadOnlyQuery('EXPLAIN SELECT 1 LIMIT 1');

        $this->addToAssertionCount(1);
    }

    public function testAcceptsValuesStatement(): void
    {
        $executor = new SafeQueryExecutor();

        $executor->validateReadOnlyQuery('VALUES (1), (2)');

        $this->addToAssertionCount(1);
    }

    public function testExecuteTruncatesLongTextWhenMultipleRowsAreReturned(): void
    {
        $connection = $this->createSqliteConnection();
        $connection->executeStatement('CREATE TABLE notes (id INTEGER PRIMARY KEY, body TEXT NOT NULL)');

        $connection->insert('notes', ['id' => 1, 'body' => str_repeat('a', 240)]);
        $connection->insert('notes', ['id' => 2, 'body' => str_repeat('b', 240)]);

        $executor = new SafeQueryExecutor();
        $rows = $executor->execute($connection, 'SELECT id, body FROM notes ORDER BY id LIMIT 10');

        $this->assertCount(2, $rows);
        $this->assertSame('<TEXT>', $rows[0]['body']);
        $this->assertSame('<TEXT>', $rows[1]['body']);
    }

    public function testExecuteKeepsLongTextForSingleRowResult(): void
    {
        $connection = $this->createSqliteConnection();
        $connection->executeStatement('CREATE TABLE notes (id INTEGER PRIMARY KEY, body TEXT NOT NULL)');

        $longText = str_repeat('a', 240);
        $connection->insert('notes', ['id' => 1, 'body' => $longText]);

        $executor = new SafeQueryExecutor();
        $rows = $executor->execute($connection, 'SELECT id, body FROM notes WHERE id = 1');

        $this->assertCount(1, $rows);
        $this->assertSame($longText, $rows[0]['body']);
    }

    private function createSqliteConnection(): Connection
    {
        return DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ], new Configuration());
    }
}
