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

use Middag\Framework\Http\Contract\RouteLoaderInterface;
use Middag\Moodle\Http\Routing\MoodleRouter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * MoodleRouter stores the RouteCollection, resolves the routing context from
 * globals, and generates plugin-aware URL strings (delegating discovery to a
 * RouteLoaderInterface). It carries no static/singleton state, so the only
 * shared surface a test perturbs is $_SERVER — read by initializeContext()
 * via Request::createFromGlobals(). setUp() pins a deterministic server env
 * and tearDown() restores the prior one so the suite never leaks.
 *
 * @internal
 */
#[CoversClass(MoodleRouter::class)]
final class MoodleRouterCoverageTest extends TestCase
{
    private const ENTRY_POINT = '/local/example/index.php';

    /** @var array<string, mixed> */
    private array $prevServer = [];

    protected function setUp(): void
    {
        $this->prevServer = $_SERVER;

        // A valid host/URI so Request::createFromGlobals() + RequestContext
        // yield a deterministic base for URL generation.
        $_SERVER['HTTP_HOST'] = 'moodle.test';
        $_SERVER['SERVER_NAME'] = 'moodle.test';
        $_SERVER['SERVER_PORT'] = '80';
        $_SERVER['REQUEST_URI'] = self::ENTRY_POINT;
        $_SERVER['SCRIPT_NAME'] = self::ENTRY_POINT;
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTPS'] = 'off';
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->prevServer;
    }

    #[Test]
    public function testConstructorStartsWithAnEmptyRouteCollection(): void
    {
        $router = new MoodleRouter();

        $routes = $router->getRoutes();

        self::assertInstanceOf(RouteCollection::class, $routes);
        self::assertCount(0, $routes);
    }

    #[Test]
    public function testGetContextReturnsNullBeforeInitialization(): void
    {
        $router = new MoodleRouter();

        self::assertNull($router->getContext());
    }

    #[Test]
    public function testInitializeContextBuildsAContextBoundToThePluginEntryPoint(): void
    {
        $router = new MoodleRouter();

        $router->initializeContext();

        $context = $router->getContext();
        self::assertInstanceOf(RequestContext::class, $context);
        self::assertSame(self::ENTRY_POINT, $context->getBaseUrl());
        self::assertSame('moodle.test', $context->getHost());
    }

    #[Test]
    public function testRegisterDefaultRoutesAddsTheNotFoundRouteWithA404Controller(): void
    {
        $router = new MoodleRouter();

        $router->registerDefaultRoutes();

        $route = $router->getRoutes()->get('route_not_found');
        self::assertInstanceOf(Route::class, $route);
        self::assertSame('/404', $route->getPath());

        // The controller default is a closure that emits the 404 response;
        // invoke it to prove the wired behaviour, not just its presence.
        $controller = $route->getDefault('_controller');
        self::assertIsCallable($controller);
        $response = $controller();
        self::assertInstanceOf(Response::class, $response);
        self::assertSame(404, $response->getStatusCode());
        self::assertSame('Page not found', $response->getContent());
    }

    #[Test]
    public function testRegisterDefaultRoutesAppliesGlobalRegexRequirements(): void
    {
        $router = new MoodleRouter();

        $router->registerDefaultRoutes();

        // addRequirements() is applied across the collection's existing routes.
        $route = $router->getRoutes()->get('route_not_found');
        self::assertSame('[0-9]+', $route->getRequirement('id'));
        self::assertSame('[0-9]+', $route->getRequirement('courseid'));
        self::assertSame('.*', $route->getRequirement('any'));
        self::assertSame('[0-9a-fA-F\-]{36}', $route->getRequirement('uuid'));
    }

    #[Test]
    public function testRegisterAddsANamedRouteWithControllerCallableAndRequirements(): void
    {
        $router = new MoodleRouter();

        $router->register('user_show', '/users/{id}', 'Acme\UserController', 'show', ['id' => '[0-9]+']);

        $route = $router->getRoutes()->get('user_show');
        self::assertInstanceOf(Route::class, $route);
        self::assertSame('/users/{id}', $route->getPath());
        self::assertSame(['Acme\UserController', 'show'], $route->getDefault('_controller'));
        self::assertSame('[0-9]+', $route->getRequirement('id'));
    }

    #[Test]
    public function testScanAnnotationsDelegatesToTheInjectedLoader(): void
    {
        $loader = $this->recordingLoader();
        $container = $this->emptyContainer();
        $router = new MoodleRouter($loader);

        $router->scanAnnotations($container, 'Acme\SomeController');

        // The router forwards its own collection, the container, and the FQCN
        // verbatim to the loader — no filtering of its own.
        self::assertSame(1, $loader->calls);
        self::assertSame($router->getRoutes(), $loader->collection);
        self::assertSame($container, $loader->container);
        self::assertSame('Acme\SomeController', $loader->className);
    }

