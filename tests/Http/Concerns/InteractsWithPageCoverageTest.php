<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Http\Concerns;

use core\context;
use core\context\course as context_course;
use core\context\module as context_module;
use core\url as moodle_url;
use Middag\Moodle\Http\Concerns\InteractsWithPage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use TypeError;

/**
 * Exercises the InteractsWithPage trait end to end via concrete host fixtures
 * that `use` it.
 *
 * The trait references ONLY the camelCase surface it declares itself
 * (`$pageUrl`, setPageUrl(), setUrlFromRoute()) plus a single optional
 * collaborator the host composes, `urlGenerator()`. Three host shapes are used:
 *
 * - a plain host with no urlGenerator() — method_exists($this,'urlGenerator')
 *   resolves to false, so setUrlFromRoute()'s body is skipped;
 * - a host that provides urlGenerator(string,array,int): string, driven by
 *   public flags so both the success and the throwing branch are reachable;
 * - a host that overrides setUrlFromRoute() to throw, which is the only way to
 *   reach getPageUrl()'s defensive try/catch around the 'index' route probe
 *   (the trait's own setUrlFromRoute() never throws — it swallows generator
 *   failures internally — but a consumer may compose a resolver that does).
 *
 * No Moodle runtime is required: contexts come from ContextSupport over the
 * bootstrap/support stubs (system() surfaces a TypeError by design of the
 * stub, mirroring ContextSupportCoverageTest), and $PAGE/$CFG are recording
 * doubles restored in tearDown so nothing leaks into the runner.
 *
 * @internal
 */
#[CoversClass(InteractsWithPage::class)]
final class InteractsWithPageCoverageTest extends TestCase
{
    private object $page;

    private mixed $prevPage;

    private mixed $prevCfg;

    protected function setUp(): void
    {
        $this->page = $this->makePage();

        // adminExternalpageSetup / adminLoadNavigation require_once $CFG->libdir.'/adminlib.php';
        // point libdir at a temp dir holding an empty adminlib so the include resolves.
        $libdir = sys_get_temp_dir() . '/middag_interacts_with_page_test';
        if (!is_dir($libdir)) {
            mkdir($libdir, 0o777, true);
        }
        file_put_contents($libdir . '/adminlib.php', "<?php\n");

        $this->prevPage = $GLOBALS['PAGE'] ?? null;
        $this->prevCfg = $GLOBALS['CFG'] ?? null;

        $GLOBALS['PAGE'] = $this->page;
        $GLOBALS['CFG'] = (object) ['libdir' => $libdir, 'wwwroot' => 'https://moodle.test'];

        unset($GLOBALS['__middag_test_admin_externalpage'], $GLOBALS['__middag_test_admin_root']);
    }

    protected function tearDown(): void
    {
        $GLOBALS['PAGE'] = $this->prevPage;
        $GLOBALS['CFG'] = $this->prevCfg;

        unset($GLOBALS['__middag_test_admin_externalpage'], $GLOBALS['__middag_test_admin_root']);
    }

    // --- setContext / getContext -------------------------------------------

    #[Test]
    public function testSetContextWithExplicitContextStoresItVerbatim(): void
    {
        $host = new InteractsWithPageHost();
        $ctx = new context(7);

        $host->callSetContext($ctx);

        self::assertSame($ctx, $host->exposeContext());
        self::assertSame($ctx, $host->callGetContext());
    }

    #[Test]
    public function testSetContextWithNullFallsBackToSystemContextTypeError(): void
    {
        // The null coalescing right operand is ContextSupport::system(); the
        // stub models system::instance() as returning a base core\context, so
        // the wrapper's context_system return type rejects it — proving the
        // fallback branch on the assignment line was taken.
        $host = new InteractsWithPageHost();

        $this->expectException(TypeError::class);

        $host->callSetContext();
    }

    #[Test]
    public function testGetContextWithoutContextResolvesToSystemTypeError(): void
    {
        $host = new InteractsWithPageHost();

        $this->expectException(TypeError::class);

        $host->callGetContext();
    }

    // --- simple page-state setters -----------------------------------------

