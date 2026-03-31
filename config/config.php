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
use MatesOfMate\DatabaseExtension\ReadOnly\ReadOnlyMiddleware;
use MatesOfMate\DatabaseExtension\Service\DatabaseSchemaService;
use MatesOfMate\DatabaseExtension\Service\SafeQueryExecutor;
use MatesOfMate\DatabaseExtension\Service\Schema\MysqlSchemaInspector;
use MatesOfMate\DatabaseExtension\Service\Schema\PostgreSqlSchemaInspector;
use MatesOfMate\DatabaseExtension\Service\Schema\SchemaInspectorFactory;
use MatesOfMate\DatabaseExtension\Service\Schema\SqliteSchemaInspector;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
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

    // Register query/schema tools and connection summary resource.
    $services->set(DatabaseQueryTool::class);
    $services->set(DatabaseSchemaTool::class);
    $services->set(ConnectionResource::class);
};
