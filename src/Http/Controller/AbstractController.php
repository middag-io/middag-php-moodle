<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Http\Controller;

use core\context;
use core\exception\coding_exception;
use core\exception\moodle_exception;
use core\output\renderable;
use core\url as moodle_url;
use Exception;
use Middag\Framework\Exception\MiddagAuthorizationException;
use Middag\Framework\Form\Renderer\RendererRegistry;
use Middag\Framework\Http\Inertia\InertiaAdapter;
use Middag\Moodle\Config\ComponentContext;
use Middag\Moodle\Domain\Context\ContextLevel;
use Middag\Moodle\Http\Contract\MoodleControllerInterface;
use Middag\Moodle\Kernel\Kernel;
use Middag\Moodle\Output\Widget;
use Middag\Moodle\Security\Contract\AuthenticationInterface;
use Middag\Moodle\Security\Contract\CapabilityInterface;
use Middag\Moodle\Shared\Util\Debug;
use Middag\Moodle\Support\ContextSupport;
use Middag\Moodle\Support\LangSupport;
use Middag\Moodle\Support\OutputSupport;
use Middag\Moodle\Support\PageSupport;
use Middag\Moodle\Support\UrlSupport;
use Middag\Ui\Form\Contract\FormInterface;
use Middag\Ui\Shared\Enum\RenderTarget;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Base Controller.
 *
 * Provides common functionality for all controllers:
 * - Dependency Injection Container access.
 * - Moodle Page/Auth setup (inlined from former traits).
 * - Form handling.
 * - Response helpers (HTML, JSON, Redirects).
 *
 * @internal
 *
 * @see MoodleControllerInterface
 */
abstract class AbstractController implements MoodleControllerInterface
{
    // =========================================================================
    // Properties
    // =========================================================================

    protected ContainerInterface $container;

    protected ?Request $request = null;

    /** @var array<string, mixed> Query parameters */
    protected array $params = [];

    /** @var array<string, mixed> POST/JSON payload */
    protected array $payload = [];

    // --- Auth (from former interacts_with_auth) ---

    protected mixed $course = null;

    protected mixed $cm = null;

    protected bool $requireLogin = false;

    protected bool $requireSesskey = false;

    protected bool $requiredLogin = false;

    protected bool $handled = false;

    protected array $capabilities = [];

    protected ?ContextLevel $capabilityContextLevel = null;

    protected int $capabilityInstanceId = 0;

    // --- Forms (from former interacts_with_forms) ---

    protected object|string|null $form = null;

    protected mixed $formparams = null;

    // --- Page (from former interacts_with_page) ---

    /** @var null|context Stores the Moodle context */
    protected ?context $context = null;

    protected string $pageLayout = 'standard';

    protected string $pageTitle = 'Default title';

    protected string $pageHeading = 'Default heading';

    protected moodle_url|string|null $pageUrl = null;

    protected array $pageNavbar = [];

    protected string $adminSection = '';

    // =========================================================================
    // Container & Request
    // =========================================================================

    /**
     * Set the container for dependency injection.
     * Called automatically by the HttpKernel during resolution.
     *
     * @param ContainerInterface $container the service container
     */
    public function setContainer(ContainerInterface $container): void
    {
        $this->container = $container;
        $this->initializeRequest();
    }

    /**
     * Inject the current HTTP request instance.
     *
     * @param Request $request current request to hydrate controller state
     */
    public function setRequest(Request $request): void
    {
        $this->request = $request;
        $this->initializeRequest();
    }

    // =========================================================================
    // Lifecycle
    // =========================================================================