    #[Test]
    public function testSettersStorePageState(): void
    {
        $host = new InteractsWithPageHost();

        $host->callSetPageUrl('/dashboard');
        $host->callSetPageLayout('admin');
        $host->callSetPageTitle('My title');
        $host->callSetPageHeading('My heading');
        $host->callAddPageNavbar('Home');
        $host->callAddPageNavbar(['Reports', 'https://moodle.test/reports']);

        self::assertSame('/dashboard', $host->exposePageUrl());
        self::assertSame('admin', $host->exposeLayout());
        self::assertSame('My title', $host->exposeTitle());
        self::assertSame('My heading', $host->exposeHeading());
        self::assertSame(
            ['Home', ['Reports', 'https://moodle.test/reports']],
            $host->exposeNavbar(),
        );
    }

    #[Test]
    public function testSetPageUrlAcceptsAMoodleUrlObject(): void
    {
        $host = new InteractsWithPageHost();
        $url = new moodle_url('/mod/view.php');

        $host->callSetPageUrl($url);

        self::assertSame($url, $host->exposePageUrl());
    }

    // --- setUrlFromRoute ----------------------------------------------------

    #[Test]
    public function testSetUrlFromRouteIsNoopWhenHostHasNoUrlGeneratorHook(): void
    {
        // The trait probes method_exists($this, 'urlGenerator'); a plain host
        // does not provide it, so the whole body is skipped and $pageUrl stays
        // untouched (still null).
        $host = new InteractsWithPageHost();

        $host->callSetUrlFromRoute('dashboard');

        self::assertNull($host->exposePageUrl());
    }

    #[Test]
    public function testSetUrlFromRouteUsesUrlGeneratorAndSetsPageUrl(): void
    {
        $host = new InteractsWithPageHostWithHooks();
        $host->urlGeneratorReturn = '/generated/path';

        $host->callSetUrlFromRoute('dashboard', ['id' => 1]);

        // urlGenerator() was invoked with the forwarded route + parameters, and
        // its return was pushed through setPageUrl() onto the trait's $pageUrl.
        self::assertSame([['dashboard', ['id' => 1]]], $host->urlGeneratorArgs);
        self::assertSame('/generated/path', $host->exposePageUrl());
    }

    #[Test]
    public function testSetUrlFromRouteCatchesGeneratorFailureAndNullsPageUrl(): void
    {
        $host = new InteractsWithPageHostWithHooks();
        $host->urlGeneratorThrows = true;
        $host->callSetPageUrl('/stale');

        $host->callSetUrlFromRoute('missing');

        // urlGenerator() threw → the catch branch resets $pageUrl to null and
        // setPageUrl() was never reached with a fresh value.
        self::assertTrue($host->urlGeneratorCalled);
        self::assertNull($host->exposePageUrl());
    }

    // --- resolveContext -----------------------------------------------------

    #[Test]
    public function testResolveContextUsesModuleContextFromCm(): void
    {
        $host = new InteractsWithPageHost();
        $host->setCmProp((object) ['id' => 42]);

        $host->callResolveContext();

        $ctx = $host->exposeContext();
        self::assertInstanceOf(context_module::class, $ctx);
        self::assertSame(42, $ctx->id);
    }

    #[Test]
    public function testResolveContextUsesCourseContextFromCourse(): void
    {
        $host = new InteractsWithPageHost();
        $host->setCourseProp(new InteractsWithPageFakeCourse(99));

        $host->callResolveContext();

        $ctx = $host->exposeContext();
        self::assertInstanceOf(context_course::class, $ctx);
        self::assertSame(99, $ctx->id);
    }

    #[Test]
    public function testResolveContextFallsBackToSystemContextTypeError(): void
    {
        // No cm and no course → else branch resolves the system context, which
        // the stub surfaces as a TypeError (see ContextSupportCoverageTest).
        $host = new InteractsWithPageHost();

        $this->expectException(TypeError::class);

        $host->callResolveContext();
    }

