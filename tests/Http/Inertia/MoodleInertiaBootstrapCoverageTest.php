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
use Middag\Framework\Http\Inertia\InertiaAdapter;
use Middag\Framework\Http\Inertia\InertiaFactory;
use Middag\Framework\Http\Inertia\InertiaVersionManager;
use Middag\Moodle\Http\Contract\RouterInterface;
use Middag\Moodle\Http\Inertia\MoodleInertiaBootstrap;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use ReflectionProperty;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;

/**
 * MoodleInertiaBootstrap wires the platform-agnostic framework Inertia runtime
 * into the Moodle host: it registers the compiled-bundle path (version hash),
 * the URL-generator closure (delegating to the adapter router), and the
 * first-visit HTML bootstrap closure (rendering the SPA mount shell against
 * Moodle's $PAGE->requires pipeline).
 *
 * The class is straight-line static code with no conditional branches; coverage
 * is achieved by exercising registerHooks() and then invoking the two closures
 * it registers (via InertiaAdapter::redirect() for the URL generator and
 * InertiaFactory::getHtmlBootstrap() for the HTML shell), plus a direct
 * htmlBootstrap() call. $CFG/$PAGE are supplied as global test fixtures and
 * core\component::get_component_directory() is driven from $GLOBALS (bootstrap
 * stub). Framework static seams are reset around every test so nothing leaks.
 *
 * @internal
 */
#[CoversClass(MoodleInertiaBootstrap::class)]
final class MoodleInertiaBootstrapCoverageTest extends TestCase
{
    private mixed $prevCfg;

    private mixed $prevPage;

    private mixed $prevComponentDir;

    private ?string $tmpRoot = null;

    protected function setUp(): void
    {
        $this->prevCfg = $GLOBALS['CFG'] ?? null;
        $this->prevPage = $GLOBALS['PAGE'] ?? null;
        $this->prevComponentDir = $GLOBALS['__middag_test_component_dir'] ?? null;

        // Default fixtures: dirroot + a local_example component directory under it,
        // so componentWebBase() resolves to "/local/example" for every test unless
        // a test overrides them (e.g. the bundle-hash test points them at a temp
        // tree holding a real bundle file).
        $GLOBALS['CFG'] = (object) ['dirroot' => '/var/www/html/moodle', 'wwwroot' => 'https://moodle.test'];
        $GLOBALS['__middag_test_component_dir'] = '/var/www/html/moodle/local/example';
        $GLOBALS['PAGE'] = $this->makePage();

        $this->resetInertiaStatics();
    }

    protected function tearDown(): void
    {
        $this->resetInertiaStatics();

        $GLOBALS['CFG'] = $this->prevCfg;
        $GLOBALS['PAGE'] = $this->prevPage;
        $GLOBALS['__middag_test_component_dir'] = $this->prevComponentDir;

        if ($this->tmpRoot !== null) {
            $this->deleteTree($this->tmpRoot);
            $this->tmpRoot = null;
        }
    }

    #[Test]
    public function testRegisterHooksConfiguresBundlePathFromComponentDirectory(): void
    {
        // Point dirroot + component directory at a real temp tree holding a bundle
        // file, so the framework version manager derives its hash from that file —
        // proving registerHooks() fed setBundlePath() the exact computed path
        // ($CFG->dirroot . componentWebBase() . /amd/build/inertia_app.min.js).
        $this->tmpRoot = sys_get_temp_dir() . '/middag_inertia_' . uniqid('', true);
        $bundleDir = $this->tmpRoot . '/local/example/amd/build';
        mkdir($bundleDir, 0o777, true);
        $bundleFile = $bundleDir . '/inertia_app.min.js';
        file_put_contents($bundleFile, 'BUNDLE-CONTENTS');

        $GLOBALS['CFG']->dirroot = $this->tmpRoot;
        $GLOBALS['__middag_test_component_dir'] = $this->tmpRoot . '/local/example';

        MoodleInertiaBootstrap::registerHooks($this->makeRouter());

        $configuredPath = (new ReflectionProperty(InertiaVersionManager::class, 'bundlePath'))->getValue();
        self::assertSame($bundleFile, $configuredPath);
        self::assertSame(md5_file($bundleFile), InertiaVersionManager::getVersion());
    }

    #[Test]
    public function testRegisterHooksWiresUrlGeneratorToTheAdapterRouter(): void
    {
        $router = $this->makeRouter('/generated/');

        MoodleInertiaBootstrap::registerHooks($router);

        // The registered URL-generator closure is exercised through the framework
        // adapter's redirect(), which resolves the route via that closure.
        $response = InertiaAdapter::redirect('dashboard', ['id' => 7]);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/generated/dashboard', $response->getTargetUrl());
        self::assertSame(Response::HTTP_SEE_OTHER, $response->getStatusCode());

        // The closure must forward the route name, params and ABSOLUTE_PATH.
        self::assertCount(1, $router->calls);
        self::assertSame('dashboard', $router->calls[0][0]);
        self::assertSame(['id' => 7], $router->calls[0][1]);
        self::assertSame(UrlGeneratorInterface::ABSOLUTE_PATH, $router->calls[0][2]);
    }

