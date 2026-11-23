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
use Middag\Framework\Http\Session\ArraySession;
use Middag\Framework\Http\Session\FlashBag;
use Middag\Moodle\Http\Contract\RouterInterface;
use Middag\Moodle\Http\Inertia\InertiaSharedProps;
use Middag\Moodle\Runtime\ContainerFactory;
use Middag\Moodle\Runtime\Kernel;
use Middag\Ui\Form\FieldMessage;
use Middag\Ui\Form\FormErrors;
use Middag\Ui\Navigation\Contract\NavigationRegistryInterface;
use Middag\Ui\Navigation\NavigationNode;
use Middag\Ui\Navigation\NavigationTree;
use Middag\Ui\Shared\ValueObject\Translatable;
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
 * register() shares eight props into the framework InertiaManager; the four
 * closure-backed builders (navigation, auth, theme, scope, flash, errors) and the
 * lazy csrf_token/locale values are exercised by invoking the shared closures. The
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
    public function registerSharesAllEightPropsWithoutVersion(): void
    {
        $GLOBALS['__middag_test_current_language'] = 'pt_br';

        InertiaSharedProps::register();

        $shared = InertiaManager::getShared();

        self::assertSame(
            ['navigation', 'auth', 'theme', 'scope', 'flash', 'errors', 'csrf_token', 'locale'],
            array_keys($shared),
        );
        self::assertArrayNotHasKey('version', $shared);

        // Closure-backed props stay lazy (raw closures, not yet resolved).
        self::assertInstanceOf(Closure::class, $shared['navigation']);
        self::assertInstanceOf(Closure::class, $shared['auth']);
        self::assertInstanceOf(Closure::class, $shared['theme']);
        self::assertInstanceOf(Closure::class, $shared['scope']);
        self::assertInstanceOf(Closure::class, $shared['flash']);

        // csrf_token stays lazy too: it is the Moodle sesskey, read per request.
        self::assertInstanceOf(Closure::class, $shared['csrf_token']);

        // locale resolves through the lazy closure to the current language.
        self::assertSame('pt_br', ($shared['locale'])());
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
        $registry = $this->registry(static function (string $route) use (&$captured): NavigationTree {
            $captured = $route;

            return new NavigationTree([new NavigationNode(key: 'audience', label: 'Audience')], $route);
        });
        Kernel::instance(NavigationRegistryInterface::class, $registry);

        $_SERVER['REQUEST_URI'] = '/nav-active';

        InertiaSharedProps::register();
        $payload = (InertiaManager::getShared()['navigation'])();

        self::assertSame('audience.segments.index', $captured);
        self::assertSame('audience.segments.index', $payload['activeKey']);
        self::assertSame('audience', $payload['tree'][0]['key']);
        self::assertSame([], $payload['footer']);
    }

    #[Test]
    public function buildNavigationYieldsEmptyActiveRouteWhenContextIsNull(): void
    {
        $this->injectKernel($this->routerWith(new RouteCollection(), null));

        $captured = 'sentinel';
        $registry = $this->registry(static function (string $route) use (&$captured): NavigationTree {
            $captured = $route;

            return new NavigationTree([], $route);
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
        $registry = $this->registry(static function (string $route) use (&$captured): NavigationTree {
            $captured = $route;

            return new NavigationTree([], $route);
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
        $registry = $this->registry(static function (string $route) use (&$captured): NavigationTree {
            $captured = $route;

            return new NavigationTree([], $route);
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

        self::assertSame('42', $auth['id']);
        self::assertSame('Ada Lovelace', $auth['name']);
        self::assertSame('user@moodle.test', $auth['email']);
        self::assertSame('https://moodle.test/u/ada.png', $auth['avatarUrl']);
        self::assertSame(
            ['local/example:manage', 'local/example:moderate', 'local/example:view'],
            $auth['capabilities'],
        );
    }

    #[Test]
    public function authClosureOmitsAvatarAndEmailWhenUnavailable(): void
    {
        $GLOBALS['USER'] = (object) ['id' => 7];
        $GLOBALS['PAGE'] = new stdClass();
        $GLOBALS['__middag_test_has_capability'] = false;
        $GLOBALS['__middag_test_user_picture_throw'] = true;

        InertiaSharedProps::register();
        $auth = (InertiaManager::getShared()['auth'])();

        self::assertSame('7', $auth['id']);
        self::assertArrayNotHasKey('email', $auth);
        self::assertArrayNotHasKey('avatarUrl', $auth);
        self::assertArrayNotHasKey('logoutUrl', $auth);
        self::assertSame([], $auth['capabilities']);
    }

    // --------------------------------------------------------------------- //
    // theme closure + buildTheme()                                          //
    // --------------------------------------------------------------------- //

    #[Test]
    public function themeClosureEmitsOnlyAppearanceAndStrings(): void
    {
        InertiaSharedProps::register();
        $theme = (InertiaManager::getShared()['theme'])();

        // No appearance preference: the key is omitted, strings is an empty object.
        self::assertSame(['strings'], array_keys($theme));
        self::assertEquals(new stdClass(), $theme['strings']);
    }

    #[Test]
    public function themeClosureIgnoresMoodleBrandColorSettings(): void
    {
        $GLOBALS['__middag_test_config'] = ['mdglib_core_inherit_theme_colors' => '1'];
        $GLOBALS['PAGE'] = (object) [
            'theme' => (object) ['settings' => (object) ['brandcolor' => '#0f6cbf']],
        ];

        InertiaSharedProps::register();
        $theme = (InertiaManager::getShared()['theme'])();

        self::assertArrayNotHasKey('brandColor', $theme);
        self::assertArrayNotHasKey('inherit', $theme);
    }

    // --------------------------------------------------------------------- //
    // scope closure + buildScope()                                          //
    // --------------------------------------------------------------------- //

    #[Test]
    public function scopeClosureReturnsAnEmptyObject(): void
    {
        InertiaSharedProps::register();
        $scope = (InertiaManager::getShared()['scope'])();

        self::assertEquals(new stdClass(), $scope);
    }

    // --------------------------------------------------------------------- //
    // flash closure + buildFlash()                                          //
    // --------------------------------------------------------------------- //

    #[Test]
    public function flashClosureReturnsNullWhenFrameworkFlashBagIsEmpty(): void
    {
        $GLOBALS['SESSION'] = new stdClass();

        InertiaSharedProps::register();

        self::assertNull((InertiaManager::getShared()['flash'])());
    }

    #[Test]
    public function flashClosureIgnoresLegacySessionFlashKeys(): void
    {
        $legacy = [
            'middag_flash_success' => 'ok',
            'middag_flash_error' => 'bad',
            'middag_flash_info' => 'fyi',
            'middag_flash_warning' => 'careful',
        ];
        $GLOBALS['SESSION'] = (object) $legacy;

        InertiaSharedProps::register();

        self::assertNull((InertiaManager::getShared()['flash'])());
        foreach ($legacy as $key => $value) {
            self::assertSame($value, $GLOBALS['SESSION']->{$key}, $key . ' must stay untouched');
        }
    }

    #[Test]
    public function flashClosureReturnsOnlyFrameworkFlashBagMessages(): void
    {
        $GLOBALS['SESSION'] = (object) ['middag_flash_success' => 'legacy'];
        $bag = new FlashBag(new ArraySession());
        $bag->add('success', 'Saved.');
        $this->injectContainer($bag);

        InertiaSharedProps::register();

        self::assertSame(['success' => 'Saved.'], (InertiaManager::getShared()['flash'])());
        self::assertSame('legacy', $GLOBALS['SESSION']->middag_flash_success);
    }

    // --------------------------------------------------------------------- //
    // errors closure + buildErrors()                                        //
    // --------------------------------------------------------------------- //

    #[Test]
    public function errorsClosureReturnsAnEmptyObjectWhenThereAreNoErrors(): void
    {
        InertiaSharedProps::register();

        self::assertEquals(new stdClass(), (InertiaManager::getShared()['errors'])());
    }

    #[Test]
    public function errorsClosureReturnsTheFieldMapFromTheFlashBag(): void
    {
        $bag = new FlashBag(new ArraySession());
        $bag->setErrors(new FormErrors(['email' => 'Invalid.']));
        $this->injectContainer($bag);

        InertiaSharedProps::register();

        self::assertSame('{"email":"Invalid."}', json_encode((InertiaManager::getShared()['errors'])()));
    }

    #[Test]
    public function errorsClosureCarriesStructuredFieldMessagesFromTheSession(): void
    {
        $bag = new FlashBag(new ArraySession());
        $bag->setErrors(new FormErrors([
            'email' => new FieldMessage('Not a valid email.', 'validation.email', 'validators'),
            'tags' => ['Too many tags.', new FieldMessage('Tags must be unique.', 'validation.unique', 'validators', ['limit' => 3])],
        ]));
        $this->injectContainer($bag);

        InertiaSharedProps::register();

        self::assertSame(
            '{"email":{"message":"Not a valid email.","key":"validation.email","domain":"validators"},'
            . '"tags":["Too many tags.",{"message":"Tags must be unique.","key":"validation.unique","domain":"validators","params":{"limit":3}}]}',
            json_encode((InertiaManager::getShared()['errors'])()),
        );
    }

    #[Test]
    public function errorsClosureNestsTheFieldMapUnderTheErrorBag(): void
    {
        $bag = new FlashBag(new ArraySession());
        $bag->setErrors(new FormErrors(['email' => 'Invalid.']), 'login');
        $this->injectContainer($bag);

        InertiaSharedProps::register();

        self::assertSame('{"login":{"email":"Invalid."}}', json_encode((InertiaManager::getShared()['errors'])()));
    }

    #[Test]
    public function errorsAreReadOnlyOnce(): void
    {
        $bag = new FlashBag(new ArraySession());
        $bag->setErrors(new FormErrors(['email' => 'Invalid.']));
        $this->injectContainer($bag);

        InertiaSharedProps::register();
        (InertiaManager::getShared()['errors'])();

        self::assertEquals(new stdClass(), (InertiaManager::getShared()['errors'])());
    }

    // --------------------------------------------------------------------- //
    // Helpers                                                               //
    // --------------------------------------------------------------------- //

    /**
     * Inject a pre-booted Kernel whose container resolves only the given FlashBag.
     */
    private function injectContainer(FlashBag $bag): void
    {
        $container = new readonly class($bag) implements ContainerInterface {
            public function __construct(private FlashBag $bag) {}

            public function has(string $id): bool
            {
                return FlashBag::class === $id;
            }

            public function get(string $id): FlashBag
            {
                return $this->bag;
            }
        };

        $reflection = new ReflectionClass(Kernel::class);
        $kernel = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('booted')->setValue($kernel, true);
        $kernel->setInternalContainer($container);
        $reflection->getProperty('instance')->setValue(null, $kernel);
    }

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
        return new readonly class($routes, $context) implements RouterInterface {
            public function __construct(
                private RouteCollection $routes,
                private ?RequestContext $context,
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
        return new readonly class($build) implements NavigationRegistryInterface {
            public function __construct(private Closure $build) {}

            public function group(
                string $key,
                string|Translatable $label,
                ?string $icon = null,
                int $weight = 50,
                ?string $entityType = null,
                ?string $badgeVariant = null,
                ?string $statusColor = null,
            ): static {
                return $this;
            }

            public function section(
                string $key,
                string|Translatable $label,
                ?string $icon = null,
                int $weight = 50,
                ?string $entityType = null,
                ?string $badgeVariant = null,
                ?string $statusColor = null,
            ): static {
                return $this;
            }

            public function item(
                string $key,
                string|Translatable $label,
                ?string $route,
                array $routeParams = [],
                ?string $icon = null,
                int $weight = 50,
                ?string $entityType = null,
                ?string $badgeVariant = null,
                ?string $statusColor = null,
                ?string $target = null,
                ?string $href = null,
            ): static {
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

            public function build(string $activeRoute): NavigationTree
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
