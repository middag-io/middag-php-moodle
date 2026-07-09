<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Http\Inertia;

use Closure;
use Middag\Framework\Http\Inertia\InertiaManager;
use Middag\Moodle\Config\ComponentContext;
use Middag\Moodle\Http\Contract\RouterInterface;
use Middag\Moodle\Http\Inertia\InertiaSharedProps;
use Middag\Moodle\Runtime\ContainerFactory;
use Middag\Moodle\Runtime\Kernel;
use Middag\Ui\Navigation\Contract\NavigationRegistryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use stdClass;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Coverage for the Inertia shared-props registrar.
 *
 * register() shares seven props into the framework InertiaManager; the four
 * closure-backed builders (navigation, auth, theme, flash) and the two static
 * values (locale, version) are exercised by invoking the shared closures. The
 * navigation builder resolves the active route through a Kernel injected via
 * reflection (mirroring the kernel test recipe), so every resolveCurrentRoute()
 * branch — matched route, null context, ResourceNotFound, generic Throwable —
 * is reachable without a real boot.
 *
 * @internal
 */
#[CoversClass(InertiaSharedProps::class)]
final class InertiaSharedPropsCoverageTest extends TestCase
{
    private mixed $prevUser = null;

    private mixed $prevPage = null;

    private mixed $prevSession = null;

    /** @var array<string, mixed> */
    private array $prevServer = [];

    protected function setUp(): void
    {
        $this->prevUser = $GLOBALS['USER'] ?? null;
        $this->prevPage = $GLOBALS['PAGE'] ?? null;
        $this->prevSession = $GLOBALS['SESSION'] ?? null;
        $this->prevServer = $_SERVER;

        $this->resetKernelWorld();

        $_SERVER['HTTP_HOST'] = 'moodle.test';
        $_SERVER['SERVER_NAME'] = 'moodle.test';
        $_SERVER['SERVER_PORT'] = '80';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        unset($_SERVER['SCRIPT_NAME'], $_SERVER['PHP_SELF'], $_SERVER['ORIG_SCRIPT_NAME']);
    }

    protected function tearDown(): void
    {
        $this->resetKernelWorld();

        $GLOBALS['USER'] = $this->prevUser;
        $GLOBALS['PAGE'] = $this->prevPage;
        $GLOBALS['SESSION'] = $this->prevSession;
        $_SERVER = $this->prevServer;

        unset(
            $GLOBALS['__middag_test_config'],
            $GLOBALS['__middag_test_has_capability'],
            $GLOBALS['__middag_test_user_picture_throw'],
            $GLOBALS['__middag_test_avatar_url'],
            $GLOBALS['__middag_test_fullname'],
            $GLOBALS['__middag_test_current_language'],
        );
    }

    // --------------------------------------------------------------------- //
    // register()                                                            //
    // --------------------------------------------------------------------- //

    #[Test]
    public function registerSharesAllSevenPropsWithStaticLocaleAndVersion(): void
    {
        $GLOBALS['__middag_test_current_language'] = 'pt_br';

        InertiaSharedProps::register();

        $shared = InertiaManager::getShared();

        self::assertSame(
            ['navigation', 'auth', 'theme', 'scope', 'flash', 'locale', 'version'],
            array_keys($shared),
        );

        // Closure-backed props stay lazy (raw closures, not yet resolved).
        self::assertInstanceOf(Closure::class, $shared['navigation']);
        self::assertInstanceOf(Closure::class, $shared['auth']);
        self::assertInstanceOf(Closure::class, $shared['theme']);
        self::assertInstanceOf(Closure::class, $shared['scope']);
        self::assertInstanceOf(Closure::class, $shared['flash']);

        // locale resolves through the lazy closure to the current language.
        self::assertSame('pt_br', ($shared['locale'])());

        // version is the running component name (configured in tests/bootstrap.php).
        self::assertSame(ComponentContext::name(), $shared['version']);
    }

    // --------------------------------------------------------------------- //
    // navigation closure + buildNavigation() + resolveCurrentRoute()        //
    // --------------------------------------------------------------------- //

    #[Test]
    public function navigationClosureBuildsTreeWithTheMatchedActiveRoute(): void
    {
        $routes = new RouteCollection();
        $routes->add('audience.segments.index', new Route('/nav-active'));
        $this->injectKernel($this->routerWith($routes, new RequestContext()));

        $captured = null;
        $registry = $this->registry(static function (string $route) use (&$captured): array {
            $captured = $route;

            return ['tree' => [['key' => 'audience']], 'activeKey' => $route, 'footer' => []];
        });
        Kernel::instance(NavigationRegistryInterface::class, $registry);

        $_SERVER['REQUEST_URI'] = '/nav-active';

        InertiaSharedProps::register();
        $payload = (InertiaManager::getShared()['navigation'])();

        self::assertSame('audience.segments.index', $captured);
        self::assertSame('audience.segments.index', $payload['activeKey']);
        self::assertSame([['key' => 'audience']], $payload['tree']);
    }

