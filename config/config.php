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
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    // Register query/schema tools and connection summary resource
    $services->set(DatabaseQueryTool::class);
    $services->set(DatabaseSchemaTool::class);
    $services->set(ConnectionResource::class);

    // Sample registration with constructor dependencies:
    // $services->set(YourTool::class)
    //     ->arg('$someParameter', '%some.parameter%');
};
