<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Http\Controller;

use core\context;
use core\context\course as context_course;
use core\context\module as context_module;
use core\exception\coding_exception;
use core\output\renderable;
use core\output\renderer_base;
use core\url as moodle_url;
use Middag\Framework\Form\Renderer\RendererRegistry;
use Middag\Framework\Http\Inertia\InertiaAdapter;
use Middag\Framework\Http\Inertia\InertiaManager;
use Middag\Moodle\Domain\Context\ContextLevel;
use Middag\Moodle\Http\Contract\RouterInterface;
use Middag\Moodle\Http\Controller\AbstractController;
use Middag\Moodle\Kernel\Kernel;
use Middag\Moodle\Security\Contract\AuthenticationInterface;
use Middag\Moodle\Security\Contract\CapabilityInterface;
use Middag\Ui\Form\Contract\FormInterface;
use Middag\Ui\Form\Contract\FormRendererInterface;
use Middag\Ui\Form\FormState;
use Middag\Ui\Shared\Enum\RenderTarget;
use Middag\Ui\Shared\ValueObject\RendererOutput;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionClass;
use ReflectionProperty;
use RuntimeException;
use stdClass;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use TypeError;

/**
 * Coverage for the base controller.
 *
 * AbstractController is abstract, so it is exercised through a concrete
 * subclass ({@see CoverageController}) that exposes each protected/private-path
 * method via public proxies. The Moodle page-setup runtime is stood in with
 * recording $PAGE/$OUTPUT doubles + $CFG; the container is a tiny PSR-11 map
 * carrying recording AuthenticationInterface / CapabilityInterface doubles.
 *
 * ContextSupport::system() surfaces a TypeError by design of the bootstrap stub
 * (core\context\system::instance() returns a base core\context that the
 * wrapper's context_system return type rejects); the null/system fallback
 * branches are therefore asserted through that TypeError, mirroring
 * ContextSupportCoverageTest / InteractsWithPageCoverageTest.
 *
 * @internal
 */
#[CoversClass(AbstractController::class)]
final class AbstractControllerCoverageTest extends TestCase
{
    private object $page;

    private object $output;

    private mixed $prevPage;

    private mixed $prevOutput;

    private mixed $prevCfg;

    /** @var array<string, mixed> */
    private array $prevServer = [];

    protected function setUp(): void
    {
        $this->page = $this->makePage();
        $this->output = $this->makeOutput();

        // adminExternalpageSetup / adminLoadNavigation require_once $CFG->libdir.'/adminlib.php';
        // point libdir at a temp dir holding an empty adminlib so the include resolves.
        $libdir = sys_get_temp_dir() . '/middag_abstract_controller_test';
        if (!is_dir($libdir)) {
            mkdir($libdir, 0o777, true);
        }
        file_put_contents($libdir . '/adminlib.php', "<?php\n");

        $this->prevPage = $GLOBALS['PAGE'] ?? null;
        $this->prevOutput = $GLOBALS['OUTPUT'] ?? null;
        $this->prevCfg = $GLOBALS['CFG'] ?? null;
        $this->prevServer = $_SERVER;

        $GLOBALS['PAGE'] = $this->page;
        $GLOBALS['OUTPUT'] = $this->output;
        $GLOBALS['CFG'] = (object) ['libdir' => $libdir, 'wwwroot' => 'https://moodle.test'];

        unset($GLOBALS['__middag_test_admin_externalpage'], $GLOBALS['__middag_test_admin_root']);

        Kernel::shutdown();

        // The Inertia URL generator is a boot-time seam; wire a deterministic one
        // so inertiaLocation()/inertiaRedirect() resolve without a real router.
        InertiaAdapter::setUrlGenerator(static fn (string $route, array $params = []): string => '/resolved/' . $route);
    }

    protected function tearDown(): void
    {
        $GLOBALS['PAGE'] = $this->prevPage;
        $GLOBALS['OUTPUT'] = $this->prevOutput;
        $GLOBALS['CFG'] = $this->prevCfg;
        $_SERVER = $this->prevServer;

        unset($GLOBALS['__middag_test_admin_externalpage'], $GLOBALS['__middag_test_admin_root']);

        Kernel::shutdown();
        InertiaManager::flush();

        // Reset the static Inertia URL generator so nothing leaks into siblings.
        (new ReflectionProperty(InertiaAdapter::class, 'urlGenerator'))->setValue(null, null);
    }

    // =========================================================================
    // Container & Request wiring
    // =========================================================================

    #[Test]
    public function testSetContainerInitializesRequestFromGlobalsWhenNoneInjected(): void
    {
        $controller = new CoverageController();
        $controller->setContainer($this->makeContainer([]));

        // initializeRequest built a Request from globals (no prior setRequest),
        // so isJson() can now read it (defaults to non-JSON here).
        self::assertFalse($controller->callIsJson());
    }

