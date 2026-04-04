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

use MatesOfMate\DatabaseExtension\Mate\ApplicationContainerFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
class ApplicationContainerFactoryTest extends TestCase
{
    protected function tearDown(): void
    {
        ApplicationContainerFactory::resetKernelHolderForTesting();
    }

    public function testFallsBackToMateContainerWhenProjectRootIsInvalid(): void
    {
        $mate = $this->createMock(ContainerInterface::class);
        $mate->method('has')->willReturn(false);
        $mate->method('hasParameter')->willReturn(false);

        $result = ApplicationContainerFactory::create($mate, '/does/not/exist/'.uniqid('', true));

        $this->assertSame($mate, $result);
    }

    public function testFallsBackWhenKernelClassDoesNotExist(): void
    {
        $mate = $this->createMock(ContainerInterface::class);
        $mate->method('has')->willReturn(false);
        $mate->method('hasParameter')->willReturn(false);
        $projectRoot = realpath(\dirname(__DIR__, 2));
        $this->assertNotFalse($projectRoot);

        $result = ApplicationContainerFactory::create($mate, $projectRoot);

        $this->assertSame($mate, $result);
    }

    public function testReturnsSameContainerWhenDoctrineServiceLayerIsPresent(): void
    {
        $mate = $this->createMock(ContainerInterface::class);
        $mate->method('has')->willReturnCallback(static fn (string $id): bool => 'doctrine' === $id);
        $mate->method('hasParameter')->willReturn(false);

        $result = ApplicationContainerFactory::create($mate, '/any/root');

        $this->assertSame($mate, $result);
    }

    public function testReturnsMateContainerWhenProjectRootIsEmpty(): void
    {
        $mate = $this->createMock(ContainerInterface::class);
        $mate->method('has')->willReturn(false);
        $mate->method('hasParameter')->willReturn(false);

        $this->assertSame($mate, ApplicationContainerFactory::create($mate, ''));
    }
}
