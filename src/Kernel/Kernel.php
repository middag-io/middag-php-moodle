<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Kernel;

use core\component as core_component;
use Middag\Framework\Kernel\Contract\KernelInterface;
use Middag\Framework\Kernel\HostContext;
use Middag\Moodle\Config\ComponentContext;
use Middag\Moodle\Http\Contract\RouterInterface;
use Middag\Moodle\Http\Inertia\InertiaSharedProps;
use Middag\Moodle\Http\Inertia\MoodleInertiaBootstrap;
use Middag\Moodle\Http\MoodleHttpKernel;
use Middag\Moodle\Http\Routing\MoodleRouter;
use Middag\Moodle\Shared\Util\Debug;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RequestContext;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Throwable;

/**
 * Application Kernel.
 *
 * The central orchestrator for the plugin. It manages the lifecycle of the
 * Dependency Injection (DI) Container, initialization of loaders, and the
 * booting process of the modular architecture.
 *
 * Architecture Note:
 * This class implements the Singleton pattern intentionally to bridge Moodle's
 * procedural global execution flow with the plugin's Object-Oriented Domain.
 * It ensures the massive ContainerBuilder is compiled exactly once per request.
 *
 * @internal
 *
 * @see KernelInterface
 */
class Kernel implements KernelInterface
{
    /** @var null|self Singleton instance */
    private static ?self $instance = null;

    /** @var null|ContainerInterface The global dependency injection container */
    private ?ContainerInterface $container = null;

    /** @var null|MoodleHttpKernel The handler for the HTTP Request/Response cycle */
    private ?MoodleHttpKernel $httpKernel = null;

    /** @var null|MoodleRouter The router service for routing configuration */
    private ?RouterInterface $router = null;

    /** @var bool Flag indicating if the kernel has successfully finished booting */
    private bool $booted = false;

    /** @var array<string, object> Runtime overrides for services (swap support for testing/facades) */
    private array $runtimeInstances = [];

    /**
     * Registry of extensions that failed during boot, keyed by slug (ADR-609).
     *
     * @var array<string, array{exception: Throwable, distribution: string, message: string, timestamp: int}>
     */
    private static array $failedExtensions = [];

    /**
     * Private constructor to enforce Singleton pattern.
     */
    private function __construct() {}

    /**
     * Boot the kernel and initialize the container.
     *
     * This method is idempotent: calling it multiple times has no effect
     * unless shutdown() is explicitly called.
     */
    public static function init(): void
    {
        if (self::$instance instanceof self && self::$instance->booted) {
            return;
        }

        // Reset any failed previous attempt to avoid stale singleton state.
        if (self::$instance instanceof self) {
            ContainerFactory::reset();
        }

        self::$instance = new self();
        self::$instance->boot();
    }

    /**
     * Reset the kernel state.
     *
     * Essential for PHPUnit isolation to prevent state leakage between tests.
     */
    public static function shutdown(): void
    {
        if (!self::$instance instanceof self) {
            return;
        }

        self::$instance->runtimeInstances = [];
        self::$instance->booted = false;

        self::$failedExtensions = [];

        // Reset the container factory state as well
        ContainerFactory::reset();

        self::$instance = null;
    }

    /**
     * Register an extension that failed during boot (ADR-609).
     *
     * Called by loaders when a THIRD_PARTY/CUSTOM extension throws during
     * bootstrap or boot. Native/Pro extensions are not registered here
     * because their failures propagate immediately as fatal errors.
     */
    public static function registerFailedExtension(string $slug, Throwable $exception, string $distribution): void
    {
        self::$failedExtensions[$slug] = [
            'exception' => $exception,
            'distribution' => $distribution,
            'message' => $exception->getMessage(),
            'timestamp' => time(),
        ];
    }

    /**
     * Returns all extensions that failed during boot.
     *
     * @return array<string, array{exception: Throwable, distribution: string, message: string, timestamp: int}>
     */
    public static function failedExtensions(): array
    {
        return self::$failedExtensions;
    }

