<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\DatabaseExtension\Tests\Fixtures\App;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use MatesOfMate\DatabaseExtension\Capability\ConnectionResource;
use MatesOfMate\DatabaseExtension\Capability\DatabaseQueryTool;
use MatesOfMate\DatabaseExtension\Capability\DatabaseSchemaTool;
use MatesOfMate\DatabaseExtension\Service\ConnectionResolver;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;

class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new DoctrineBundle(),
        ];
    }

    public function getProjectDir(): string
    {
        return \dirname(__DIR__, 3);
    }

    public function getCacheDir(): string
    {
        return $this->getProjectDir().'/var/cache/'.$this->environment.'/integration-test-kernel';
    }

    protected function configureContainer(ContainerConfigurator $container, LoaderInterface $loader, ContainerBuilder $builder): void
    {
        $container->extension('framework', [
            'secret' => 'database-extension-test-secret',
            'test' => true,
            'http_method_override' => false,
        ]);

        $container->extension('doctrine', [
            'dbal' => [
                'default_connection' => 'default',
                'connections' => [
                    'default' => [
                        'driver' => 'pdo_sqlite',
                        'path' => $this->readEnv('TEST_SQLITE_PATH', sys_get_temp_dir().'/database_extension_test.sqlite'),
                    ],
                    'mysql' => [
                        'url' => $this->readEnv('TEST_MYSQL_URL', 'mysql://test_user:test_password@127.0.0.1:13306/database_extension_test?serverVersion=8.0'),
                    ],
                    'pgsql' => [
                        'url' => $this->readEnv('TEST_PGSQL_URL', 'postgresql://test_user:test_password@127.0.0.1:15432/database_extension_test?serverVersion=16'),
                    ],
                ],
            ],
        ]);

        $container->import($this->getProjectDir().'/config/config.php');

        $services = $container->services();
        $services->get(ConnectionResolver::class)->public();
        $services->get(DatabaseQueryTool::class)->public();
        $services->get(DatabaseSchemaTool::class)->public();
        $services->get(ConnectionResource::class)->public();
    }

    private function readEnv(string $name, string $default): string
    {
        $value = $_SERVER[$name] ?? $_ENV[$name] ?? getenv($name);

        if (!\is_string($value)) {
            return $default;
        }

        $trimmedValue = trim($value);

        return '' === $trimmedValue ? $default : $trimmedValue;
    }
}