    #[Test]
    public function testRegisterHooksWiresHtmlBootstrapClosure(): void
    {
        MoodleInertiaBootstrap::registerHooks($this->makeRouter());

        $bootstrap = InertiaFactory::getHtmlBootstrap();
        self::assertInstanceOf(Closure::class, $bootstrap);

        $response = $bootstrap(['component' => 'Dashboard'], '{"component":"Dashboard"}', 'data-x="1"');

        self::assertInstanceOf(Response::class, $response);
        // The closure delegates to htmlBootstrap(), so the shell + $PAGE side
        // effects prove the wired path ran end to end.
        self::assertStringContainsString('class="middag-root"', $response->getContent());
        self::assertSame(
            [['local_example/inertia_app', 'init']],
            $GLOBALS['PAGE']->requires->amd,
        );
    }

    #[Test]
    public function testHtmlBootstrapRendersTheInertiaShellAndRegistersAssets(): void
    {
        $json = '{"component":"Home","props":[],"url":"/","version":"dev"}';

        $response = MoodleInertiaBootstrap::htmlBootstrap('local_example/inertia_app', ['component' => 'Home'], $json, 'ignored-attr');

        self::assertInstanceOf(Response::class, $response);
        $body = $response->getContent();

        // Blocking appearance script emitted first (flash-of-wrong-theme guard).
        self::assertStringContainsString('middag-appearance', $body);
        // Mount div + JSON page-data script carry the configured app id ("app" default).
        self::assertStringContainsString('<div id="app" class="middag-root"></div>', $body);
        self::assertStringContainsString('<script type="application/json" data-page="app">' . $json . '</script>', $body);
        // Appearance script precedes the mount div.
        self::assertLessThan(strpos($body, 'middag-root'), strpos($body, 'middag-appearance'));

        // AMD init + both plugin stylesheets registered against $PAGE->requires.
        self::assertSame([['local_example/inertia_app', 'init']], $GLOBALS['PAGE']->requires->amd);
        self::assertSame(
            ['/local/example/styles/middag-app.css', '/local/example/styles/isolation.css'],
            $GLOBALS['PAGE']->requires->css,
        );
    }

    #[Test]
    public function testHtmlBootstrapHonoursTheProductConfiguredAppId(): void
    {
        InertiaFactory::setAppId('middag-app');

        $response = MoodleInertiaBootstrap::htmlBootstrap('local_example/inertia_app', [], '{}', '');
        $body = $response->getContent();

        self::assertStringContainsString('<div id="middag-app" class="middag-root"></div>', $body);
        self::assertStringContainsString('data-page="middag-app"', $body);
    }

    #[Test]
    public function testHtmlBootstrapDerivesAssetPathsFromTheComponentDirectory(): void
    {
        // A non-"local" plugin type must not double a "/local/" prefix: the web
        // base comes from the component directory relative to dirroot verbatim.
        $GLOBALS['CFG']->dirroot = '/var/www/html/moodle';
        $GLOBALS['__middag_test_component_dir'] = '/var/www/html/moodle/mod/unidade';

        MoodleInertiaBootstrap::htmlBootstrap('mod_unidade/inertia_app', [], '{}', '');

        self::assertSame(
            ['/mod/unidade/styles/middag-app.css', '/mod/unidade/styles/isolation.css'],
            $GLOBALS['PAGE']->requires->css,
        );
    }

    private function resetInertiaStatics(): void
    {
        (new ReflectionProperty(InertiaVersionManager::class, 'manualVersion'))->setValue(null, null);
        (new ReflectionProperty(InertiaVersionManager::class, 'bundlePath'))->setValue(null, null);
        (new ReflectionProperty(InertiaAdapter::class, 'urlGenerator'))->setValue(null, null);
        (new ReflectionProperty(InertiaFactory::class, 'htmlBootstrap'))->setValue(null, null);
        (new ReflectionProperty(InertiaFactory::class, 'appId'))->setValue(null, 'app');
    }

    /**
     * Recording $PAGE stand-in: a public `requires` object logging js_call_amd()
     * modules and css() stylesheet URLs, mirroring Moodle's page_requirements.
     */
    private function makePage(): object
    {
        $requires = new class {
            /** @var list<array{0: string, 1: string}> */
            public array $amd = [];

            /** @var list<string> */
            public array $css = [];

            public function js_call_amd(string $fullmodule, string $func, array $params = []): void
            {
                $this->amd[] = [$fullmodule, $func];
            }

            public function css(string $stylesheet): void
            {
                $this->css[] = $stylesheet;
            }
        };

        return new class($requires) {
            public function __construct(public object $requires) {}
        };
    }

    /**
     * Recording RouterInterface stand-in: generateUrl() logs its arguments and
     * returns a deterministic "{prefix}{route}" URL.
     */
    private function makeRouter(string $prefix = '/url/'): object
    {
        return new class($prefix) implements RouterInterface {
            /** @var list<array{0: string, 1: array<string, mixed>, 2: int}> */
            public array $calls = [];

            public function __construct(private readonly string $prefix) {}

            public function initializeContext(): void {}

            public function getRoutes(): RouteCollection
            {
                return new RouteCollection();
            }

            public function getContext(): RequestContext
            {
                return new RequestContext();
            }

            public function registerDefaultRoutes(): void {}

            public function scanAnnotations(ContainerInterface $container, ?string $specific_class = null): void {}

            public function generateUrl(string $route, array $parameters = [], int $reference_type = 1): string
            {
                $this->calls[] = [$route, $parameters, $reference_type];

                return $this->prefix . $route;
            }
        };
    }

    private function deleteTree(string $path): void
    {
        if (is_dir($path)) {
            foreach (scandir($path) ?: [] as $entry) {
                if ($entry !== '.' && $entry !== '..') {
                    $this->deleteTree($path . '/' . $entry);
                }
            }
            @rmdir($path);

            return;
        }

        if (file_exists($path)) {
            @unlink($path);
        }
    }
}