    #[Test]
    public function testResolveContextLeavesAnAlreadyResolvedContextUntouched(): void
    {
        $host = new InteractsWithPageHost();
        $existing = new context(5);
        $host->callSetContext($existing);
        // A cm is present, but the guard is skipped because context is not null.
        $host->setCmProp((object) ['id' => 1]);

        $host->callResolveContext();

        self::assertSame($existing, $host->exposeContext());
    }

    // --- getPageUrl ---------------------------------------------------------

    #[Test]
    public function testGetPageUrlReturnsHomeWhenNothingIsSet(): void
    {
        // $pageUrl is null and the plain host has no urlGenerator, so the
        // setUrlFromRoute('index') probe is a no-op and resolution falls to home.
        $host = new InteractsWithPageHost();

        $url = $host->callGetPageUrl();

        self::assertInstanceOf(moodle_url::class, $url);
        self::assertSame('/', $url->out(false));
    }

    #[Test]
    public function testGetPageUrlBuildsUrlFromStringPageUrl(): void
    {
        $host = new InteractsWithPageHost();
        $host->callSetPageUrl('/reports/index.php');

        $url = $host->callGetPageUrl();

        self::assertInstanceOf(moodle_url::class, $url);
        self::assertSame('/reports/index.php', $url->out(false));
    }

    #[Test]
    public function testGetPageUrlReturnsThePageUrlWhenAlreadyAMoodleUrl(): void
    {
        $host = new InteractsWithPageHost();
        $existing = new moodle_url('/existing/page.php');
        $host->callSetPageUrl($existing);

        self::assertSame($existing, $host->callGetPageUrl());
    }

    #[Test]
    public function testGetPageUrlTreatsEmptyAndZeroStringsAsUnset(): void
    {
        // is_string() passes but the '' / '0' guards reject both, so resolution
        // falls through to the home URL.
        $emptyHost = new InteractsWithPageHost();
        $emptyHost->callSetPageUrl('');

        $zeroHost = new InteractsWithPageHost();
        $zeroHost->callSetPageUrl('0');

        self::assertSame('/', $emptyHost->callGetPageUrl()->out(false));
        self::assertSame('/', $zeroHost->callGetPageUrl()->out(false));
    }

    #[Test]
    public function testGetPageUrlResolvesViaSetUrlFromRouteHook(): void
    {
        // $pageUrl is null → the trait invokes its own setUrlFromRoute('index'),
        // which routes through urlGenerator() and writes the generated string
        // onto $pageUrl; that string then wins the resolution.
        $host = new InteractsWithPageHostWithHooks();
        $host->urlGeneratorReturn = '/from-index-route';

        $url = $host->callGetPageUrl();

        self::assertSame([['index', []]], $host->urlGeneratorArgs);
        self::assertSame('/from-index-route', $url->out(false));
    }

    #[Test]
    public function testGetPageUrlSuppressesGeneratorFailureAndFallsBackToHome(): void
    {
        // urlGenerator() throws inside setUrlFromRoute('index'); its internal
        // catch nulls $pageUrl, so getPageUrl() then falls through to home.
        $host = new InteractsWithPageHostWithHooks();
        $host->urlGeneratorThrows = true;

        $url = $host->callGetPageUrl();

        self::assertTrue($host->urlGeneratorCalled);
        self::assertSame('/', $url->out(false));
    }

    #[Test]
    public function testGetPageUrlSuppressesSetUrlFromRouteThrowAndFallsBackToHome(): void
    {
        // A host that composes a route resolver whose setUrlFromRoute() throws:
        // getPageUrl()'s try/catch around the 'index' probe swallows it and
        // resolution falls through to home. This is the only path that reaches
        // that defensive catch (the trait's own setUrlFromRoute never throws).
        $host = new InteractsWithPageHostThrowingRoute();

        $url = $host->callGetPageUrl();

        self::assertTrue($host->setUrlFromRouteCalled);
        self::assertSame('/', $url->out(false));
    }