    #[Test]
    public function testScanAnnotationsForwardsNullClassNameToTheLoader(): void
    {
        $loader = $this->recordingLoader();
        $router = new MoodleRouter($loader);

        $router->scanAnnotations($this->emptyContainer());

        self::assertSame(1, $loader->calls);
        self::assertNull($loader->className);
    }

    #[Test]
    public function testGenerateUrlLazilyInitializesContextForARegisteredRoute(): void
    {
        // Fresh router: context is null, so generateUrl() must initialize it
        // (and build the generator) before producing the path.
        $router = new MoodleRouter();
        $router->register('foo', '/foo/{id}', 'Acme\Ctrl', 'act', ['id' => '[0-9]+']);

        $url = $router->generateUrl('foo', ['id' => 7]);

        self::assertSame(self::ENTRY_POINT . '/foo/7', $url);
        // A context was materialized as a side effect of generation.
        self::assertInstanceOf(RequestContext::class, $router->getContext());
    }

    #[Test]
    public function testGenerateUrlReusesAnAlreadyInitializedContext(): void
    {
        // Pre-initialize the context: generateUrl() then builds only the
        // generator and skips the initializeContext() branch.
        $router = new MoodleRouter();
        $router->initializeContext();

        $context = $router->getContext();
        $router->register('foo', '/foo/{id}', 'Acme\Ctrl', 'act', ['id' => '[0-9]+']);

        $url = $router->generateUrl('foo', ['id' => 9]);

        self::assertSame(self::ENTRY_POINT . '/foo/9', $url);
        // Same context instance — it was not re-created.
        self::assertSame($context, $router->getContext());
    }

    #[Test]
    public function testGenerateUrlHonoursTheAbsoluteUrlReferenceType(): void
    {
        $router = new MoodleRouter();
        $router->register('foo', '/foo/{id}', 'Acme\Ctrl', 'act', ['id' => '[0-9]+']);

        $url = $router->generateUrl('foo', ['id' => 3], UrlGeneratorInterface::ABSOLUTE_URL);

        // The absolute form carries the request host and the full path.
        self::assertStringContainsString('moodle.test', $url);
        self::assertStringContainsString(self::ENTRY_POINT . '/foo/3', $url);
    }

    #[Test]
    public function testGenerateUrlFallsBackToTheNotFoundRouteForAnUnknownName(): void
    {
        // With default routes registered, an unknown name triggers the
        // RouteNotFoundException catch branch, which regenerates the
        // route_not_found path instead of crashing the UI.
        $router = new MoodleRouter();
        $router->registerDefaultRoutes();

        $url = $router->generateUrl('does_not_exist');

        self::assertSame(self::ENTRY_POINT . '/404', $url);
    }

    #[Test]
    public function testGenerateUrlReusesTheGeneratorAcrossCalls(): void
    {
        // The second call must take the "generator already built" branch and
        // still return the identical, deterministic result.
        $router = new MoodleRouter();
        $router->register('foo', '/foo/{id}', 'Acme\Ctrl', 'act', ['id' => '[0-9]+']);

        $first = $router->generateUrl('foo', ['id' => 1]);
        $second = $router->generateUrl('foo', ['id' => 1]);

        self::assertSame(self::ENTRY_POINT . '/foo/1', $first);
        self::assertSame($first, $second);
    }

    /**
     * A RouteLoaderInterface double that records exactly what the router hands
     * it, without mutating the collection.
     */
    private function recordingLoader(): object
    {
        return new class implements RouteLoaderInterface {
            public int $calls = 0;

            public ?RouteCollection $collection = null;

            public ?ContainerInterface $container = null;

            public ?string $className = null;

            public function loadRoutes(RouteCollection $collection, ContainerInterface $container, ?string $className): void
            {
                ++$this->calls;
                $this->collection = $collection;
                $this->container = $container;
                $this->className = $className;
            }
        };
    }

    /**
     * A minimal PSR-11 container — the router never queries it directly (only
     * the loader would), so an always-empty stand-in suffices.
     */
    private function emptyContainer(): ContainerInterface
    {
        return new class implements ContainerInterface {
            public function get(string $id): mixed
            {
                throw new class('not found') extends RuntimeException implements NotFoundExceptionInterface {};
            }

            public function has(string $id): bool
            {
                return false;
            }
        };
    }
}
