<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Kernel\Loader;

use Middag\Framework\Http\Contract\RouteLoaderInterface as route_loader_interface;
use Middag\Framework\Kernel\Contract\LoaderFailurePolicyInterface as boot_failure_policy;
use Middag\Moodle\Kernel\Config\ComponentContext;
use Middag\Moodle\Kernel\Http\AbstractApiController as abstract_api_controller;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Routing\Attribute\Route as RouteAttribute;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Route Loader.
 *
 * Responsible for inspecting classes via Reflection and extracting
 * PHP 8 #[Route] attributes to build the RouteCollection.
 *
 * @internal
 *
 * @see route_loader_interface
 */
class RouteLoader implements route_loader_interface
{
    /**
     * Scan a specific class for Route attributes and add them to the collection.
     *
     * @param RouteCollection    $collection route collection to populate
     * @param ContainerInterface $container  DI container used to autowire controllers
     * @param null|string        $class_name optional FQCN to scan
     */
    public function loadRoutes(RouteCollection $collection, ContainerInterface $container, ?string $class_name): void
    {
        if (in_array($class_name, [null, '', '0'], true)) {
            return;
        }

        if ($container->has(boot_failure_policy::class)) {
            /** @var boot_failure_policy $policy */
            $policy = $container->get(boot_failure_policy::class);
            if ($policy->shouldSkipClass($class_name)) {
                return;
            }
        }

        if (!class_exists($class_name)) {
            return;
        }

        $reflection = new ReflectionClass($class_name);

        // Auto-register controller in container if not present and container is not yet compiled.
        // After compilation (during extension boot), controllers are already registered by ServiceLoader.
        if (!$container->has($class_name) && $container instanceof ContainerBuilder && !$container->isCompiled()) {
            $container->autowire($class_name, $class_name)
                ->setPublic(true)
                ->setAutoconfigured(true);
        }

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $attributes = $method->getAttributes(RouteAttribute::class);
            foreach ($attributes as $attr) {
                /** @var RouteAttribute $route_attr */
                $route_attr = $attr->newInstance();

                $this->addRoute(
                    $collection,
                    $route_attr,
                    $class_name,
                    $method->getName()
                );
            }
        }
    }

    /**
     * Converts the Attribute into a Symfony Route object and adds to collection.
     *
     * Injects `_plugin_base` for routes from external plugins so the
     * plugin_aware_url_generator produces correct URLs.
     */
    private function addRoute(RouteCollection $collection, RouteAttribute $attr, string $class, string $method): void
    {
        if (!$attr->path || !$attr->name) {
            return;
        }

        $defaults = ['_controller' => [$class, $method]];

        // Detect external plugin routes by namespace and inject _plugin_base.
        $plugin_base = $this->resolvePluginBase($class);
        if ($plugin_base !== null) {
            $defaults['_plugin_base'] = $plugin_base;
        }

        $route = new Route(
            $attr->path,
            $defaults,
            $attr->requirements,
            $attr->options,
            $attr->host,
            $attr->schemes,
            $attr->methods ?: ['GET', 'POST']
        );

        $collection->add($attr->name, $route);
    }

    /**
     * Resolve the plugin base URL from a controller's FQCN.
     *
     * Returns null for local_example classes (uses the default entry point).
     * For external plugin classes, returns:
     *   - `/local/{plugin}/ajax.php`  for api_controller subclasses (JSON endpoints)
     *   - `/local/{plugin}/index.php` for all other controllers (web/Inertia)
     */
    private function resolvePluginBase(string $class): ?string
    {
        // local_example classes use the default entry point — no override needed.
        if (str_starts_with($class, ComponentContext::name() . '\\')) {
            return null;
        }

        // Extract plugin component: local_yourplugin\... → 'local_yourplugin'
        // Use preg_replace to replace only the first underscore, preserving plugin
        // names that contain underscores (e.g. local_my_plugin → local/my_plugin).
        $parts = explode('\\', $class);
        if (count($parts) >= 2 && str_starts_with($parts[0], 'local_')) {
            $plugin_dir = preg_replace('/^local_/', 'local/', $parts[0]);

            $entry_file = is_subclass_of($class, abstract_api_controller::class)
                ? 'ajax.php'
                : 'index.php';

            return '/' . $plugin_dir . '/' . $entry_file;
        }

        return null;
    }
}
