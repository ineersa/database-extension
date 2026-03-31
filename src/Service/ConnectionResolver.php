<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\DatabaseExtension\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\DriverManager;
use MatesOfMate\DatabaseExtension\Exception\ToolUsageError;
use MatesOfMate\DatabaseExtension\ReadOnly\ReadOnlyMiddleware;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ConnectionResolver
{
    private const DOCTRINE_REGISTRY_SERVICE_ID = 'doctrine';
    private const DEFAULT_CONNECTION_SERVICE_ID = 'doctrine.dbal.default_connection';
    private const CONNECTION_SERVICE_ID_PATTERN = 'doctrine.dbal.%s_connection';
    private const DEFAULT_CONNECTION_PARAMETER = 'doctrine.default_connection';
    private const CONNECTIONS_PARAMETER = 'doctrine.connections';

    public function __construct(
        private readonly ContainerInterface $container,
        private readonly ReadOnlyMiddleware $readOnlyMiddleware,
    ) {
    }

    public function hasDoctrineServiceLayer(): bool
    {
        if ($this->container->has(self::DOCTRINE_REGISTRY_SERVICE_ID)) {
            return true;
        }

        if ($this->container->has(self::DEFAULT_CONNECTION_SERVICE_ID)) {
            return true;
        }

        return $this->container->hasParameter(self::CONNECTIONS_PARAMETER);
    }

    /**
     * @return array{
     *     name: string,
     *     default_name: string,
     *     default_used: bool,
     *     metadata: array{driver: ?string, platform: ?string, server_version: ?string},
     *     connection: Connection
     * }
     */
    public function resolve(?string $connectionName = null): array
    {
        if (!$this->hasDoctrineServiceLayer()) {
            throw new ToolUsageError(message: 'Doctrine DBAL services are not available in this application.', hint: 'Install and enable DoctrineBundle with at least one DBAL connection to use database-query and database-schema tools.');
        }

        $trimmedRequestedName = null === $connectionName ? null : trim($connectionName);
        $defaultConnectionName = $this->getDefaultConnectionName();
        $resolvedConnectionName = null === $trimmedRequestedName || '' === $trimmedRequestedName
            ? $defaultConnectionName
            : $trimmedRequestedName;

        $connection = $this->getDoctrineConnection($resolvedConnectionName);
        $metadata = $this->buildConnectionMetadata($connection);

        return [
            'name' => $resolvedConnectionName,
            'default_name' => $defaultConnectionName,
            'default_used' => null === $trimmedRequestedName || '' === $trimmedRequestedName,
            'metadata' => $metadata,
            'connection' => $this->rebuildReadOnlyConnection($connection),
        ];
    }

    /**
     * @return list<array{name: string, is_default: bool, driver: ?string, platform: ?string, server_version: ?string}>
     */
    public function listConnectionMetadata(): array
    {
        if (!$this->hasDoctrineServiceLayer()) {
            return [];
        }

        try {
            $defaultConnectionName = $this->getDefaultConnectionName();
            $connectionNames = $this->getConnectionNames();
        } catch (\Throwable) {
            return [];
        }

        if (!\in_array($defaultConnectionName, $connectionNames, true)) {
            $connectionNames[] = $defaultConnectionName;
        }

        $metadata = [];

        foreach ($connectionNames as $connectionName) {
            $connectionMetadata = [
                'driver' => null,
                'platform' => null,
                'server_version' => null,
            ];

            try {
                $connectionMetadata = $this->buildConnectionMetadata($this->getDoctrineConnection($connectionName));
            } catch (\Throwable) {
                // Keep metadata nullable when the concrete connection is currently broken.
            }

            $metadata[] = [
                'name' => $connectionName,
                'is_default' => $connectionName === $defaultConnectionName,
                'driver' => $connectionMetadata['driver'],
                'platform' => $connectionMetadata['platform'],
                'server_version' => $connectionMetadata['server_version'],
            ];
        }

        usort($metadata, static function (array $left, array $right): int {
            if ($left['is_default'] === $right['is_default']) {
                return $left['name'] <=> $right['name'];
            }

            return $left['is_default'] ? -1 : 1;
        });

        return $metadata;
    }

    /**
     * @return list<string>
     */
    private function getConnectionNames(): array
    {
        $registry = $this->getDoctrineRegistry();
        if (null !== $registry && method_exists($registry, 'getConnectionNames')) {
            $connectionNames = \call_user_func([$registry, 'getConnectionNames']);
            if (\is_array($connectionNames)) {
                return $this->normalizeConnectionNames($connectionNames);
            }
        }

        if ($this->container->hasParameter(self::CONNECTIONS_PARAMETER)) {
            $configuredConnections = $this->container->getParameter(self::CONNECTIONS_PARAMETER);
            if (\is_array($configuredConnections)) {
                $normalizedConnections = $this->normalizeConnectionNames($configuredConnections);
                if ([] !== $normalizedConnections) {
                    return $normalizedConnections;
                }
            }
        }

        if ($this->container->has(self::DEFAULT_CONNECTION_SERVICE_ID)) {
            return ['default'];
        }

        return [];
    }

    private function getDefaultConnectionName(): string
    {
        $registry = $this->getDoctrineRegistry();
        if (null !== $registry && method_exists($registry, 'getDefaultConnectionName')) {
            $defaultConnectionName = \call_user_func([$registry, 'getDefaultConnectionName']);
            if (\is_string($defaultConnectionName) && '' !== trim($defaultConnectionName)) {
                return trim($defaultConnectionName);
            }
        }

        if ($this->container->hasParameter(self::DEFAULT_CONNECTION_PARAMETER)) {
            $configuredDefault = $this->container->getParameter(self::DEFAULT_CONNECTION_PARAMETER);
            if (\is_string($configuredDefault) && '' !== trim($configuredDefault)) {
                return trim($configuredDefault);
            }
        }

        if ($this->container->has(self::DEFAULT_CONNECTION_SERVICE_ID)) {
            return 'default';
        }

        $connectionNames = $this->getConnectionNames();
        if ([] !== $connectionNames) {
            return $connectionNames[0];
        }

        throw new ToolUsageError(message: 'No Doctrine DBAL connections were found.', hint: 'Configure at least one Doctrine DBAL connection in doctrine.yaml to use database-query and database-schema.');
    }

    private function getDoctrineConnection(string $connectionName): Connection
    {
        $registry = $this->getDoctrineRegistry();
        if (null !== $registry && method_exists($registry, 'getConnection')) {
            try {
                $resolvedConnection = \call_user_func([$registry, 'getConnection'], $connectionName);
            } catch (\Throwable $throwable) {
                throw $this->mapConnectionResolutionFailure($connectionName, $throwable);
            }

            if ($resolvedConnection instanceof Connection) {
                return $resolvedConnection;
            }

            throw new ToolUsageError(message: \sprintf('Doctrine connection "%s" could not be resolved as a DBAL connection.', $connectionName), hint: 'Verify Doctrine DBAL connection wiring and retry.');
        }

        $serviceId = \sprintf(self::CONNECTION_SERVICE_ID_PATTERN, $connectionName);
        if ('default' === $connectionName && $this->container->has(self::DEFAULT_CONNECTION_SERVICE_ID)) {
            $serviceId = self::DEFAULT_CONNECTION_SERVICE_ID;
        }

        if (!$this->container->has($serviceId)) {
            throw new ToolUsageError(message: \sprintf('Connection "%s" is not configured.', $connectionName), hint: $this->buildUnknownConnectionHint());
        }

        $resolvedService = $this->container->get($serviceId);
        if ($resolvedService instanceof Connection) {
            return $resolvedService;
        }

        throw new ToolUsageError(message: \sprintf('Service "%s" is not a Doctrine DBAL connection.', $serviceId), hint: 'Verify Doctrine DBAL service definitions and retry.');
    }

    private function mapConnectionResolutionFailure(string $connectionName, \Throwable $throwable): ToolUsageError
    {
        try {
            $availableConnections = $this->getConnectionNames();
        } catch (\Throwable) {
            $availableConnections = [];
        }

        if (!\in_array($connectionName, $availableConnections, true)) {
            return new ToolUsageError(
                message: \sprintf('Connection "%s" is not configured.', $connectionName),
                hint: $this->buildUnknownConnectionHint(),
                previous: $throwable,
            );
        }

        return new ToolUsageError(
            message: $throwable->getMessage(),
            hint: \sprintf('Connection "%s" exists but could not be initialized. Verify credentials/host and retry.', $connectionName),
            previous: $throwable,
        );
    }

    private function rebuildReadOnlyConnection(Connection $connection): Connection
    {
        $configuration = clone $connection->getConfiguration();
        $middlewares = $configuration->getMiddlewares();

        if (!$this->hasReadOnlyMiddleware($middlewares)) {
            $middlewares[] = $this->readOnlyMiddleware;
            $configuration->setMiddlewares($middlewares);
        }

        return DriverManager::getConnection($connection->getParams(), $configuration);
    }

    /**
     * @param array<Middleware> $middlewares
     */
    private function hasReadOnlyMiddleware(array $middlewares): bool
    {
        foreach ($middlewares as $middleware) {
            if ($middleware instanceof ReadOnlyMiddleware) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{driver: ?string, platform: ?string, server_version: ?string}
     */
    private function buildConnectionMetadata(Connection $connection): array
    {
        $params = $connection->getParams();

        $driver = null;
        if (isset($params['driver']) && \is_string($params['driver']) && '' !== trim($params['driver'])) {
            $driver = strtolower(trim($params['driver']));
        }

        if (null === $driver && isset($params['driverClass']) && \is_string($params['driverClass'])) {
            $driver = strtolower(trim($params['driverClass']));
        }

        if (null === $driver) {
            $driver = strtolower($connection->getDriver()::class);
        }

        $serverVersion = isset($params['serverVersion']) && \is_string($params['serverVersion'])
            ? $params['serverVersion']
            : null;

        return [
            'driver' => $driver,
            'platform' => $this->detectPlatform($driver),
            'server_version' => $serverVersion,
        ];
    }

    private function detectPlatform(?string $driver): ?string
    {
        if (null === $driver || '' === trim($driver)) {
            return null;
        }

        $normalizedDriver = strtolower($driver);

        return match (true) {
            str_contains($normalizedDriver, 'mysql'), str_contains($normalizedDriver, 'mariadb') => 'mysql',
            str_contains($normalizedDriver, 'pgsql'), str_contains($normalizedDriver, 'postgres') => 'postgresql',
            str_contains($normalizedDriver, 'sqlite') => 'sqlite',
            str_contains($normalizedDriver, 'sqlsrv'), str_contains($normalizedDriver, 'mssql') => 'sqlserver',
            default => $normalizedDriver,
        };
    }

    private function buildUnknownConnectionHint(): string
    {
        $availableConnections = $this->getConnectionNames();
        if ([] === $availableConnections) {
            return 'No Doctrine DBAL connections are currently available. Configure at least one connection and retry.';
        }

        $defaultConnectionName = $this->getDefaultConnectionName();

        return \sprintf(
            'Use one of: %s. Omit `connection` to use default "%s".',
            implode(', ', $availableConnections),
            $defaultConnectionName,
        );
    }

    private function getDoctrineRegistry(): ?object
    {
        if (!$this->container->has(self::DOCTRINE_REGISTRY_SERVICE_ID)) {
            return null;
        }

        $registry = $this->container->get(self::DOCTRINE_REGISTRY_SERVICE_ID);

        return \is_object($registry) ? $registry : null;
    }

    /**
     * @param array<int|string, mixed> $connectionNames
     *
     * @return list<string>
     */
    private function normalizeConnectionNames(array $connectionNames): array
    {
        $normalizedNames = [];

        foreach ($connectionNames as $key => $value) {
            if (\is_string($key) && '' !== trim($key)) {
                $normalizedNames[] = trim($key);

                continue;
            }

            if (!\is_string($value)) {
                continue;
            }

            if ('' === trim($value)) {
                continue;
            }

            $trimmedValue = trim($value);
            $connectionName = $this->extractConnectionNameFromServiceId($trimmedValue);
            $normalizedNames[] = $connectionName ?? $trimmedValue;
        }

        $normalizedNames = array_values(array_unique($normalizedNames));
        sort($normalizedNames);

        return $normalizedNames;
    }

    private function extractConnectionNameFromServiceId(string $serviceId): ?string
    {
        if (!str_starts_with($serviceId, 'doctrine.dbal.')) {
            return null;
        }

        if (!str_ends_with($serviceId, '_connection')) {
            return null;
        }

        $connectionName = substr($serviceId, \strlen('doctrine.dbal.'));
        $connectionName = substr($connectionName, 0, -\strlen('_connection'));

        return '' === trim($connectionName) ? null : $connectionName;
    }
}
