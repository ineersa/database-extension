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

use MatesOfMate\DatabaseExtension\Capability\ConnectionResource;
use MatesOfMate\DatabaseExtension\Capability\DatabaseQueryTool;
use MatesOfMate\DatabaseExtension\Capability\DatabaseSchemaTool;
use MatesOfMate\DatabaseExtension\Service\ConnectionResolver;
use MatesOfMate\DatabaseExtension\Tests\Fixtures\App\NoDoctrineKernel;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

class NoDoctrineKernelTest extends KernelTestCase
{
    public function testDoesNotExposeDatabaseCapabilitiesWhenDoctrineBundleIsMissing(): void
    {
        self::bootKernel();

        $container = self::getContainer();

        $this->assertFalse($container->has(ConnectionResolver::class));
        $this->assertFalse($container->has(DatabaseQueryTool::class));
        $this->assertFalse($container->has(DatabaseSchemaTool::class));
        $this->assertFalse($container->has(ConnectionResource::class));
    }

    /**
     * @param array<string, mixed> $options
     */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new NoDoctrineKernel('test', true);
    }
}
