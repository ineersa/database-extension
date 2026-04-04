<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\DatabaseExtension\Mate;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * When AI Mate builds its own DI container, Doctrine lives in the application's kernel
 * container — not in Mate's {@see ContainerInterface} alias. This factory boots the
 * Symfony kernel once per process and returns its container when possible.
 *
 * @internal
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ApplicationContainerFactory
{
    private static ?KernelInterface $kernel = null;

    /**
     * Clears the booted kernel reference (for tests only).
     *
     * @internal
     */
    public static function resetKernelHolderForTesting(): void
    {
        self::$kernel = null;
    }

    /**
     * @param non-empty-string $projectRoot
     */
    public static function create(ContainerInterface $mateContainer, string $projectRoot): ContainerInterface
    {
        $applicationContainer = self::tryBootKernelContainer($projectRoot);
        if (null !== $applicationContainer) {
            return $applicationContainer;
        }

        return $mateContainer;
    }

    /**
     * @param non-empty-string $projectRoot
     */
    private static function tryBootKernelContainer(string $projectRoot): ?ContainerInterface
    {
        if (null !== self::$kernel) {
            return self::$kernel->getContainer();
        }

        $autoload = $projectRoot.'/vendor/autoload.php';
        if (!is_file($autoload)) {
            return null;
        }

        require_once $autoload;

        $projectEnv = $projectRoot.'/.env';
        if (is_file($projectEnv) && class_exists(Dotenv::class)) {
            (new Dotenv())->bootEnv($projectEnv);
        }

        $kernelClass = self::resolveKernelClass();
        if (!class_exists($kernelClass)) {
            return null;
        }

        try {
            $env = self::readEnvString('APP_ENV', 'dev');
            $debug = self::readEnvBool('APP_DEBUG', true);
            $kernel = new $kernelClass($env, $debug);
            if (!$kernel instanceof KernelInterface) {
                return null;
            }

            $kernel->boot();

            self::$kernel = $kernel;

            return $kernel->getContainer();
        } catch (\Throwable) {
            return null;
        }
    }

    private static function resolveKernelClass(): string
    {
        $fromEnv = self::readEnvString('MATE_SYMFONY_KERNEL_CLASS', '');
        if ('' !== $fromEnv) {
            return $fromEnv;
        }

        return 'App\\Kernel';
    }

    private static function readEnvString(string $name, string $default): string
    {
        $value = $_SERVER[$name] ?? $_ENV[$name] ?? getenv($name);

        if (!\is_string($value) || '' === trim($value)) {
            return $default;
        }

        return trim($value);
    }

    private static function readEnvBool(string $name, bool $default): bool
    {
        $raw = $_SERVER[$name] ?? $_ENV[$name] ?? getenv($name);
        if (false === $raw || '' === $raw) {
            return $default;
        }

        if (\is_bool($raw)) {
            return $raw;
        }

        $normalized = filter_var((string) $raw, \FILTER_VALIDATE_BOOLEAN, \FILTER_NULL_ON_FAILURE);
        if (null !== $normalized) {
            return $normalized;
        }

        return $default;
    }
}