    #[Test]
    public function buildNavigationYieldsEmptyActiveRouteWhenContextIsNull(): void
    {
        $this->injectKernel($this->routerWith(new RouteCollection(), null));

        $captured = 'sentinel';
        $registry = $this->registry(static function (string $route) use (&$captured): array {
            $captured = $route;

            return ['tree' => [], 'activeKey' => $route, 'footer' => []];
        });
        Kernel::instance(NavigationRegistryInterface::class, $registry);

        InertiaSharedProps::buildNavigation();

        // Null RequestContext short-circuits resolveCurrentRoute() to ''.
        self::assertSame('', $captured);
    }

    #[Test]
    public function buildNavigationYieldsEmptyActiveRouteWhenNoRouteMatches(): void
    {
        // Empty collection: UrlMatcher throws ResourceNotFoundException.
        $this->injectKernel($this->routerWith(new RouteCollection(), new RequestContext()));

        $captured = 'sentinel';
        $registry = $this->registry(static function (string $route) use (&$captured): array {
            $captured = $route;

            return ['tree' => [], 'activeKey' => $route, 'footer' => []];
        });
        Kernel::instance(NavigationRegistryInterface::class, $registry);

        $_SERVER['REQUEST_URI'] = '/does-not-match-any-route';

        InertiaSharedProps::buildNavigation();

        self::assertSame('', $captured);
    }

    #[Test]
    public function buildNavigationYieldsEmptyActiveRouteOnGenericThrowable(): void
    {
        // Router null on a booted kernel: Kernel::routing() throws RuntimeException,
        // caught by the generic Throwable arm of resolveCurrentRoute().
        $this->injectKernel(null);

        $captured = 'sentinel';
        $registry = $this->registry(static function (string $route) use (&$captured): array {
            $captured = $route;

            return ['tree' => [], 'activeKey' => $route, 'footer' => []];
        });
        Kernel::instance(NavigationRegistryInterface::class, $registry);

        InertiaSharedProps::buildNavigation();

        self::assertSame('', $captured);
    }

    // --------------------------------------------------------------------- //
    // auth closure + buildAuth()                                            //
    // --------------------------------------------------------------------- //

    #[Test]
    public function authClosureBuildsUserWithCapabilitiesAndAvatar(): void
    {
        $GLOBALS['USER'] = (object) ['id' => '42', 'email' => 'user@moodle.test'];
        $GLOBALS['PAGE'] = new stdClass();
        $GLOBALS['__middag_test_has_capability'] = true;
        $GLOBALS['__middag_test_fullname'] = 'Ada Lovelace';
        $GLOBALS['__middag_test_avatar_url'] = 'https://moodle.test/u/ada.png';

        InertiaSharedProps::register();
        $auth = (InertiaManager::getShared()['auth'])();

        self::assertSame(42, $auth['id']);
        self::assertSame('Ada Lovelace', $auth['name']);
        self::assertSame('user@moodle.test', $auth['email']);
        self::assertSame('https://moodle.test/u/ada.png', $auth['avatarUrl']);
        self::assertSame(
            ['local/example:manage', 'local/example:moderate', 'local/example:view'],
            $auth['capabilities'],
        );
    }

    #[Test]
    public function authClosureLeavesAvatarNullAndEmailBlankWhenUnavailable(): void
    {
        $GLOBALS['USER'] = (object) ['id' => 7];
        $GLOBALS['PAGE'] = new stdClass();
        $GLOBALS['__middag_test_has_capability'] = false;
        $GLOBALS['__middag_test_user_picture_throw'] = true;

        InertiaSharedProps::register();
        $auth = (InertiaManager::getShared()['auth'])();

        self::assertSame(7, $auth['id']);
        self::assertSame('', $auth['email']);
        self::assertNull($auth['avatarUrl']);
        self::assertSame([], $auth['capabilities']);
    }

    // --------------------------------------------------------------------- //
    // theme closure + buildTheme()                                          //
    // --------------------------------------------------------------------- //

    #[Test]
    public function themeClosureReturnsDisabledDefaultsWhenInheritanceOff(): void
    {
        // No config set → inheritance disabled → brandColor null.
        InertiaSharedProps::register();
        $theme = (InertiaManager::getShared()['theme'])();

        self::assertSame([], $theme['strings']);
        self::assertNull($theme['appearance']);
        self::assertNull($theme['brandColor']);
        self::assertFalse($theme['inherit']);
    }

    #[Test]
    public function themeClosureReturnsBrandColorWhenInheritanceEnabled(): void
    {
        $GLOBALS['__middag_test_config'] = ['mdg_core_inherit_theme_colors' => '1'];
        $GLOBALS['PAGE'] = (object) [
            'theme' => (object) ['settings' => (object) ['brandcolor' => '#0f6cbf']],
        ];

        InertiaSharedProps::register();
        $theme = (InertiaManager::getShared()['theme'])();

        self::assertSame('#0f6cbf', $theme['brandColor']);
        self::assertTrue($theme['inherit']);
    }

