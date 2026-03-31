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
use MatesOfMate\DatabaseExtension\ReadOnly\ReadOnlyMiddleware;
use MatesOfMate\DatabaseExtension\Service\ConnectionResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class ConnectionResolverTest extends TestCase
{
    public function testResolvesDefaultConnectionWhenConnectionNameIsOmitted(): void
    {
        $defaultConnection = $this->createSqliteConnection();
        $registry = new TestDoctrineRegistry([
            'default' => $defaultConnection,
        ]);

        $container = new ContainerBuilder();
        $container->set('doctrine', $registry);

        $resolver = new ConnectionResolver($container, new ReadOnlyMiddleware());

        $resolved = $resolver->resolve();

        $this->assertSame('default', $resolved['name']);
        $this->assertSame('default', $resolved['default_name']);
        $this->assertTrue($resolved['default_used']);
        $this->assertSame('pdo_sqlite', $resolved['metadata']['driver']);
        $this->assertSame('sqlite', $resolved['metadata']['platform']);
        $this->assertInstanceOf(Connection::class, $resolved['connection']);
        $this->assertNotSame($defaultConnection, $resolved['connection']);

        $middlewares = $resolved['connection']->getConfiguration()->getMiddlewares();

        $this->assertCount(1, $middlewares);
        $this->assertInstanceOf(ReadOnlyMiddleware::class, $middlewares[0]);
    }

    public function testResolvesNamedConnectionAndListsConnectionMetadata(): void
    {
        $defaultConnection = $this->createSqliteConnection();
        $analyticsConnection = $this->createSqliteConnection();

        $registry = new TestDoctrineRegistry([
            'default' => $defaultConnection,
            'analytics' => $analyticsConnection,
        ]);

        $container = new ContainerBuilder();
        $container->set('doctrine', $registry);

        $resolver = new ConnectionResolver($container, new ReadOnlyMiddleware());

        $resolved = $resolver->resolve('analytics');

        $this->assertSame('analytics', $resolved['name']);
        $this->assertSame('default', $resolved['default_name']);
        $this->assertFalse($resolved['default_used']);

        $metadata = $resolver->listConnectionMetadata();

        $this->assertCount(2, $metadata);
        $this->assertSame('default', $metadata[0]['name']);
        $this->assertTrue($metadata[0]['is_default']);
        $this->assertSame('analytics', $metadata[1]['name']);
        $this->assertFalse($metadata[1]['is_default']);
    }

    public function testThrowsStructuredErrorForUnknownConnection(): void
    {
        $registry = new TestDoctrineRegistry([
            'default' => $this->createSqliteConnection(),
        ]);

        $container = new ContainerBuilder();
        $container->set('doctrine', $registry);

        $resolver = new ConnectionResolver($container, new ReadOnlyMiddleware());

        try {
            $resolver->resolve('missing');
            $this->fail('Expected ToolUsageError was not thrown.');
        } catch (ToolUsageError $error) {
            $this->assertSame('Connection "missing" is not configured.', $error->getMessage());
            $this->assertNotNull($error->getHint());
            $this->assertStringContainsString('Use one of: default.', $error->getHint());
        }
    }

    public function testReturnsFalseWhenDoctrineServiceLayerIsUnavailable(): void
    {
        $container = new ContainerBuilder();
        $resolver = new ConnectionResolver($container, new ReadOnlyMiddleware());

        $this->assertFalse($resolver->hasDoctrineServiceLayer());
        $this->assertSame([], $resolver->listConnectionMetadata());
    }

    private function createSqliteConnection(): Connection
    {
        return DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ], new Configuration());
    }
}

/**
 * @internal
 */
class TestDoctrineRegistry
{
    /**
     * @param array<string, Connection> $connections
     */
    public function __construct(
        private readonly array $connections,
        private readonly string $defaultConnectionName = 'default',
    ) {
    }

    /**
     * @return list<string>
     */
    public function getConnectionNames(): array
    {
        return array_keys($this->connections);
    }

    public function getDefaultConnectionName(): string
    {
        return $this->defaultConnectionName;
    }

    public function getConnection(string $name): Connection
    {
        if (!isset($this->connections[$name])) {
            throw new \InvalidArgumentException(\sprintf('Connection "%s" does not exist.', $name));
        }

        return $this->connections[$name];
    }
}