    #[Test]
    public function testGetPageUrlSkipsRouteResolutionWhenPageUrlIsAlreadySet(): void
    {
        // A non-null $pageUrl short-circuits the first guard, so the trait never
        // calls setUrlFromRoute()/urlGenerator(); the already-set string wins.
        $host = new InteractsWithPageHostWithHooks();
        $host->callSetPageUrl('/already/set');

        $url = $host->callGetPageUrl();

        self::assertFalse($host->urlGeneratorCalled);
        self::assertSame('/already/set', $url->out(false));
    }

    // --- setupMoodlePage ----------------------------------------------------

    #[Test]
    public function testSetupMoodlePageAppliesSettingsAndNavbarTrail(): void
    {
        $host = new InteractsWithPageHost();
        // Pre-set the context so resolveContext() skips the system fallback.
        $host->callSetContext(new context(11));
        $host->callSetPageLayout('report');
        $host->callSetPageTitle('Reports');
        $host->callSetPageHeading('All reports');
        $host->callAddPageNavbar(['Reports', 'https://moodle.test/reports']);
        $host->callAddPageNavbar('Detail');

        $host->callSetupMoodlePage();

        self::assertSame('report', $this->page->calls['layout']);
        self::assertSame('Reports', $this->page->calls['title']);
        self::assertSame('All reports', $this->page->calls['heading']);
        self::assertInstanceOf(context::class, $this->page->calls['context']);
        self::assertInstanceOf(moodle_url::class, $this->page->calls['url']);
        // One array item (label + action) and one bare string item.
        self::assertSame(
            [['Reports', 'https://moodle.test/reports'], ['Detail', null]],
            $this->page->navbar->added,
        );
        // No admin section configured → the admin hooks were not invoked.
        self::assertArrayNotHasKey('__middag_test_admin_externalpage', $GLOBALS);
    }

    #[Test]
    public function testSetupMoodlePageRunsAdminHooksWhenAnAdminSectionIsSet(): void
    {
        $GLOBALS['__middag_test_admin_root'] = new class {
            public function locate(string $section, bool $strict = false): stdClass
            {
                return (object) ['path' => ['a', 'b', 'c', 'd']];
            }
        };
        $this->page->settingsnav = new class {
            public string $text = 'Node';

            public mixed $action = 'https://moodle.test/node';

            public function get(mixed $key): object
            {
                return $this;
            }
        };

        $host = new InteractsWithPageHost();
        $host->callSetContext(new context(11));
        $host->setAdminSectionProp('mysection');

        $host->callSetupMoodlePage();

        // adminExternalpageSetup() ran with the configured section...
        self::assertSame('mysection', $GLOBALS['__middag_test_admin_externalpage']);
        // ...and adminLoadNavigation() forced the admin layout and walked the tree.
        self::assertSame('admin', $this->page->calls['layout']);
        self::assertSame([true], $this->page->navbar->ignored);
    }

    /**
     * Recording $PAGE double: captures the setter calls setupMoodlePage() makes.
     */
    private function makePage(): object
    {
        return new class {
            /** @var array<string, mixed> */
            public array $calls = [];

            public object $navbar;

            public mixed $settingsnav = null;

            public function __construct()
            {
                $this->navbar = new class {
                    /** @var array<int, array{0: string, 1: mixed}> */
                    public array $added = [];

                    /** @var array<int, mixed> */
                    public array $ignored = [];

                    public function add(string $text, mixed $action = null): void
                    {
                        $this->added[] = [$text, $action];
                    }

                    public function ignore_active(mixed $value = true): void
                    {
                        $this->ignored[] = $value;
                    }
                };
            }

            public function set_context(object $context): void
            {
                $this->calls['context'] = $context;
            }

            public function set_pagelayout(string $layout): void
            {
                $this->calls['layout'] = $layout;
            }

            public function set_title(string $title): void
            {
                $this->calls['title'] = $title;
            }

            public function set_heading(string $heading): void
            {
                $this->calls['heading'] = $heading;
            }

            public function set_url(mixed $url): void
            {
                $this->calls['url'] = $url;
            }
        };
    }
}

