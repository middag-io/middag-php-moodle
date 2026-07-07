<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Http\Routing;

use Middag\Framework\Kernel\Contract\LoaderFailurePolicyInterface;
use Middag\Moodle\Http\Routing\RouteLoader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Throwable;

/**
 * RouteLoader is a stateless reflector: it inspects a controller class for
 * Symfony #[Route] attributes and populates a RouteCollection, injecting a
 * _plugin_base default for controllers that belong to an external plugin. Each
 * branch is exercised with the fixture controllers declared in
 * tests/stubs/areas/http-routeloader.php against real Symfony containers and
 * collections, asserting on the resulting Route objects.
 *
 * @internal
 */
#[CoversClass(RouteLoader::class)]
final class RouteLoaderCoverageTest extends TestCase
{
    private const HOME = 'local_example\RouteLoaderHomeController';

    private const WEB = 'local_yourplugin\RouteLoaderWebController';

    private const API = 'local_yourplugin\RouteLoaderApiController';

    private const EXTERNAL = 'App\Controller\RouteLoaderExternalController';

    #[Test]
    public function testNullEmptyAndZeroClassNamesAreIgnored(): void
    {
        $loader = new RouteLoader();
        $collection = new RouteCollection();
        $container = new ContainerBuilder();

        foreach ([null, '', '0'] as $className) {
            $loader->loadRoutes($collection, $container, $className);
        }

        self::assertCount(0, $collection->all());
    }

    #[Test]
    public function testFailurePolicySkipsFlaggedClass(): void
    {
        $loader = new RouteLoader();
        $collection = new RouteCollection();
        $container = new ContainerBuilder();
        $container->set(LoaderFailurePolicyInterface::class, $this->makePolicy(true));

        $loader->loadRoutes($collection, $container, self::HOME);

        // The policy short-circuits before any reflection: no route is registered.
        self::assertCount(0, $collection->all());
        self::assertFalse($container->hasDefinition(self::HOME));
    }

    #[Test]
    public function testNonexistentClassIsIgnoredWhenPolicyAllows(): void
    {
        $loader = new RouteLoader();
        $collection = new RouteCollection();
        $container = new ContainerBuilder();
        // Policy present but permissive: exercises the shouldSkipClass() === false
        // path, then the class_exists() guard rejects an unknown class.
        $container->set(LoaderFailurePolicyInterface::class, $this->makePolicy(false));

        $loader->loadRoutes($collection, $container, 'Middag\Moodle\Tests\DoesNotExist');

        self::assertCount(0, $collection->all());
    }

    #[Test]
    public function testComponentControllerRoutesHaveNoPluginBaseAndNamelessRouteIsSkipped(): void
    {
        $loader = new RouteLoader();
        $collection = new RouteCollection();
        $container = new ContainerBuilder();

        $loader->loadRoutes($collection, $container, self::HOME);

        // Only the named route is registered; the nameless #[Route] is dropped by
        // addRoute()'s guard, and the non-route public method contributes nothing.
        self::assertSame(['rl_home'], array_keys($collection->all()));

        $route = $collection->get('rl_home');
        self::assertInstanceOf(Route::class, $route);
        self::assertSame('/home', $route->getPath());
        self::assertSame([self::HOME, 'index'], $route->getDefault('_controller'));
        self::assertSame(['GET'], $route->getMethods());
        // local_example is the configured component: no plugin-base override.
        self::assertFalse($route->hasDefault('_plugin_base'));

        // Uncompiled ContainerBuilder without the class registered → autowired.
        self::assertTrue($container->hasDefinition(self::HOME));
        $definition = $container->getDefinition(self::HOME);
        self::assertTrue($definition->isPublic());
        self::assertTrue($definition->isAutowired());
        self::assertTrue($definition->isAutoconfigured());
    }

    #[Test]
    public function testExternalWebControllerGetsIndexPluginBaseAndDefaultMethods(): void
    {
        $loader = new RouteLoader();
        $collection = new RouteCollection();
        $container = new ContainerBuilder();

        $loader->loadRoutes($collection, $container, self::WEB);

        $route = $collection->get('lyp_dashboard');
        self::assertInstanceOf(Route::class, $route);
        self::assertSame('/local/yourplugin/index.php', $route->getDefault('_plugin_base'));
        // No methods on the attribute → the ['GET', 'POST'] fallback is applied.
        self::assertSame(['GET', 'POST'], $route->getMethods());
    }

    #[Test]
    public function testExternalApiControllerGetsAjaxPluginBase(): void
    {
        $loader = new RouteLoader();
        $collection = new RouteCollection();
        $container = new ContainerBuilder();

        $loader->loadRoutes($collection, $container, self::API);

        $route = $collection->get('lyp_ping');
        self::assertInstanceOf(Route::class, $route);
        // AbstractApiController subclass → JSON endpoint entry point.
        self::assertSame('/local/yourplugin/ajax.php', $route->getDefault('_plugin_base'));
        self::assertSame(['GET', 'POST'], $route->getMethods());
    }

    #[Test]
    public function testNonPluginNamespaceControllerHasNoPluginBaseAndSkipsAutowire(): void
    {
        $loader = new RouteLoader();
        $collection = new RouteCollection();
        // A plain PSR-11 container that is not a ContainerBuilder: the policy lookup
        // returns false and the autowire branch is skipped, yet the route still loads.
        $container = $this->makePsrContainer();

        $loader->loadRoutes($collection, $container, self::EXTERNAL);

        $route = $collection->get('app_external');
        self::assertInstanceOf(Route::class, $route);
        // "App\Controller" is neither the component nor a "local_" plugin → no base.
        self::assertFalse($route->hasDefault('_plugin_base'));
        self::assertSame([self::EXTERNAL, 'ext'], $route->getDefault('_controller'));
    }

    private function makePolicy(bool $skip): LoaderFailurePolicyInterface
    {
        return new class($skip) implements LoaderFailurePolicyInterface {
            public function __construct(private readonly bool $skip) {}

            public function shouldSkipClass(string $class): bool
            {
                return $this->skip;
            }

            public function isolateOrThrow(string $artifact, string $class, Throwable $throwable): bool
            {
                return false;
            }
        };
    }

    private function makePsrContainer(): ContainerInterface
    {
        return new class implements ContainerInterface {
            public function get(string $id): mixed
            {
                return null;
            }

            public function has(string $id): bool
            {
                return false;
            }
        };
    }
}
