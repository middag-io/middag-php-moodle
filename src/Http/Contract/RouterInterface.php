<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Http\Contract;

use Middag\Moodle\Http\Routing\Router;
use Psr\Container\ContainerInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;

/**
 * Adapter-local router contract.
 *
 * The framework no longer ships a router contract — URL generation and route
 * resolution are host concerns. This contract pins the surface the Moodle
 * adapter's {@see Router} exposes and that the kernel,
 * controllers, settings tree and Inertia bootstrap type against.
 *
 * @internal
 */
interface RouterInterface
{
    /**
     * Initialize the routing context (base URL, host, scheme) from globals.
     */
    public function initializeContext(): void;

    /**
     * Retrieve the registered route collection.
     */
    public function getRoutes(): RouteCollection;

    /**
     * Get the current routing context (host, scheme, base path).
     */
    public function getContext(): ?RequestContext;

    /**
     * Register default system routes and global regex requirements.
     */
    public function registerDefaultRoutes(): void;

    /**
     * Delegate annotation/attribute scanning to the route loader.
     *
     * @param ContainerInterface $container      DI container used to autowire controllers
     * @param null|string        $specific_class optional FQCN to scan specifically
     */
    public function scanAnnotations(ContainerInterface $container, ?string $specific_class = null): void;

    /**
     * Generate a URL string for a named route.
     *
     * @param string               $route          route name
     * @param array<string, mixed> $parameters     route parameters
     * @param int                  $reference_type absolute path or absolute URL
     */
    public function generateUrl(string $route, array $parameters = [], int $reference_type = 1): string;
}