/**
 * Concrete host composing the trait and exposing its protected surface.
 *
 * Declares only `$course`/`$cm` (referenced by resolveContext()); the page URL
 * lives in the trait's own `$pageUrl`. Provides NO urlGenerator() hook, so the
 * method_exists() probe in setUrlFromRoute() resolves to false here.
 *
 * @internal
 */
class InteractsWithPageHost
{
    use InteractsWithPage;

    public mixed $course = null;

    public mixed $cm = null;

    public function callSetContext(?context $context = null): void
    {
        $this->setContext($context);
    }

    public function callGetContext(): context
    {
        return $this->getContext();
    }

    public function callSetPageUrl(moodle_url|string $url): void
    {
        $this->setPageUrl($url);
    }

    public function callSetPageLayout(string $layout): void
    {
        $this->setPageLayout($layout);
    }

    public function callSetPageTitle(string $title): void
    {
        $this->setPageTitle($title);
    }

    public function callSetPageHeading(string $heading): void
    {
        $this->setPageHeading($heading);
    }

    public function callAddPageNavbar(array|string $item): void
    {
        $this->addPageNavbar($item);
    }

    public function callSetUrlFromRoute(string $route, array $parameters = []): void
    {
        $this->setUrlFromRoute($route, $parameters);
    }

    public function callResolveContext(): void
    {
        $this->resolveContext();
    }

    public function callSetupMoodlePage(): void
    {
        $this->setupMoodlePage();
    }

    public function callGetPageUrl(): moodle_url
    {
        return $this->getPageUrl();
    }

    public function exposeContext(): ?context
    {
        return $this->context;
    }

    public function exposePageUrl(): moodle_url|string|null
    {
        return $this->pageUrl;
    }

    public function exposeLayout(): string
    {
        return $this->pageLayout;
    }

    public function exposeTitle(): string
    {
        return $this->pageTitle;
    }

    public function exposeHeading(): string
    {
        return $this->pageHeading;
    }

    public function exposeNavbar(): array
    {
        return $this->pageNavbar;
    }

    public function setCourseProp(mixed $course): void
    {
        $this->course = $course;
    }

    public function setCmProp(mixed $cm): void
    {
        $this->cm = $cm;
    }

    public function setAdminSectionProp(string $section): void
    {
        $this->adminSection = $section;
    }
}

/**
 * Host variant that composes the optional urlGenerator() collaborator the trait
 * probes with method_exists(). Behaviour is driven by public flags so both the
 * success and the throwing branch of setUrlFromRoute() are reachable, and the
 * captured call log lets tests assert route resolution ran (or was skipped).
 *
 * @internal
 */
class InteractsWithPageHostWithHooks extends InteractsWithPageHost
{
    public bool $urlGeneratorThrows = false;

    public string $urlGeneratorReturn = '/generated';

    public bool $urlGeneratorCalled = false;

    /** @var array<int, array{0: string, 1: array}> */
    public array $urlGeneratorArgs = [];

    public function urlGenerator(string $route, array $parameters = [], int $referenceType = 0): string
    {
        $this->urlGeneratorCalled = true;
        $this->urlGeneratorArgs[] = [$route, $parameters];

        if ($this->urlGeneratorThrows) {
            throw new RuntimeException('route not found');
        }

        return $this->urlGeneratorReturn;
    }
}

/**
 * Host variant whose composed route resolver throws. Overriding the trait's
 * setUrlFromRoute() is the only way to reach getPageUrl()'s defensive try/catch
 * around the 'index' probe — the trait's own implementation swallows generator
 * failures internally and never propagates.
 *
 * @internal
 */
class InteractsWithPageHostThrowingRoute extends InteractsWithPageHost
{
    public bool $setUrlFromRouteCalled = false;

    public function setUrlFromRoute(string $route, array $parameters = [], int $referenceType = 0): void
    {
        $this->setUrlFromRouteCalled = true;

        throw new RuntimeException('route resolver unavailable');
    }
}

/**
 * Minimal course-shaped double exposing get_id(), as resolveContext() expects.
 *
 * @internal
 */
class InteractsWithPageFakeCourse
{
    public function __construct(private readonly int $id) {}

    public function get_id(): int
    {
        return $this->id;
    }
}