    #[Test]
    public function testSetRequestKeepsInjectedRequest(): void
    {
        $controller = new CoverageController();
        $request = new Request(server: ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
        $controller->setRequest($request);

        // setContainer must NOT overwrite the already-injected request.
        $controller->setContainer($this->makeContainer([]));

        self::assertTrue($controller->callIsJson());
    }

    // =========================================================================
    // handle() lifecycle + handled guard
    // =========================================================================

    #[Test]
    public function testHandleRunsSetupOnceThenShortCircuits(): void
    {
        $controller = $this->makeController(new Request(), $this->makeContainer([]), new context(1));
        $controller->setPageUrl(new moodle_url('/page'));

        $controller->handle();
        self::assertArrayHasKey('context', $this->page->calls);

        // Second handle() must return early (handled flag) — no further page work.
        $this->page->calls = [];
        $controller->handle();

        self::assertSame([], $this->page->calls);
    }

    #[Test]
    public function testPreHandleDefaultHookIsNoopObservedThroughSetForm(): void
    {
        // The default preHandle() is an empty hook. setForm() always runs it
        // (see setForm()), so on a controller that does not override preHandle()
        // the form is stored without any further lifecycle side effect: handle()
        // is not triggered and the handled flag stays false.
        $controller = new CoverageController();

        $controller->setForm(new ControllerFormDouble());

        self::assertInstanceOf(ControllerFormDouble::class, $controller->exposeForm());
        self::assertFalse($controller->exposeHandled());
    }

    // =========================================================================
    // Auth flag setters
    // =========================================================================

    #[Test]
    public function testSetRequireLoginStoresCourseAndCm(): void
    {
        $controller = new CoverageController();
        $course = $this->makeCourse(5);
        $cm = (object) ['id' => 9];

        $controller->setRequireLogin($course, $cm);

        self::assertTrue($controller->exposeRequireLoginFlag());
    }

    #[Test]
    public function testSetRequireCapabilitiesStoresContextLevel(): void
    {
        $cap = $this->makeCapability();
        $controller = $this->makeController(new Request(), $this->makeContainer([CapabilityInterface::class => $cap]));
        $controller->setRequireCapabilities(['cap/x'], ContextLevel::COURSE, 3);

        $controller->callCheckCapabilities();

        self::assertSame([['cap/x', ContextLevel::COURSE, 3]], $cap->authorized);
    }

    #[Test]
    public function testSetRequireCapabilitiesStoresNullForNonContextLevel(): void
    {
        $cap = $this->makeCapability();
        $controller = $this->makeController(new Request(), $this->makeContainer([CapabilityInterface::class => $cap]));

        // A plain string context is not a ContextLevel, so it falls back to SYSTEM.
        $controller->setRequireCapabilities(['cap/y'], 'not-a-level', 0);

        $controller->callCheckCapabilities();

        self::assertSame([['cap/y', ContextLevel::SYSTEM, 0]], $cap->authorized);
    }

    // =========================================================================
    // requireLogin() (inlined auth guard)
    // =========================================================================

    #[Test]
    public function testRequireLoginNoopWhenNoFlagsSet(): void
    {
        $auth = $this->makeAuth();
        $controller = $this->makeController(new Request(), $this->makeContainer([AuthenticationInterface::class => $auth]));

        $controller->callRequireLogin();

        self::assertSame(0, $auth->requireLoginCalls);
        self::assertSame(0, $auth->requireSesskeyCalls);
    }

    #[Test]
    public function testRequireLoginWithoutCoursePassesNullCourseId(): void
    {
        $auth = $this->makeAuth();
        $controller = $this->makeController(new Request(), $this->makeContainer([AuthenticationInterface::class => $auth]));
        $controller->setRequireLogin();

        $controller->callRequireLogin();

        self::assertSame([[null, true]], $auth->requireLoginArgs);
    }

    #[Test]
    public function testRequireLoginWithCoursePassesCourseId(): void
    {
        $auth = $this->makeAuth();
        $controller = $this->makeController(new Request(), $this->makeContainer([AuthenticationInterface::class => $auth]));
        $controller->setRequireLogin($this->makeCourse(7));

        $controller->callRequireLogin();

        self::assertSame([[7, true]], $auth->requireLoginArgs);
    }

    #[Test]
    public function testRequireSesskeyEnforcedOnNonIdempotentMethod(): void
    {
        $auth = $this->makeAuth();
        $controller = $this->makeController(new Request(server: ['REQUEST_METHOD' => 'POST']), $this->makeContainer([AuthenticationInterface::class => $auth]));
        $controller->setRequireSesskey(true);

        $controller->callRequireLogin();

        self::assertSame(1, $auth->requireSesskeyCalls);
    }

    #[Test]
    public function testRequireSesskeySkippedOnIdempotentMethod(): void
    {
        $auth = $this->makeAuth();
        $controller = $this->makeController(new Request(server: ['REQUEST_METHOD' => 'GET']), $this->makeContainer([AuthenticationInterface::class => $auth]));
        $controller->setRequireSesskey(true);

        $controller->callRequireLogin();

        self::assertSame(0, $auth->requireSesskeyCalls);
    }

    // =========================================================================
    // checkCapabilities()
    // =========================================================================

    #[Test]
    public function testCheckCapabilitiesNoopWhenEmpty(): void
    {
        $cap = $this->makeCapability();
        $controller = $this->makeController(new Request(), $this->makeContainer([CapabilityInterface::class => $cap]));

        $controller->callCheckCapabilities();

        self::assertSame([], $cap->authorized);
    }

    // =========================================================================
    // Forms
    // =========================================================================

    #[Test]
    public function testSetFormThrowsWhenClassStringDoesNotExist(): void
    {
        $controller = new CoverageController();

        $this->expectException(coding_exception::class);
        $controller->setForm('This\Class\Does\Not\Exist');
    }

    #[Test]
    public function testSetFormInstantiatesFromClassString(): void
    {
        $controller = new CoverageController();

        $controller->setForm(DummyFormForController::class, ['param' => 1]);

        self::assertInstanceOf(DummyFormForController::class, $controller->exposeForm());
    }

    #[Test]
    public function testSetFormStoresObjectVerbatim(): void
    {
        $controller = new CoverageController();
        $form = new ControllerFormDouble();

        $controller->setForm($form);

        self::assertSame($form, $controller->exposeForm());
    }

    #[Test]
    public function testSetFormAlwaysInvokesPreHandleHook(): void
    {
        // setForm() unconditionally runs the preHandle() lifecycle hook before
        // building the form. A subclass overriding preHandle() observes the call.
        $controller = new PreHandleCoverageController();
        self::assertFalse($controller->preHandleRan);

        $controller->setForm(new ControllerFormDouble());

        self::assertTrue($controller->preHandleRan);
        self::assertInstanceOf(ControllerFormDouble::class, $controller->exposeForm());
    }

    #[Test]
    public function testHandleFormSubmissionTrueWhenSubmittedAndValidated(): void
    {
        $controller = new CoverageController();
        $controller->forceForm(new ControllerFormDouble(submitted: true, validated: true));

        self::assertTrue($controller->callHandleFormSubmission());
    }

    #[Test]
    public function testHandleFormSubmissionFalseWhenNotValidated(): void
    {
        $controller = new CoverageController();
        $controller->forceForm(new ControllerFormDouble(submitted: true, validated: false));

        self::assertFalse($controller->callHandleFormSubmission());
    }

    #[Test]
    public function testHandleFormSubmissionFalseWhenNoForm(): void
    {
        $controller = new CoverageController();

        self::assertFalse($controller->callHandleFormSubmission());
    }

    #[Test]
    public function testProcessFormSubmissionReturnsDataWhenValid(): void
    {
        $controller = new CoverageController();
        $controller->forceForm(new ControllerFormDouble(submitted: true, validated: true, data: ['a' => 1]));

        self::assertSame(['a' => 1], $controller->callProcessFormSubmission());
    }

    #[Test]
    public function testProcessFormSubmissionReturnsFalseWhenInvalid(): void
    {
        $controller = new CoverageController();
        $controller->forceForm(new ControllerFormDouble(submitted: false));

        self::assertFalse($controller->callProcessFormSubmission());
    }

    #[Test]
    public function testProcessFormCancelReflectsFormState(): void
    {
        $controller = new CoverageController();
        $controller->forceForm(new ControllerFormDouble(cancelled: true));

        self::assertTrue($controller->callProcessFormCancel());
    }

    #[Test]
    public function testProcessFormCancelFalseWhenNoForm(): void
    {
        $controller = new CoverageController();

        self::assertFalse($controller->callProcessFormCancel());
    }

    #[Test]
    public function testIsFormSubmittedReflectsFormState(): void
    {
        $controller = new CoverageController();
        $controller->forceForm(new ControllerFormDouble(submitted: true));

        self::assertTrue($controller->callIsFormSubmitted());
    }

    #[Test]
    public function testIsFormSubmittedFalseWhenNoForm(): void
    {
        $controller = new CoverageController();

        self::assertFalse($controller->callIsFormSubmitted());
    }

    #[Test]
    public function testRenderFormHtmlReturnsEmptyStringWhenNoForm(): void
    {
        $controller = new CoverageController();

        self::assertSame('', $controller->callRenderFormHtml());
    }

    #[Test]
    public function testRenderFormHtmlCapturesFormDisplayOutput(): void
    {
        $controller = new CoverageController();
        $controller->forceForm(new ControllerFormDouble(displayHtml: '<form-body>'));

        self::assertSame('<form-body>', $controller->callRenderFormHtml());
    }

    #[Test]
    public function testRenderFormHtmlWithNonObjectFormReturnsEmptyBuffer(): void
    {
        // A non-empty, non-object form passes the empty() guard but skips the
        // is_object() display() call, so the buffered output is empty.
        $controller = new CoverageController();
        $controller->forceForm('a-plain-string-form');

        self::assertSame('', $controller->callRenderFormHtml());
    }

    // =========================================================================
    // renderForm() + defaultRenderTarget()
    // =========================================================================

    #[Test]
    public function testDefaultRenderTargetIsHtml(): void
    {
        $controller = new CoverageController();

        self::assertSame(RenderTarget::HTML, $controller->callDefaultRenderTarget());
    }

    #[Test]
    public function testRenderFormReturns500WhenRegistryMissing(): void
    {
        $controller = $this->makeController(new Request(), $this->makeContainer([]), new context(1));

        $response = $controller->callRenderForm($this->makeFormInterface());

        self::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
    }

    #[Test]
    public function testRenderFormHtmlTargetWrapsBodyInPageLayout(): void
    {
        $registry = new RendererRegistry([$this->makeHtmlRenderer('<rendered-form>')]);
        $controller = $this->makeController(new Request(), $this->makeContainer([RendererRegistry::class => $registry]), new context(1));
        $controller->setPageUrl(new moodle_url('/form'));

        // Null target falls through to defaultRenderTarget() (HTML).
        $response = $controller->callRenderForm($this->makeFormInterface());

        self::assertStringContainsString('<rendered-form>', (string) $response->getContent());
        self::assertStringContainsString('[header]', (string) $response->getContent());
    }

    #[Test]
    public function testRenderFormPropsTargetProducesInertiaResponse(): void
    {
        $_SERVER['HTTP_X_INERTIA'] = 'true';

        $registry = new RendererRegistry([$this->makePropsRenderer(['field' => 'v'])]);
        $controller = $this->makeController(new Request(), $this->makeContainer([RendererRegistry::class => $registry]), new context(1));
        $controller->setPageUrl(new moodle_url('/form'));

        $response = $controller->callRenderForm($this->makeFormInterface(), RenderTarget::PROPS);

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertStringContainsString('FormPage', (string) $response->getContent());
    }

    // =========================================================================
    // resolveContext()
    // =========================================================================

    #[Test]
    public function testResolveContextKeepsExistingContext(): void
    {
        $controller = new CoverageController();
        $ctx = new context(11);
        $controller->setContext($ctx);

        $controller->callResolveContext();

        self::assertSame($ctx, $controller->getContext());
    }

    #[Test]
    public function testResolveContextUsesModuleContextFromCm(): void
    {
        $controller = new CoverageController();
        $controller->setRequireLogin($this->makeCourse(5), (object) ['id' => 9]);

        $controller->callResolveContext();

        self::assertInstanceOf(context_module::class, $controller->getContext());
    }

    #[Test]
    public function testResolveContextUsesCourseContextFromCourse(): void
    {
        $controller = new CoverageController();
        $controller->setRequireLogin($this->makeCourse(5));

        $controller->callResolveContext();

        self::assertInstanceOf(context_course::class, $controller->getContext());
    }

    #[Test]
    public function testResolveContextFallsBackToSystemContextTypeError(): void
    {
        // No context, course or cm → the else branch calls ContextSupport::system(),
        // whose stub returns a base core\context the wrapper's return type rejects.
        $controller = new CoverageController();

        $this->expectException(TypeError::class);
        $controller->callResolveContext();
    }

    // =========================================================================
    // setContext() / getContext()
    // =========================================================================

    #[Test]
    public function testSetContextWithNullFallsBackToSystemTypeError(): void
    {
        $controller = new CoverageController();

        $this->expectException(TypeError::class);
        $controller->setContext();
    }

    #[Test]
    public function testGetContextWithoutContextResolvesToSystemTypeError(): void
    {
        $controller = new CoverageController();

        $this->expectException(TypeError::class);
        $controller->getContext();
    }

    // =========================================================================
    // setupMoodlePage()
    // =========================================================================

    #[Test]
    public function testSetupMoodlePageAppliesSettingsAndNavbar(): void
    {
        $controller = $this->makeController(new Request(), $this->makeContainer([]), new context(1));
        $controller->setPageUrl(new moodle_url('/here'));
        $controller->setPageLayout('admin');
        $controller->setPageTitle('My title');
        $controller->setPageHeading('My heading');
        $controller->addPageNavbar(['Home', '/home']); // array item
        $controller->addPageNavbar('Bare label');       // string item

        $controller->callSetupMoodlePage();

        self::assertSame('admin', $this->page->calls['layout']);
        self::assertSame('My title', $this->page->calls['title']);
        self::assertSame('My heading', $this->page->calls['heading']);
        self::assertCount(2, $this->page->navbar->added);
        self::assertSame(['Home', '/home'], $this->page->navbar->added[0]);
        self::assertSame(['Bare label', null], $this->page->navbar->added[1]);
    }

    #[Test]
    public function testSetupMoodlePageRunsAdminExternalPageSetupWhenAdminSectionSet(): void
    {
        $controller = $this->makeController(new Request(), $this->makeContainer([]), new context(1));
        $controller->setPageUrl(new moodle_url('/admin/here'));
        $controller->forceAdminSection('mysection');

        $GLOBALS['__middag_test_admin_root'] = new class {
            public function locate(string $section, bool $strict = false): stdClass
            {
                return (object) ['path' => ['a', 'b', 'c', 'd']];
            }
        };

        $controller->callSetupMoodlePage();

        self::assertSame('mysection', $GLOBALS['__middag_test_admin_externalpage']);
    }

    // =========================================================================
    // getPageUrl()
    // =========================================================================

    #[Test]
    public function testGetPageUrlReturnsExistingMoodleUrl(): void
    {
        $controller = new CoverageController();
        $url = new moodle_url('https://moodle.test/set');
        $controller->setPageUrl($url);

        self::assertSame($url, $controller->callGetPageUrl());
    }

    #[Test]
    public function testGetPageUrlBuildsUrlFromString(): void
    {
        $controller = new CoverageController();
        $controller->setPageUrl('/some/path');

        $url = $controller->callGetPageUrl();

        self::assertInstanceOf(moodle_url::class, $url);
        self::assertStringContainsString('/some/path', $url->out(false));
    }

    #[Test]
    public function testGetPageUrlFallsBackToHomeWhenUnsetAndRouteResolutionFails(): void
    {
        // pageUrl null → setUrlFromRoute('index') → urlGenerator() → Kernel::routing()
        // throws (no container builder registered) → suppressed → home() fallback.
        $controller = new CoverageController();

        $url = $controller->callGetPageUrl();

        self::assertInstanceOf(moodle_url::class, $url);
        self::assertSame('/', $url->out(false));
    }

    // =========================================================================
    // setUrlFromRoute() + urlGenerator()
    // =========================================================================

    #[Test]
    public function testSetUrlFromRouteSetsPageUrlOnSuccess(): void
    {
        $this->bootKernelWithRouter('https://moodle.test/generated');
        $controller = new CoverageController();

        $controller->setUrlFromRoute('some_route', ['id' => 1]);

        $url = $controller->callGetPageUrl();
        self::assertInstanceOf(moodle_url::class, $url);
        self::assertSame('https://moodle.test/generated', $url->out(false));
    }

    #[Test]
    public function testSetUrlFromRouteNullsPageUrlOnFailure(): void
    {
        // No booted kernel → urlGenerator throws → catch nulls pageUrl.
        $controller = new CoverageController();
        $controller->setPageUrl(new moodle_url('/pre-existing'));

        $controller->setUrlFromRoute('missing_route');

        self::assertNull($controller->exposePageUrl());
    }

    #[Test]
    public function testUrlGeneratorWrapsRouterOutputInMoodleUrl(): void
    {
        $this->bootKernelWithRouter('https://moodle.test/gen');
        $controller = new CoverageController();

        $url = $controller->callUrlGenerator('route', ['a' => 1]);

        self::assertInstanceOf(moodle_url::class, $url);
        self::assertSame('https://moodle.test/gen', $url->out(false));
    }

    // =========================================================================
    // Response helpers: response / jsonResponse / isJson / isInertiaRequest
    // =========================================================================

    #[Test]
    public function testResponseReturnsJsonForArrayData(): void
    {
        $controller = $this->makeController(new Request(), $this->makeContainer([]));

        $response = $controller->callResponse(['a' => 1]);

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertStringContainsString('"a":1', (string) $response->getContent());
    }

    #[Test]
    public function testResponseReturnsPlainResponseForScalarWhenNotJson(): void
    {
        $controller = new CoverageController();
        $controller->setRequest(new Request());

        $response = $controller->callResponse('plain text');

        self::assertNotInstanceOf(JsonResponse::class, $response);
        self::assertSame('plain text', (string) $response->getContent());
    }

    #[Test]
    public function testResponseReturnsJsonForScalarWhenRequestIsJson(): void
    {
        $controller = new CoverageController();
        $controller->setRequest(new Request(server: ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']));

        $response = $controller->callResponse('data', 201);

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(201, $response->getStatusCode());
    }

    #[Test]
    public function testJsonResponseBuildsJsonResponseWithStatus(): void
    {
        $controller = new CoverageController();

        $response = $controller->callJsonResponse(['k' => 'v'], 202);

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(202, $response->getStatusCode());
    }

    #[Test]
    public function testIsJsonFalseWithoutRequest(): void
    {
        $controller = new CoverageController();

        self::assertFalse($controller->callIsJson());
    }

    #[Test]
    public function testIsJsonTrueForXmlHttpRequest(): void
    {
        $controller = new CoverageController();
        $controller->setRequest(new Request(server: ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']));

        self::assertTrue($controller->callIsJson());
    }

    #[Test]
    public function testIsJsonTrueForAcceptJsonHeader(): void
    {
        $controller = new CoverageController();
        $controller->setRequest(new Request(server: ['HTTP_ACCEPT' => 'application/json']));

        self::assertTrue($controller->callIsJson());
    }

    #[Test]
    public function testIsJsonFalseForPlainRequest(): void
    {
        $controller = new CoverageController();
        $controller->setRequest(new Request(server: ['HTTP_ACCEPT' => 'text/html']));

        self::assertFalse($controller->callIsJson());
    }

    #[Test]
    public function testIsInertiaRequestFalseWithoutRequest(): void
    {
        $controller = new CoverageController();

        self::assertFalse($controller->callIsInertiaRequest());
    }

    #[Test]
    public function testIsInertiaRequestTrueWithHeader(): void
    {
        $controller = new CoverageController();
        $controller->setRequest(new Request(server: ['HTTP_X_INERTIA' => 'true']));

        self::assertTrue($controller->callIsInertiaRequest());
    }

    #[Test]
    public function testIsInertiaRequestFalseWithoutHeader(): void
    {
        $controller = new CoverageController();
        $controller->setRequest(new Request());

        self::assertFalse($controller->callIsInertiaRequest());
    }

    // =========================================================================
    // render() variants
    // =========================================================================

    #[Test]
    public function testRenderWrapsStringContentInPageLayout(): void
    {
        $controller = $this->readyRenderController();

        $response = $controller->callRender('body-html');

        self::assertSame('[header]body-html[footer]', (string) $response->getContent());
    }

    #[Test]
    public function testRenderUnwrapsResponseContent(): void
    {
        $controller = $this->readyRenderController();

        $response = $controller->callRender(new Response('inner-response'));

        self::assertStringContainsString('inner-response', (string) $response->getContent());
    }

    #[Test]
    public function testRenderUsesComponentRendererForRenderable(): void
    {
        $controller = $this->readyRenderController();

        $response = $controller->callRender(new ControllerRenderable(), 'local_example');

        self::assertStringContainsString('[rendered:', (string) $response->getContent());
    }

    #[Test]
    public function testRenderFromWidgetRendersWidget(): void
    {
        $controller = $this->readyRenderController();

        $response = $controller->callRenderFromWidget('VueThing', ['x' => 1]);

        self::assertStringContainsString('[rendered:', (string) $response->getContent());
    }

    #[Test]
    public function testRenderFromRendererRendersWidget(): void
    {
        $controller = $this->readyRenderController();

        $response = $controller->callRenderFromRenderer(new ControllerRenderable());

        self::assertStringContainsString('[rendered:', (string) $response->getContent());
    }

    #[Test]
    public function testRenderFromTemplateRendersTemplate(): void
    {
        $controller = $this->readyRenderController();

        $response = $controller->callRenderFromTemplate('local_example/tpl', ['a' => 1]);

        self::assertStringContainsString('[tpl:local_example/tpl]', (string) $response->getContent());
    }

    // =========================================================================
    // inertia() / inertiaLocation() / inertiaRedirect()
    // =========================================================================

    #[Test]
    public function testInertiaReturnsJsonResponseForInertiaRequest(): void
    {
        $_SERVER['HTTP_X_INERTIA'] = 'true';
        $controller = $this->readyRenderController();

        $response = $controller->callInertia('Dashboard', ['p' => 1]);

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertStringContainsString('Dashboard', (string) $response->getContent());
    }

    #[Test]
    public function testInertiaWrapsHtmlBootstrapForInitialVisit(): void
    {
        // No X-Inertia in globals → InertiaResponse yields the default HTML shell,
        // which render() then wraps in the Moodle page layout.
        $controller = $this->readyRenderController();

        $response = $controller->callInertia('Landing', []);

        self::assertNotInstanceOf(JsonResponse::class, $response);
        $content = (string) $response->getContent();
        self::assertStringContainsString('[header]', $content);
        self::assertStringContainsString('Landing', $content);
    }

    #[Test]
    public function testInertiaLocationReturnsRedirectResponse(): void
    {
        $controller = new CoverageController();

        $response = $controller->callInertiaLocation('some_route', ['id' => 1]);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/resolved/some_route', $response->getTargetUrl());
    }

    #[Test]
    public function testInertiaRedirectReturnsSeeOtherRedirect(): void
    {
        $controller = new CoverageController();

        $response = $controller->callInertiaRedirect('other_route');

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(Response::HTTP_SEE_OTHER, $response->getStatusCode());
        self::assertSame('/resolved/other_route', $response->getTargetUrl());
    }

    // =========================================================================
    // errorPage()
    // =========================================================================

    #[Test]
    public function testErrorPageRendersNotificationWithStatus(): void
    {
        $controller = $this->readyRenderController();

        ob_start();
        $response = $controller->callErrorPage('Something broke');
        ob_end_clean();

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertStringContainsString('[notify:Something broke]', (string) $response->getContent());
    }

    #[Test]
    public function testErrorPageAppendsEscapedDebugOutput(): void
    {
        $controller = $this->makeController(
            new Request(query: ['debug_output' => '<b>trace</b>']),
            $this->makeContainer([]),
            new context(1),
        );
        $controller->setPageUrl(new moodle_url('/err'));

        ob_start();
        $response = $controller->callErrorPage('Broke', 422);
        ob_end_clean();

        self::assertSame(422, $response->getStatusCode());
        $content = (string) $response->getContent();
        self::assertStringContainsString('<pre>', $content);
        self::assertStringContainsString('&lt;b&gt;trace&lt;/b&gt;', $content);
    }

    // =========================================================================
    // redirect() / redirectToRoute()
    // =========================================================================

    #[Test]
    public function testRedirectFromMoodleUrl(): void
    {
        $controller = new CoverageController();

        $response = $controller->callRedirect(new moodle_url('/target'), 301);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/target', $response->getTargetUrl());
        self::assertSame(301, $response->getStatusCode());
    }

    #[Test]
    public function testRedirectFromString(): void
    {
        $controller = new CoverageController();

        $response = $controller->callRedirect('/plain');

        self::assertSame('/plain', $response->getTargetUrl());
        self::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
    }

    #[Test]
    public function testRedirectToRouteResolvesFirstCandidate(): void
    {
        $this->bootKernelWithRouter('https://moodle.test/route');
        $controller = new CoverageController();

        $response = $controller->callRedirectToRoute('dashboard', ['id' => 5]);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('https://moodle.test/route', $response->getTargetUrl());
    }

    #[Test]
    public function testRedirectToRouteFallsBackWhenAllRoutesFail(): void
    {
        // No booted kernel → every urlGenerator() throws → ultimate fallback,
        // derived from the running host component via ComponentContext.
        $controller = new CoverageController();

        $response = $controller->callRedirectToRoute('nope');

        self::assertSame('/local/example/index.php', $response->getTargetUrl());
    }

    // =========================================================================
    // getService() / authentication()
    // =========================================================================

    #[Test]
    public function testGetServiceReturnsRegisteredService(): void
    {
        $service = new stdClass();
        $controller = $this->makeController(new Request(), $this->makeContainer(['svc' => $service]));

        self::assertSame($service, $controller->callGetService('svc'));
    }

    #[Test]
    public function testGetServiceReturnsNullWhenMissing(): void
    {
        $controller = $this->makeController(new Request(), $this->makeContainer([]));

        self::assertNull($controller->callGetService('absent'));
    }

    #[Test]
    public function testGetServiceReturnsNullAndTracesWhenContainerThrows(): void
    {
        // has() true but get() throws a ContainerExceptionInterface → caught,
        // traced, and null returned.
        $controller = new CoverageController();
        $controller->setContainer($this->makeThrowingContainer('boom'));

        self::assertNull($controller->callGetService('boom'));
    }

    #[Test]
    public function testAuthenticationResolvesAdapterFromContainer(): void
    {
        $auth = $this->makeAuth();
        $controller = $this->makeController(new Request(), $this->makeContainer([AuthenticationInterface::class => $auth]));

        self::assertSame($auth, $controller->callAuthentication());
    }

    // =========================================================================
    // Fixtures & helpers
    // =========================================================================

    private function makeController(Request $request, ContainerInterface $container, ?context $context = null): CoverageController
    {
        $controller = new CoverageController();
        $controller->setRequest($request);
        $controller->setContainer($container);
        if ($context instanceof context) {
            $controller->setContext($context);
        }

        return $controller;
    }

    /**
     * A controller wired for the full render() runtime: empty container, request,
     * an explicit context (so resolveContext skips ContextSupport::system()) and a
     * pre-set page URL (so getPageUrl skips the router).
     */
    private function readyRenderController(): CoverageController
    {
        $controller = $this->makeController(new Request(), $this->makeContainer([]), new context(1));
        $controller->setPageUrl(new moodle_url('/page'));

        return $controller;
    }

    /**
     * @param array<string, mixed> $services
     */
    private function makeContainer(array $services): ContainerInterface
    {
        return new class($services) implements ContainerInterface {
            /** @param array<string, mixed> $services */
            public function __construct(private array $services) {}

            public function get(string $id): mixed
            {
                if (!array_key_exists($id, $this->services)) {
                    throw new class('not found') extends RuntimeException implements NotFoundExceptionInterface {};
                }

                return $this->services[$id];
            }

            public function has(string $id): bool
            {
                return array_key_exists($id, $this->services);
            }
        };
    }

    private function makeThrowingContainer(string $id): ContainerInterface
    {
        return new class($id) implements ContainerInterface {
            public function __construct(private readonly string $id) {}

            public function get(string $id): mixed
            {
                throw new class('boom') extends RuntimeException implements NotFoundExceptionInterface {};
            }

            public function has(string $id): bool
            {
                return $id === $this->id;
            }
        };
    }

    private function makeAuth(): object
    {
        return new class implements AuthenticationInterface {
            public int $requireLoginCalls = 0;

            public int $requireSesskeyCalls = 0;

            /** @var array<int, array{0: null|int, 1: bool}> */
            public array $requireLoginArgs = [];

            public function requireLogin(?int $courseid = null, bool $autologinguest = true): void
            {
                ++$this->requireLoginCalls;
                $this->requireLoginArgs[] = [$courseid, $autologinguest];
            }

            public function isLoggedIn(): bool
            {
                return true;
            }

            public function isGuest(): bool
            {
                return false;
            }

            public function requireSesskey(): void
            {
                ++$this->requireSesskeyCalls;
            }
        };
    }

    private function makeCapability(): object
    {
        return new class implements CapabilityInterface {
            /** @var array<int, array{0: string, 1: ContextLevel, 2: int}> */
            public array $authorized = [];

            public function can(string $capability, ContextLevel $contextlevel = ContextLevel::SYSTEM, int $instanceid = 0, ?int $userid = null): bool
            {
                return true;
            }

            public function authorize(string $capability, ContextLevel $contextlevel = ContextLevel::SYSTEM, int $instanceid = 0, ?int $userid = null): void
            {
                $this->authorized[] = [$capability, $contextlevel, $instanceid];
            }
        };
    }

    private function makeCourse(int $id): object
    {
        return new class($id) {
            public function __construct(private readonly int $id) {}

            public function get_id(): int
            {
                return $this->id;
            }
        };
    }

    private function makeFormInterface(): FormInterface
    {
        return new class implements FormInterface {
            public function schema(): array
            {
                return [];
            }

            public function hydrate(array $input): void {}

            public function validate(): void {}

            public function isSubmittedAndValid(): bool
            {
                return false;
            }

            public function validated(): array
            {
                return [];
            }

            public function errors(): array
            {
                return [];
            }

            public function state(): FormState
            {
                throw new RuntimeException('unused in coverage');
            }
        };
    }

    private function makeHtmlRenderer(string $body): FormRendererInterface
    {
        return new class($body) implements FormRendererInterface {
            public function __construct(private readonly string $body) {}

            public static function target(): RenderTarget
            {
                return RenderTarget::HTML;
            }

            public function render(FormInterface $form): RendererOutput
            {
                return RendererOutput::html(RenderTarget::HTML, $this->body);
            }
        };
    }

    private function makePropsRenderer(array $props): FormRendererInterface
    {
        return new class($props) implements FormRendererInterface {
            /** @param array<string, mixed> $props */
            public function __construct(private readonly array $props) {}

            public static function target(): RenderTarget
            {
                return RenderTarget::PROPS;
            }

            public function render(FormInterface $form): RendererOutput
            {
                return RendererOutput::props(RenderTarget::PROPS, $this->props);
            }
        };
    }

    private function bootKernelWithRouter(string $generatedUrl): void
    {
        $router = new class($generatedUrl) implements RouterInterface {
            public function __construct(private readonly string $generatedUrl) {}

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

            public function scanAnnotations(ContainerInterface $container, ?string $specificClass = null): void {}

            public function generateUrl(string $route, array $parameters = [], int $referenceType = 1): string
            {
                return $this->generatedUrl;
            }
        };

        $reflection = new ReflectionClass(Kernel::class);
        $kernel = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('booted')->setValue($kernel, true);
        $reflection->getProperty('router')->setValue($kernel, $router);
        $reflection->getProperty('instance')->setValue(null, $kernel);
    }

    private function makePage(): object
    {
        return new class {
            /** @var array<string, mixed> */
            public array $calls = [];

            public object $navbar;

            public object $requires;

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

                $this->requires = new class {
                    public function js_amd_inline(string $code): void {}
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

            public function get_renderer(string $component): renderer_base
            {
                return new class extends renderer_base {
                    public function render(object $renderable): string
                    {
                        return '[rendered:' . $renderable::class . ']';
                    }
                };
            }
        };
    }

    private function makeOutput(): object
    {
        return new class {
            public function header(): string
            {
                return '[header]';
            }

            public function footer(): string
            {
                return '[footer]';
            }

            public function notification(string $message, string $type = 'notifyinfo'): string
            {
                return '[notify:' . $message . ']';
            }

            public function render_from_template(string $templatename, array $context): string
            {
                return '[tpl:' . $templatename . ']';
            }
        };
    }
}

/**
 * Concrete controller exposing AbstractController's protected surface for tests.
 *
 * @internal
 */
class CoverageController extends AbstractController
{
    public function callRequireLogin(): void
    {
        $this->requireLogin();
    }

    public function callCheckCapabilities(): void
    {
        $this->checkCapabilities();
    }

    public function callHandleFormSubmission(): bool
    {
        return $this->handleFormSubmission();
    }

    public function callProcessFormSubmission(): mixed
    {
        return $this->processFormSubmission();
    }

    public function callProcessFormCancel(): bool
    {
        return $this->processFormCancel();
    }

    public function callIsFormSubmitted(): bool
    {
        return $this->isFormSubmitted();
    }

    public function callRenderFormHtml(): string
    {
        return $this->renderFormHtml();
    }

    public function callRenderForm(FormInterface $form, ?RenderTarget $target = null): Response
    {
        return $this->renderForm($form, $target);
    }

    public function callDefaultRenderTarget(): RenderTarget
    {
        return $this->defaultRenderTarget();
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

    public function callResponse(mixed $data, int $status = Response::HTTP_OK): JsonResponse|Response
    {
        return $this->response($data, $status);
    }

    public function callJsonResponse(mixed $data, int $status = Response::HTTP_OK): JsonResponse
    {
        return $this->jsonResponse($data, $status);
    }

    public function callIsJson(): bool
    {
        return $this->isJson();
    }

    public function callIsInertiaRequest(): bool
    {
        return $this->isInertiaRequest();
    }

    public function callRender(object|string $content = '', ?string $component = null): Response
    {
        return $this->render($content, $component);
    }

    public function callRenderFromWidget(string $vueComponent, array $props = [], ?string $component = null): Response
    {
        return $this->renderFromWidget($vueComponent, $props, $component);
    }

    public function callRenderFromRenderer(renderable $widget, ?string $component = null): Response
    {
        return $this->renderFromRenderer($widget, $component);
    }

    public function callRenderFromTemplate(string $templatename, array $context = []): Response
    {
        return $this->renderFromTemplate($templatename, $context);
    }

    public function callInertia(string $component, array $props = []): Response
    {
        return $this->inertia($component, $props);
    }

    public function callInertiaLocation(string $route, array $params = []): RedirectResponse|Response
    {
        return $this->inertiaLocation($route, $params);
    }

    public function callInertiaRedirect(string $route, array $params = []): RedirectResponse
    {
        return $this->inertiaRedirect($route, $params);
    }

    public function callErrorPage(string $message, int $status = Response::HTTP_BAD_REQUEST): Response
    {
        return $this->errorPage($message, $status);
    }

    public function callUrlGenerator(string $route, array $parameters = []): moodle_url
    {
        return $this->urlGenerator($route, $parameters);
    }

    public function callRedirect(moodle_url|string $url, int $status = Response::HTTP_FOUND): RedirectResponse
    {
        return $this->redirect($url, $status);
    }

    public function callRedirectToRoute(string $route, array $parameters = [], int $status = Response::HTTP_FOUND): RedirectResponse
    {
        return $this->redirectToRoute($route, $parameters, $status);
    }

    public function callGetService(string $serviceName): mixed
    {
        return $this->getService($serviceName);
    }

    public function callAuthentication(): AuthenticationInterface
    {
        return $this->authentication();
    }

    public function forceForm(mixed $form): void
    {
        $this->form = $form;
    }

    public function exposeForm(): mixed
    {
        return $this->form;
    }

    public function forceAdminSection(string $section): void
    {
        $this->adminSection = $section;
    }

    public function exposeRequireLoginFlag(): bool
    {
        return $this->requireLogin;
    }

    public function exposePageUrl(): mixed
    {
        return $this->pageUrl;
    }

    public function exposeHandled(): bool
    {
        return $this->handled;
    }
}

/**
 * Variant overriding the preHandle() lifecycle hook to record that setForm()
 * invoked it (setForm() now always calls preHandle() before building the form).
 *
 * @internal
 */
class PreHandleCoverageController extends CoverageController
{
    public bool $preHandleRan = false;

    public function preHandle(): void
    {
        $this->preHandleRan = true;
    }
}

/**
 * Recording form double covering the form-state predicates.
 *
 * @internal
 */
class ControllerFormDouble
{
    public function __construct(
        private readonly bool $submitted = false,
        private readonly bool $validated = false,
        private readonly bool $cancelled = false,
        private readonly mixed $data = null,
        private readonly string $displayHtml = '',
    ) {}

    public function is_submitted(): bool
    {
        return $this->submitted;
    }

    public function is_validated(): bool
    {
        return $this->validated;
    }

    public function is_cancelled(): bool
    {
        return $this->cancelled;
    }

    public function get_data(): mixed
    {
        return $this->data;
    }

    public function display(): void
    {
        echo $this->displayHtml;
    }
}

/**
 * Instantiable form class for the setForm(string) branch (new $form(null, $params)).
 *
 * @internal
 */
class DummyFormForController
{
    public function __construct(mixed $action = null, mixed $params = null) {}
}

/**
 * Minimal renderable for render()'s renderable+component branch.
 *
 * @internal
 */
class ControllerRenderable implements renderable {}