    /**
     * Lifecycle Hook: Execute common setup logic.
     * Handle the execution of the controller (login, permissions, page settings).
     *
     * @throws moodle_exception if login, sesskey, or capabilities checks fail
     */
    public function handle(): void
    {
        if ($this->handled) {
            return;
        }
        $this->handled = true;

        // 1. Resolve Context and Layout ($PAGE) - from interacts_with_page
        $this->resolveContext(); // Ensures context is set before auth checks

        // 2. Auth Guard & Authentication Checks - from interacts_with_auth
        $this->requireLogin();
        $this->checkCapabilities();

        // 3. Setup Moodle Page - from interacts_with_page
        $this->setupMoodlePage();
    }

    /**
     * Lifecycle hook run automatically by the kernel before the action.
     *
     * Subclasses should override it to configure auth and context centrally
     * (flags + $this->handle()). Page controllers using lazy auth (flags
     * configured inside the action) do not need to override it: handle() is
     * still triggered by render() at the right moment.
     */
    public function preHandle(): void
    {
        // Empty hook by default. Does not call handle() so it never interferes
        // with the lazy pattern of page controllers that configure auth in the action.
    }

    // =========================================================================
    // Auth (inlined from former interacts_with_auth)
    // =========================================================================

    /**
     * Set if login is required and set related options.
     */
    public function setRequireLogin(mixed $course = null, mixed $cm = null): void
    {
        $this->requireLogin = true;
        $this->course = $course;
        $this->cm = $cm;
    }

    /**
     * Define the requirement of sesskey validation for non-idempotent requests.
     */
    public function setRequireSesskey(bool $require = true): void
    {
        $this->requireSesskey = $require;
    }

    /**
     * Define the capabilities that the user must have.
     *
     * @param array<string>            $capabilities required capability names
     * @param null|ContextLevel|string $context      Moodle context level when relevant; widens
     *                                               the framework contract's `string $context`
     *                                               (platform-agnostic) to also accept a
     *                                               {@see ContextLevel}. Non-ContextLevel
     *                                               values are stored as null.
     */
    public function setRequireCapabilities(array $capabilities, mixed $context = null, int $instanceid = 0): void
    {
        $this->capabilities = $capabilities;
        $this->capabilityContextLevel = $context instanceof ContextLevel ? $context : null;
        $this->capabilityInstanceId = $instanceid;
    }

    // =========================================================================
    // Forms (inlined from former interacts_with_forms)
    // =========================================================================

    /**
     * Set the form for the controller to handle.
     *
     * @param object|string $form       Class string or instance
     * @param mixed         $formparams Parameters for the form constructor
     *
     * @throws coding_exception
     * @throws moodle_exception
     */
    public function setForm(object|string $form, mixed $formparams = null): void
    {
        if (is_string($form)) {
            if (!class_exists($form)) {
                throw new coding_exception(sprintf("The form class '%s' does not exist.", $form));
            }
        } elseif (!is_object($form)) {
            throw new coding_exception('Invalid form provided. Must be an object or a class string.');
        }

        if (method_exists($this, 'pre_handle')) {
            $this->preHandle();
        }

        $this->form = is_object($form) ? $form : new $form(null, $formparams);
        $this->formparams = $formparams;
    }

    // =========================================================================
    // Page (inlined from former interacts_with_page)
    // =========================================================================

    /**
     * Set the context for the controller.
     */
    public function setContext(?context $context = null): void
    {
        $this->context = $context ?? ContextSupport::system();
    }

    /**
     * Get the resolved context (defaults to system).
     */
    public function getContext(): context
    {
        return $this->context ?? ContextSupport::system();
    }

    /**
     * Set the page URL.
     */
    public function setPageUrl(moodle_url|string $url): void
    {
        $this->pageUrl = $url;
    }

    /**
     * Set Moodle page layout.
     */
    public function setPageLayout(string $layout): void
    {
        $this->pageLayout = $layout;
    }

    /**
     * Set page title.
     */
    public function setPageTitle(string $title): void
    {
        $this->pageTitle = $title;
    }

    /**
     * Set page heading.
     */
    public function setPageHeading(string $heading): void
    {
        $this->pageHeading = $heading;
    }

