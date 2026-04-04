<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use MatesOfMate\DatabaseExtension\Capability\ConnectionResource;
use MatesOfMate\DatabaseExtension\Capability\DatabaseQueryTool;
use MatesOfMate\DatabaseExtension\Capability\DatabaseSchemaTool;
use MatesOfMate\DatabaseExtension\Mate\ApplicationContainerFactory;
use MatesOfMate\DatabaseExtension\ReadOnly\ReadOnlyMiddleware;
use MatesOfMate\DatabaseExtension\Service\ConnectionResolver;
use MatesOfMate\DatabaseExtension\Service\DatabaseSchemaService;
use MatesOfMate\DatabaseExtension\Service\SafeQueryExecutor;
use MatesOfMate\DatabaseExtension\Service\Schema\MysqlSchemaInspector;
use MatesOfMate\DatabaseExtension\Service\Schema\PostgreSqlSchemaInspector;
use MatesOfMate\DatabaseExtension\Service\Schema\SchemaInspectorFactory;
use MatesOfMate\DatabaseExtension\Service\Schema\SqliteSchemaInspector;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container, ContainerBuilder $containerBuilder): void {
    $services = $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    // Register core read-only and schema service layer.
    $services->set(ReadOnlyMiddleware::class);
    $services->set(SafeQueryExecutor::class);
    $services->set(MysqlSchemaInspector::class);
    $services->set(PostgreSqlSchemaInspector::class);
    $services->set(SqliteSchemaInspector::class);
    $services->set(SchemaInspectorFactory::class);
    $services->set(DatabaseSchemaService::class);

    // Mate builds a standalone ContainerBuilder (symfony/ai-mate) that never registers the
    // DoctrineBundle extension, yet MCP discovery still registers capability services. We must
    // always wire ConnectionResolver there so the container compiles. Symfony applications
    // without Doctrine must still skip it (see NoDoctrineKernelTest).
    $isMateBuildContainer = $containerBuilder->hasParameter('mate.root_dir');

    $doctrineServiceLayerAvailable = $containerBuilder->hasExtension('doctrine')
        || $containerBuilder->hasDefinition('doctrine')
        || $containerBuilder->hasDefinition('doctrine.dbal.default_connection')
        || $containerBuilder->hasParameter('doctrine.connections');

    if ($isMateBuildContainer || $doctrineServiceLayerAvailable) {
        $projectRoot = '';
        if ($containerBuilder->hasParameter('mate.root_dir')) {
            $projectRoot = (string) $containerBuilder->getParameter('mate.root_dir');
        } elseif ($containerBuilder->hasParameter('kernel.project_dir')) {
            $projectRoot = (string) $containerBuilder->getParameter('kernel.project_dir');
        }
        $containerBuilder->setParameter('matesofmate.database_extension.project_root', $projectRoot);

        $services->set('matesofmate.database_extension.application_container')
            ->class(ContainerInterface::class)
            ->factory([ApplicationContainerFactory::class, 'create'])
            ->args([service('service_container'), '%matesofmate.database_extension.project_root%']);

        $services->set(ConnectionResolver::class)
            ->arg('$container', service('matesofmate.database_extension.application_container'));
    }

    if (!$isMateBuildContainer && !$doctrineServiceLayerAvailable) {
        return;
    }

    $services->set(DatabaseQueryTool::class);
    $services->set(DatabaseSchemaTool::class);
    $services->set(ConnectionResource::class);
};