    /**
     * Instance proxy onto the static failed-module registry.
     */
    public function register(string $slug, Throwable $exception, string $distribution): void
    {
        self::registerFailedExtension($slug, $exception, $distribution);
    }

    public function has(string $slug): bool
    {
        return array_key_exists($slug, self::$failedExtensions);
    }

    /**
     * @return array<string, array{exception: Throwable, distribution: string, message: string, timestamp: int}>
     */
    public function all(): array
    {
        return self::$failedExtensions;
    }

    /**
     * Sets the internal container instance manually.
     *
     * @param ContainerInterface $container
     *
     * @internal used strictly by the ContainerFactory during the build process
     */
    public function setInternalContainer(ContainerInterface $container): void
    {
        $this->container = $container;
    }

    /**
     * Access the Booted Container.
     *
     * @return ContainerInterface
     *
     * @throws RuntimeException if accessed before booting logic completes
     */
    public static function container(): ContainerInterface
    {
        self::ensureBooted();

        if (self::$instance->container === null) {
            throw new RuntimeException('Kernel booted but container is null. Critical initialization failure.');
        }

        return self::$instance->container;
    }

    /**
     * Retrieve a service from the container (Facade/Service Locator pattern).
     *
     * WARNING: Prefer dependency injection (Constructor Injection) over using this method.
     * This method is primarily for use in legacy Moodle files (lib.php, version.php),
     * Hooks, or mustache templates contexts.
     *
     * @template T
     *
     * @param class-string<T>|string $id the service identifier or class name
     *
     * @throws RuntimeException if the service cannot be resolved
     */
    public static function get(string $id): object
    {
        self::ensureBooted();

        // 1. Check for runtime swapped instance (Testing/Mocking)
        if (isset(self::$instance->runtimeInstances[$id])) {
            // @var T
            return self::$instance->runtimeInstances[$id];
        }

        // 2. Container lookup
        try {
            // @var T $service
            return self::container()->get($id);
        } catch (NotFoundExceptionInterface $e) {
            throw new RuntimeException('Service not found in container: ' . $id, 0, $e);
        } catch (ContainerExceptionInterface $e) {
            throw new RuntimeException(sprintf('Error resolving service %s: ', $id) . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Runtime override of a container entry.
     *
     * Allows Facades (or tests) to replace services even after
     * the container is compiled.
     *
     * @param string $id       service ID
     * @param object $instance the instance to use
     */
    public static function instance(string $id, object $instance): void
    {
        if (!self::$instance instanceof self) {
            self::init();
        }
        self::$instance->runtimeInstances[$id] = $instance;
    }

    /**
     * Dispatch the current HTTP request to the appropriate controller.
     *
     * External plugins that delegate routing to MIDDAG pass their own base URL
     * so the UrlMatcher resolves pathInfo correctly for their entry point.
     *
     * @param null|string $base_url Override the base URL (e.g. '/local/yourplugin/index.php').
     *                              Null uses the default MIDDAG entry point.
     *
     * @throws RuntimeException
     */
    public static function handle(?string $base_url = null): void
    {
        self::ensureBooted();

        if (!self::$instance->httpKernel || !self::$instance->router) {
            throw new RuntimeException('Kernel components (HttpKernel or Router) not initialized correctly.');
        }

        $context = self::$instance->router->getContext();

        if ($base_url !== null) {
            $context->setBaseUrl($base_url);
        }

        // Build the PSR-7 ServerRequest from PHP globals and hand it to the
        // framework HttpKernel (PSR-15 boundary). The kernel converts it back to
        // an HttpFoundation request internally. Mirrors Router::initializeContext,
        // which also builds the Symfony request from globals.
        $psr17 = new Psr17Factory();
        $psrFactory = new PsrHttpFactory($psr17, $psr17, $psr17, $psr17);

        $response = self::$instance->httpKernel->handle(
            $psrFactory->createRequest(Request::createFromGlobals())
        );

        self::emitResponse($response);
    }

    /**
     * Dispatch an event or signal through the framework's event dispatcher.
     *
     * @param object $event
     *
     * @return object
     */
    public static function dispatch(object $event): object
    {
        self::ensureBooted();

        try {
            /** @var EventDispatcherInterface $dispatcher */
            $dispatcher = self::get(EventDispatcherInterface::class);

            return $dispatcher->dispatch($event);
        } catch (Throwable $throwable) {
            // Re-throw or log based on criticality. ADR-701 (reactive model) + ADR-702 (failure isolation).
            throw new RuntimeException('Dispatch failed: ' . $throwable->getMessage(), 0, $throwable);
        }
    }

    /**
     * Absolute directory of the host plugin (the consumer component) on this
     * Moodle installation, resolved through Moodle's component registry.
     *
     * This is the supported way to locate host resources (facade/, extensions/,
     * lib.php, ...) — a package-relative path would point inside vendor/ in the
     * Composer layout, so no such constant exists.
     *
     * Unit tests stub {@see core_component} (tests/bootstrap.php) and drive the
     * result via {@code $GLOBALS['__middag_test_component_dir']}; that stub is
     * the only supported non-Moodle resolution path.
     *
     * @throws RuntimeException when Moodle cannot resolve the configured
     *                          component to an existing directory
     */
    public static function hostDirectory(): string
    {
        $component = ComponentContext::name();

        /** @var null|string $directory moodle-stubs type the return as string, but the real API yields null for an unknown/uninstalled component */
        $directory = core_component::get_component_directory($component);

        if ($directory === null || $directory === '' || !is_dir($directory)) {
            throw new RuntimeException(sprintf(
                'Cannot resolve the host directory for component "%s": Moodle\'s component registry returned no existing directory.',
                $component,
            ));
        }

        return $directory;
    }

    /**
     * Access the Router to register routes manually or inspect current routes.
     *
     * @return RouterInterface
     */
    public static function routing(): RouterInterface
    {
        self::ensureBooted();

        if (!self::$instance->router) {
            throw new RuntimeException('Router not initialized.');
        }

        return self::$instance->router;
    }

    /**
     * Emit a PSR-7 response to the SAPI (status line, headers, body).
     *
     * The framework HttpKernel returns a Response; without emitting it here, the
     * plugin's PSR-15 routes (Inertia pages, JSON endpoints, redirects) produce a
     * blank page — controllers return a Response instead of echoing, so nothing
     * reaches the browser. Header emission is guarded by headers_sent() so it is
     * a no-op if Moodle already flushed output.
     */
    private static function emitResponse(ResponseInterface $response): void
    {
        if (!headers_sent()) {
            http_response_code($response->getStatusCode());

            foreach ($response->getHeaders() as $name => $values) {
                foreach ($values as $value) {
                    header(sprintf('%s: %s', $name, $value), false);
                }
            }
        }

        $body = (string) $response->getBody();

        if ($body !== '') {
            echo $body;
        }
    }

    /**
     * Main Boot Process.
     *
     * Delegates container construction to the ContainerFactory,
     * then wires the HTTP kernel and marks the kernel as booted.
     */
    private function boot(): void
    {
        // Guard against reentrant boot (ensure_booted -> init -> boot -> ... -> ensure_booted)
        if ($this->booted) {
            return;
        }
        // Mark booting-in-progress to prevent reentrant calls via ensure_booted().
        $this->booted = true;

        try {
            $this->ensureAutoload();

            // 1. Initialize core components
            $this->router = new MoodleRouter();

            // 2. Build and compile the container using the dedicated factory
            // We pass $this (Kernel) to inject it into the container itself
            $this->container = ContainerFactory::getInstance($this, $this->router);

            // 3. Initialize HTTP Kernel (PSR-15 boundary; HttpFoundation bridged internally).
            $this->router->initializeContext();
            $psr17 = new Psr17Factory();
            $this->httpKernel = new MoodleHttpKernel(
                $this->container,
                $this->router->getRoutes(),
                $this->router->getContext() ?? new RequestContext(),
                new HttpFoundationFactory(),
                new PsrHttpFactory($psr17, $psr17, $psr17, $psr17),
            );

            // 4. Register the neutral host context (MOODLE-01). Adapter helpers
            //    and the Inertia runtime resolve the running component's identity,
            //    asset version, and base path through HostContext instead of
            //    hard-coding a consumer plugin. Set before the Inertia wiring below
            //    so it is available to any reader.
            HostContext::set(MoodleHostContext::resolve());

            // 5. Wire platform-agnostic Inertia runtime (framework) to Moodle host
            //    (PD-008 C): AMD module, bundle path, URL generator, HTML bootstrap.
            MoodleInertiaBootstrap::registerHooks($this->router);

            // 6. Register Inertia shared props (navigation, auth, theme, scope, flash).
            InertiaSharedProps::register();
        } catch (Throwable $throwable) {
            $this->booted = false;
            $this->handleBootError($throwable);
        }
    }

    /**
     * Ensures the kernel is initialized before access.
     */
    private static function ensureBooted(): void
    {
        // IMPORTANT: must check ->booted, NOT ->container. Checking container causes
        // infinite recursion because container is null during boot().
        if (!self::$instance instanceof self || !self::$instance->booted) {
            self::init();
        }
    }

    /**
     * Loads the consumer plugin's autoloader if not already registered.
     *
     * In web flows Moodle has already included the plugin's lib.php; in CLI or
     * early-boot contexts it is resolved through the host component directory
     * ({@see self::hostDirectory()}). The legacy package-relative fallbacks
     * (__DIR__-based lib.php and externallib/autoload.php) never resolved in
     * the Composer vendor/ layout and were removed.
     */
    private function ensureAutoload(): void
    {
        $autoload_fn = ComponentContext::autoloadFunction();

        if (!function_exists($autoload_fn)) {
            try {
                $consumer_lib = self::hostDirectory() . '/lib.php';
            } catch (RuntimeException) {
                // Best-effort: an unresolvable host directory is not fatal here —
                // the Composer autoloader may already serve the classes.
                $consumer_lib = null;
            }

            if ($consumer_lib !== null && is_file($consumer_lib)) {
                require_once $consumer_lib;
            }
        }

        if (function_exists($autoload_fn)) {
            $autoload_fn();
        }
    }

    /**
     * Handles fatal errors during the boot process.
     * Prevents "White Screen of Death" by logging appropriately.
     *
     * @param Throwable $throwable
     *
     * @noinspection ForgottenDebugOutputInspection
     */
    private function handleBootError(Throwable $throwable): void
    {
        // In CLI context, print the error to STDERR and terminate immediately.
        $this->reportCliFatalIfNeeded($throwable);

        // Trace through the adapter's Debug util (always present in this package).
        Debug::traceException($throwable);

        // Always rethrow so the caller sees the real boot error instead of
        // a misleading "container is null" from subsequent facade/service lookups.
        throw $throwable;
    }

    /**
     * In a CLI context, print the fatal boot error to STDERR and terminate the
     * process. Rethrowing there can segfault during Symfony DI teardown.
     *
     * @codeCoverageIgnore CLI-only path terminating via exit(); not exercisable under PHPUnit.
     *
     * @noinspection ForgottenDebugOutputInspection
     */
    private function reportCliFatalIfNeeded(Throwable $throwable): void
    {
        if (defined('CLI_SCRIPT') && CLI_SCRIPT) {
            fwrite(STDERR, '[MIDDAG KERNEL FATAL]: ' . $throwable->getMessage() . PHP_EOL);
            fwrite(STDERR, $throwable->getTraceAsString() . PHP_EOL);

            exit(1);
        }
    }
}
