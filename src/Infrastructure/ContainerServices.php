<?php

declare(strict_types=1);

namespace App\Infrastructure;

use Psr\Container\ContainerInterface;

/** Checked boundary for PSR-11's untyped service results and PHP configuration files. */
final class ContainerServices
{
    public static function create(): ContainerInterface
    {
        $factory = require __DIR__ . '/../../config/container.php';
        if (!is_callable($factory)) { throw new \LogicException('Invalid container factory'); }
        $container = $factory();
        if (!$container instanceof ContainerInterface) { throw new \LogicException('Invalid container'); }
        return $container;
    }

    /** @template T of object
     * @param class-string<T> $class
     * @return T
     */
    public static function get(ContainerInterface $container, string $class): object
    {
        $service = $container->get($class);
        if (!$service instanceof $class) { throw new \LogicException('Invalid service: ' . $class); }
        return $service;
    }
}