    /**
     * Add an item to the page navbar trail.
     */
    public function addPageNavbar(array|string $item): void
    {
        $this->pageNavbar[] = $item;
    }

    /**
     * Helper to set URL from route name.
     */
    public function setUrlFromRoute(string $route, array $parameters = [], int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): void
    {
        try {
            $url = $this->urlGenerator($route, $parameters, $referenceType);
            $this->setPageUrl($url);
        } catch (Exception) {
            $this->pageUrl = null;
        }
    }

    /**
     * Ensure the user is logged in if required.
     */
    protected function requireLogin(): void
    {
        if ($this->requireLogin) {
            $this->authentication()->requireLogin(
                $this->course?->get_id(),
                true,
            );
            $this->requiredLogin = true;
        }

        if ($this->requireSesskey && $this->request instanceof Request) {
            $method = strtoupper($this->request->getMethod());
            if (!in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
                $this->authentication()->requireSesskey();
            }
        }
    }

    /**
     * Check if the user has the required capabilities.
     *
     * @throws MiddagAuthorizationException
     */
    protected function checkCapabilities(): void
    {
        $contextlevel = $this->capabilityContextLevel ?? ContextLevel::SYSTEM;

        foreach ($this->capabilities as $capability) {
            $this->capability()->authorize($capability, $contextlevel, $this->capabilityInstanceId);
        }
    }

    /**
     * Check if the bound form was submitted and validated.
     */
    protected function handleFormSubmission(): bool
    {
        return is_object($this->form) && $this->form->is_submitted() && $this->form->is_validated();
    }

    /**
     * Return submitted form data when valid, otherwise false.
     */
    protected function processFormSubmission(): mixed
    {
        if ($this->handleFormSubmission()) {
            return $this->form->get_data();
        }

        return false;
    }

    /**
     * Determine if the form has been cancelled.
     */
    protected function processFormCancel(): bool
    {
        if (is_object($this->form)) {
            return $this->form->is_cancelled();
        }

        return false;
    }

    /**
     * Check if the form was submitted (regardless of validation).
     */
    protected function isFormSubmitted(): bool
    {
        return is_object($this->form) && $this->form->is_submitted();
    }

    /**
     * Internal helper to render the form and return HTML.
     */
    protected function renderFormHtml(): string
    {
        if (empty($this->form)) {
            return '';
        }

        ob_start();
        if (is_object($this->form)) {
            $this->form->display();
        }

        return ob_get_clean();
    }