    // --------------------------------------------------------------------- //
    // scope closure + buildScope()                                          //
    // --------------------------------------------------------------------- //

    #[Test]
    public function scopeClosureReturnsBaseIdentifiers(): void
    {
        InertiaSharedProps::register();
        $scope = (InertiaManager::getShared()['scope'])();

        self::assertSame(['identifiers' => []], $scope);
    }

    // --------------------------------------------------------------------- //
    // flash closure + buildFlash()                                          //
    // --------------------------------------------------------------------- //

    #[Test]
    public function flashClosureReturnsNullWhenSessionHasNoFlash(): void
    {
        $GLOBALS['SESSION'] = new stdClass();

        InertiaSharedProps::register();

        self::assertNull((InertiaManager::getShared()['flash'])());
    }

    #[Test]
    public function flashClosureCollectsEveryLevelAndClearsTheSession(): void
    {
        $session = (object) [
            'middag_flash_success' => 'saved',
            'middag_flash_error' => 'boom',
            'middag_flash_info' => 'fyi',
            'middag_flash_warning' => 'careful',
        ];
        $GLOBALS['SESSION'] = $session;

        InertiaSharedProps::register();
        $flash = (InertiaManager::getShared()['flash'])();

        self::assertSame(
            ['success' => 'saved', 'error' => 'boom', 'info' => 'fyi', 'warning' => 'careful'],
            $flash,
        );

        // One-shot: each key is consumed off the session.
        self::assertFalse(isset($session->middag_flash_success));
        self::assertFalse(isset($session->middag_flash_error));
        self::assertFalse(isset($session->middag_flash_info));
        self::assertFalse(isset($session->middag_flash_warning));
    }

    // --------------------------------------------------------------------- //
    // Helpers                                                               //
    // --------------------------------------------------------------------- //

    /**
     * Inject a pre-booted Kernel singleton with an optional router, bypassing a
     * real boot (mirrors the kernel test recipe).
     */
    private function injectKernel(?RouterInterface $router): void
    {
        $reflection = new ReflectionClass(Kernel::class);
        $kernel = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('booted')->setValue($kernel, true);

        if ($router instanceof RouterInterface) {
            $reflection->getProperty('router')->setValue($kernel, $router);
        }

        $reflection->getProperty('instance')->setValue(null, $kernel);
    }

    /**
     * A RouterInterface stand-in exposing the given routes and (nullable) context.
     */
    private function routerWith(RouteCollection $routes, ?RequestContext $context): RouterInterface
    {
        return new class($routes, $context) implements RouterInterface {
            public function __construct(
                private readonly RouteCollection $routes,
                private readonly ?RequestContext $context,
            ) {}

            public function initializeContext(): void {}

            public function getRoutes(): RouteCollection
            {
                return $this->routes;
            }

            public function getContext(): ?RequestContext
            {
                return $this->context;
            }

            public function registerDefaultRoutes(): void {}

            public function scanAnnotations(ContainerInterface $container, ?string $specific_class = null): void {}

            public function generateUrl(string $route, array $parameters = [], int $reference_type = 1): string
            {
                return '';
            }
        };
    }

    /**
     * A NavigationRegistryInterface whose build() delegates to the given callback;
     * every registration method returns $this (fluent, unused here).
     */
    private function registry(Closure $build): NavigationRegistryInterface
    {
        return new class($build) implements NavigationRegistryInterface {
            public function __construct(private readonly Closure $build) {}

            public function group(string $key, string $label, ?string $icon = null, int $weight = 50): static
            {
                return $this;
            }

            public function section(string $key, string $label, ?string $icon = null, int $weight = 50): static
            {
                return $this;
            }

            public function item(string $key, string $label, string $route, array $routeParams = [], ?string $icon = null, int $weight = 50): static
            {
                return $this;
            }

            public function capability(string $nodeKey, string $capability): static
            {
                return $this;
            }

            public function collapsible(string $groupKey, bool $defaultOpen = false): static
            {
                return $this;
            }

            public function drilldown(string $nodeKey): static
            {
                return $this;
            }

            public function badge(string $nodeKey, Closure $resolver): static
            {
                return $this;
            }

            public function build(string $activeRoute): array
            {
                return ($this->build)($activeRoute);
            }
        };
    }

    /**
     * Reset every static seam this test touches so no state leaks between tests
     * or into sibling suites (the suite runs failOnRisky/failOnWarning).
     */
    private function resetKernelWorld(): void
    {
        Kernel::shutdown();

        $factory = new ReflectionClass(ContainerFactory::class);
        $factory->setStaticPropertyValue('container', null);
        $factory->setStaticPropertyValue('builder', null);
        $factory->setStaticPropertyValue('resetCallbacks', []);

        InertiaManager::flush();
    }
}