    /**
     * Render a FormInterface instance using the appropriate renderer adapter.
     *
     * Selects the renderer via RendererRegistry based on the given RenderTarget
     * (defaults to the controller's default target, which is HTML).
     * For the PROPS target, delegates to inertia() to produce a proper SPA response.
     * For the HTML target, wraps the rendered HTML in the standard Moodle page layout.
     *
     * @param FormInterface     $form   the hydrated (and optionally validated) form
     * @param null|RenderTarget $target override the default render target
     *
     * @return Response
     *
     * @throws moodle_exception
     */
    protected function renderForm(FormInterface $form, ?RenderTarget $target = null): Response
    {
        $target ??= $this->defaultRenderTarget();

        /** @var null|RendererRegistry $registry */
        $registry = $this->getService(RendererRegistry::class);

        if ($registry === null) {
            // Graceful degradation: RendererRegistry not registered in container.
            return new Response('', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $output = $registry->get($target)->render($form);

        if ($target === RenderTarget::PROPS) {
            return $this->inertia('FormPage', $output->props);
        }

        // HTML: wrap rendered HTML body in standard Moodle page layout.
        return $this->render($output->body);
    }

    /**
     * Returns the default render target for render_form().
     *
     * Subclasses may override this to change the default (e.g. PROPS for SPA controllers).
     */
    protected function defaultRenderTarget(): RenderTarget
    {
        return RenderTarget::HTML;
    }

    /**
     * Resolves the context if null, based on course/cm properties.
     */
    protected function resolveContext(): void
    {
        if (is_null($this->context)) {
            if (!empty($this->cm)) {
                $this->setContext(ContextSupport::module((int) $this->cm->id));
            } elseif (!empty($this->course)) {
                $this->setContext(ContextSupport::course($this->course->get_id()));
            } else {
                $this->setContext(ContextSupport::system());
            }
        }
    }

    /**
     * Apply all settings to the global $PAGE object.
     *
     * @throws coding_exception|moodle_exception
     */
    protected function setupMoodlePage(): void
    {
        $this->resolveContext();

        if ($this->adminSection !== '' && $this->adminSection !== '0') {
            PageSupport::adminExternalpageSetup($this->adminSection);
        }

        PageSupport::setContext($this->getContext());
        PageSupport::setPagelayout($this->pageLayout);
        PageSupport::setTitle($this->pageTitle);
        PageSupport::setHeading($this->pageHeading);
        PageSupport::setUrl($this->getPageUrl());

        foreach ($this->pageNavbar as $item) {
            if (is_array($item)) {
                PageSupport::navbarAdd($item[0] ?? '', $item[1] ?? null);
            } else {
                PageSupport::navbarAdd($item);
            }
        }

        if ($this->adminSection !== '' && $this->adminSection !== '0') {
            PageSupport::adminLoadNavigation($this->adminSection);
        }
    }

    /**
     * Get the Moodle page URL, resolving defaults when needed.
     *
     * @throws moodle_exception
     */
    protected function getPageUrl(): moodle_url
    {
        if ($this->pageUrl === null) {
            try {
                $this->setUrlFromRoute('index');
            } catch (Exception) {
                // Intentionally suppressed: 'index' route may not exist; falls through to other URL resolution.
            }
        }

        if (is_string($this->pageUrl) && ($this->pageUrl !== '' && $this->pageUrl !== '0')) {
            return UrlSupport::get($this->pageUrl);
        }

        if ($this->pageUrl instanceof moodle_url) {
            return $this->pageUrl;
        }

        return UrlSupport::home();
    }

    // =========================================================================
    // Response helpers
    // =========================================================================
    /**
     * Return a generic response based on the provided data and status code.
     *
     * @param mixed $data   Data to return
     * @param int   $status HTTP status code (default is 200)
     */
    protected function response(mixed $data, int $status = Response::HTTP_OK): JsonResponse|Response
    {
        if (is_array($data) || $this->isJson()) {
            return $this->jsonResponse($data, $status);
        }

        return new Response((string) $data, $status);
    }

    /**
     * Return a JSON response.
     *
     * @param mixed $data   Data to return
     * @param int   $status HTTP status code (default is 200)
     *
     * @return JsonResponse
     */
    protected function jsonResponse(mixed $data, int $status = Response::HTTP_OK): JsonResponse
    {
        return new JsonResponse($data, $status);
    }

    /**
     * Check if the request expects a JSON response.
     *
     * @return bool
     */
    protected function isJson(): bool
    {
        if (!$this->request instanceof Request) {
            return false;
        }

        if ($this->request->isXmlHttpRequest()) {
            return true;
        }

        $accept = (string) $this->request->headers->get('Accept', '');

        return str_contains($accept, 'application/json');
    }

    /**
     * Check if the incoming request is an Inertia navigation.
     */
    protected function isInertiaRequest(): bool
    {
        return $this->request instanceof Request && $this->request->headers->get('X-Inertia') === 'true';
    }

    /**
     * Render a Moodle view (HTML response) or widget.
     *
     * @param object|string $content   the content or widget to render
     * @param null|string   $component optional component name for rendering widgets
     *
     * @return Response
     *
     * @throws moodle_exception
     */
    protected function render(object|string $content = '', ?string $component = null): Response
    {
        // Ensure page is setup before rendering
        $this->handle(); // Process login, permissions, and set the page context

        // Output Moodle's page header and content.
        $output = OutputSupport::header(true);

        if ($content instanceof renderable && $component !== null) {
            $renderer = PageSupport::getRenderer($component);
            $output .= $renderer->render($content);
        } elseif ($content instanceof Response) {
            $output .= $content->getContent();
        } else {
            $output .= $content;
        }

        // Append any forms managed by the trait
        $output .= $this->renderFormHtml();

        $output .= OutputSupport::footer(true);

        return new Response($output);
    }

    /**
     * Render a widget using a Moodle renderer.
     *
     * @param string $vue_component
     * @param array  $props
     * @param string $component     The component to use with the renderer
     *
     * @return Response
     *
     * @throws moodle_exception
     */
    protected function renderFromWidget(string $vue_component, array $props = [], ?string $component = null): Response
    {
        $component ??= ComponentContext::name();

        return $this->render(new Widget($vue_component, $props, $component), $component);
    }

    /**
     * Generates an Inertia response for the specified component and props.
     *
     * @param string $component the name of the Inertia component to render
     * @param array  $props     an associative array of properties to pass to the component
     *
     * @return Response the generated response, either JSON for SPA requests or wrapped in Moodle layout for initial visits
     *
     * @throws moodle_exception
     */
    protected function inertia(string $component, array $props = []): Response
    {
        // Guarantees auth and page setup on all paths (initial visit and SPA).
        $this->handle();

        // Generate Inertia response (HTML or JSON)
        $response = InertiaAdapter::render($component, $props);

        // If Inertia SPA request: return JSON directly
        if ($response instanceof JsonResponse) {
            return $response;
        }

        // Otherwise, first visit → wrap in Moodle layout
        return $this->render($response);
    }

    /**
     * Generates an Inertia location response.
     *
     * Use for hard redirects to destinations outside the SPA (non-Inertia pages,
     * external URLs). Triggers a full browser load on the Inertia client.
     * For internal redirects after POST/PUT/DELETE, prefer `inertia_redirect()`.
     *
     * @param string $route  the name of the route to redirect or navigate to
     * @param array  $params an optional array of parameters to include with the route
     *
     * @return RedirectResponse|Response the generated Inertia location response
     */
    protected function inertiaLocation(string $route, array $params = []): RedirectResponse|Response
    {
        return InertiaAdapter::location($route, $params);
    }

    /**
     * Generates an Inertia-friendly redirect to another Inertia page (303 See Other).
     *
     * Use after POST/PUT/DELETE to redirect back to an Inertia page. The client
     * follows with GET and updates in place — no full browser reload.
     *
     * @param string $route  the name of the route to redirect to
     * @param array  $params an optional array of parameters to include with the route
     *
     * @return RedirectResponse 303 See Other pointing to the resolved route URL
     */
    protected function inertiaRedirect(string $route, array $params = []): RedirectResponse
    {
        return InertiaAdapter::redirect($route, $params);
    }

    /**
     * Render a renderable widget using a Moodle renderer component.
     *
     * @param renderable $widget    renderable instance to output
     * @param string     $component renderer component name (defaults to plugin component)
     *
     * @return Response
     *
     * @throws moodle_exception
     */
    protected function renderFromRenderer(renderable $widget, ?string $component = null): Response
    {
        return $this->render($widget, $component ?? ComponentContext::name());
    }

    /**
     * Render content using Moodle mustache templates.
     *
     * @param string $templatename    The template view. e.g. 'local_example/dashboard'
     * @param array  $templatecontext The context for the template
     *
     * @return Response
     *
     * @throws moodle_exception
     */
    protected function renderFromTemplate(string $templatename, array $templatecontext = []): Response
    {
        return $this->render(OutputSupport::renderFromTemplate($templatename, $templatecontext));
    }

    /**
     * Display an error page with a specific message and status code.
     *
     * @param string $message Error message
     * @param int    $status  HTTP status code (default is 400)
     *
     * @return Response
     *
     * @throws moodle_exception
     */
    protected function errorPage(string $message, int $status = Response::HTTP_BAD_REQUEST): Response
    {
        $this->setPageTitle(LangSupport::getString('error', 'error'));
        $this->setPageHeading(LangSupport::getString('error', 'error'));

        $this->handle(); // Process login, permissions, and set the page context

        // Render Moodle's standard error page.
        $content = OutputSupport::header();
        $content .= OutputSupport::notification($message, 'notifyproblem');

        if ($this->request && $debug_output = $this->request->get('debug_output')) {
            $content .= '<pre>' . s($debug_output) . '</pre>';
        }

        $content .= OutputSupport::footer();

        return new Response($content, $status);
    }

    /**
     * Generates a URL from the given parameters.
     *
     * @param string $route          route name registered in the router
     * @param array  $parameters     parameters to inject into the route placeholders
     * @param int    $reference_type URL reference type (absolute path or absolute URL)
     *
     * @return moodle_url
     *
     * @see UrlGeneratorInterface
     */
    protected function urlGenerator(string $route, array $parameters = [], int $reference_type = UrlGeneratorInterface::ABSOLUTE_PATH): moodle_url
    {
        // Router::generateUrl returns a string (RouterInterface contract). Wrap it
        // into a moodle_url for the controller helper's declared return type.
        return new moodle_url(Kernel::routing()->generateUrl($route, $parameters, $reference_type));
    }

    /**
     * Helper to perform a redirect.
     *
     * @param moodle_url|string $url
     * @param int               $status
     *
     * @return RedirectResponse
     */
    protected function redirect(moodle_url|string $url, int $status = Response::HTTP_FOUND): RedirectResponse
    {
        if ($url instanceof moodle_url) {
            $url = $url->out(false);
        }

        return new RedirectResponse($url, $status);
    }

    /**
     * Redirect to a named route with fallback support.
     *
     * @param string $route
     * @param array  $parameters
     * @param int    $status
     *
     * @return RedirectResponse
     */
    protected function redirectToRoute(string $route, array $parameters = [], int $status = Response::HTTP_FOUND): RedirectResponse
    {
        $try_routes = [$route, 'error', 'home'];

        foreach ($try_routes as $candidate) {
            try {
                // If route exists, this succeeds
                $url = $this->urlGenerator($candidate, $candidate === $route ? $parameters : []);

                return $this->redirect($url, $status);
            } catch (Exception) {
                // Intentionally suppressed: route may not exist; loop tries next fallback candidate.
            }
        }

        // Ultimate fallback if even 'error' route is missing
        return $this->redirect('/local/middag/index.php', $status);
    }

    // =========================================================================
    // Service resolution
    // =========================================================================

    /**
     * Retrieve a service from the container safely.
     *
     * @template T
     *
     * @param class-string<T>|string $service_name
     *
     * @return null|T the service instance or null if not found
     */
    protected function getService(string $service_name): mixed
    {
        try {
            if ($this->container->has($service_name)) {
                return $this->container->get($service_name);
            }
        } catch (ContainerExceptionInterface $containerException) {
            if (class_exists(Debug::class)) {
                Debug::traceException($containerException);
            }
        }

        return null;
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Resolve the authentication adapter from the container.
     */
    protected function authentication(): AuthenticationInterface
    {
        return $this->container->get(AuthenticationInterface::class);
    }

    /**
     * Resolve the capability adapter from the container.
     */
    private function capability(): CapabilityInterface
    {
        return $this->container->get(CapabilityInterface::class);
    }

    /**
     * Initialize request properties.
     */
    private function initializeRequest(): void
    {
        if (!$this->request instanceof Request) {
            $this->request = Request::createFromGlobals();
        }

        $this->params = $this->request->query->all();
        // Modern Symfony approach for payload (JSON/POST)
        $this->payload = $this->request->getPayload()->all();
    }
}
