<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Kernel;

use Middag\Framework\Http\Inertia\InertiaManager;
use Middag\Framework\Kernel\HostContext;
use Middag\Moodle\Config\ComponentContext;
use Middag\Moodle\Http\Contract\RouterInterface;
use Middag\Moodle\Http\MoodleHttpKernel;
use Middag\Moodle\Kernel\ContainerFactory;
use Middag\Moodle\Kernel\Kernel;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;
use stdClass;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Throwable;

/**
 * Lifecycle coverage for the Kernel singleton.
 *
 * Two driving modes (per the kernel test recipe):
 *  (A) a pre-booted Kernel injected via reflection — exercises handle(), get(),
 *      dispatch(), routing(), container(), instance(), emitResponse() without a
 *      real boot; the singleton's collaborators (router, httpKernel, container)
 *      are set directly.
 *  (B) a real init()/boot() through the ContainerFactory::setBuilder() seam —
 *      exercises boot(), ensureAutoload(), ensureBooted() and the HostContext /
 *      Inertia wiring end to end. Every test resets the singleton, the container
 *      factory seam, the host context and the Inertia registry so no global
 *      state leaks (the suite runs failOnRisky).
 *
 * @internal
 */
#[CoversClass(Kernel::class)]
final class KernelCoverageTest extends TestCase
{
    private mixed $prevCfg = null;

    /** @var array<string, mixed> */
    private array $prevServer = [];

    /** @var list<string> */
    private array $tempDirs = [];

    protected function setUp(): void
    {
        $this->prevCfg = $GLOBALS['CFG'] ?? null;
        $this->prevServer = $_SERVER;

        $this->resetKernelWorld();

        // Default composition-root wiring for the boot seam: a bare container
        // builder is enough for boot() to succeed (the product's full wiring is
        // out of scope for the adapter).
        ContainerFactory::setBuilder(static fn (): ContainerBuilder => new ContainerBuilder());

        $GLOBALS['CFG'] = (object) [
            'dirroot' => sys_get_temp_dir(),
            'wwwroot' => 'https://moodle.test',
        ];

        // Request::createFromGlobals() (handle() + router init) rejects an empty
        // host when bridged to PSR-7; populate a valid SAPI environment.
        $_SERVER['HTTP_HOST'] = 'moodle.test';
        $_SERVER['SERVER_NAME'] = 'moodle.test';
        $_SERVER['SERVER_PORT'] = '80';
        $_SERVER['REQUEST_URI'] = '/local/middag/index.php/api/ping';
        $_SERVER['SCRIPT_NAME'] = '/local/middag/index.php';
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    protected function tearDown(): void
    {
        $this->resetKernelWorld();

        $GLOBALS['CFG'] = $this->prevCfg;
        $_SERVER = $this->prevServer;

        // http_response_code() is process-global; restore a benign default so a
        // status emitted by emitResponse() never leaks into a later test.
        http_response_code(200);

        foreach ($this->tempDirs as $dir) {
            $lib = $dir . '/lib.php';
            if (is_file($lib)) {
                unlink($lib);
            }
            if (is_dir($dir)) {
                rmdir($dir);
            }
        }
        $this->tempDirs = [];

        unset(
            $GLOBALS['__middag_test_component_dir'],
            $GLOBALS['__middag_test_local_example_autoload_calls'],
            $GLOBALS['__middag_test_kl_autoloada'],
        );
    }

    // --------------------------------------------------------------------- //
    // Static failed-extension registry + instance proxies                   //
    // --------------------------------------------------------------------- //

    #[Test]
    public function registerFailedExtensionRecordsMetadataUnderTheSlug(): void
    {
        $exception = new RuntimeException('boom');

        Kernel::registerFailedExtension('local_broken', $exception, 'THIRD_PARTY');

        $registry = Kernel::failedExtensions();

        self::assertArrayHasKey('local_broken', $registry);
        self::assertSame($exception, $registry['local_broken']['exception']);
        self::assertSame('THIRD_PARTY', $registry['local_broken']['distribution']);
        self::assertSame('boom', $registry['local_broken']['message']);
        self::assertIsInt($registry['local_broken']['timestamp']);
    }

    #[Test]
    public function instanceProxiesRegisterHasAndAllOntoTheStaticRegistry(): void
    {
        $kernel = (new ReflectionClass(Kernel::class))->newInstanceWithoutConstructor();
        $exception = new RuntimeException('nope');

        $kernel->register('mod_x', $exception, 'CUSTOM');

        self::assertTrue($kernel->has('mod_x'));
        self::assertFalse($kernel->has('mod_absent'));
        self::assertArrayHasKey('mod_x', $kernel->all());
        self::assertSame($exception, $kernel->all()['mod_x']['exception']);
    }

    // --------------------------------------------------------------------- //
    // setInternalContainer + container()                                    //
    // --------------------------------------------------------------------- //

    #[Test]
    public function containerReturnsTheContainerSetOnABootedKernel(): void
    {
        $container = $this->container([]);
        $kernel = $this->injectKernel();
        $kernel->setInternalContainer($container);

        self::assertSame($container, Kernel::container());
    }

    #[Test]
    public function containerThrowsWhenBootedButTheContainerIsNull(): void
    {
        $this->injectKernel();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('container is null');

        Kernel::container();
    }

    // --------------------------------------------------------------------- //
    // get()                                                                 //
    // --------------------------------------------------------------------- //

    #[Test]
    public function getReturnsARuntimeSwappedInstanceWithoutTouchingTheContainer(): void
    {
        $service = new stdClass();
        // Container that would throw if consulted, proving the runtime swap wins.
        $kernel = $this->injectKernel();
        $kernel->setInternalContainer($this->container([]));

        Kernel::instance('svc.swapped', $service);

        self::assertSame($service, Kernel::get('svc.swapped'));
    }

    #[Test]
    public function getResolvesAServiceFromTheContainer(): void
    {
        $service = new stdClass();
        $kernel = $this->injectKernel();
        $kernel->setInternalContainer($this->container(['svc.id' => $service]));

        self::assertSame($service, Kernel::get('svc.id'));
    }

    #[Test]
    public function getWrapsAContainerNotFoundInARuntimeException(): void
    {
        $kernel = $this->injectKernel();
        $kernel->setInternalContainer($this->container([]));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Service not found in container: missing.id');

        Kernel::get('missing.id');
    }

    #[Test]
    public function getWrapsAGenericContainerExceptionInARuntimeException(): void
    {
        $failure = new class('resolve blew up') extends RuntimeException implements ContainerExceptionInterface {};
        $kernel = $this->injectKernel();
        $kernel->setInternalContainer($this->container(['bad.id' => $failure]));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error resolving service bad.id: resolve blew up');

        Kernel::get('bad.id');
    }

    // --------------------------------------------------------------------- //
    // instance()                                                            //
    // --------------------------------------------------------------------- //

    #[Test]
    public function instanceReusesTheExistingInstanceWithoutReinitialising(): void
    {
        $kernel = $this->injectKernel();
        $service = new stdClass();

        Kernel::instance('svc.reuse', $service);

        // Same singleton object — no re-init happened.
        self::assertSame($kernel, $this->currentInstance());
        self::assertSame($service, Kernel::get('svc.reuse'));
    }

    #[Test]
    public function instanceAutoInitialisesTheKernelWhenNoneExists(): void
    {
        self::assertNull($this->currentInstance());
        $service = new stdClass();

        Kernel::instance('svc.autoinit', $service);

        // A booted singleton was created on demand, and the swap is resolvable.
        self::assertInstanceOf(Kernel::class, $this->currentInstance());
        self::assertSame($service, Kernel::get('svc.autoinit'));
    }

    // --------------------------------------------------------------------- //
    // dispatch()                                                            //
    // --------------------------------------------------------------------- //

    #[Test]
    public function dispatchReturnsTheDispatcherResult(): void
    {
        $event = new stdClass();
        $dispatcher = new class implements EventDispatcherInterface {
            public function dispatch(object $event, ?string $eventName = null): object
            {
                return $event;
            }
        };
        $kernel = $this->injectKernel();
        $kernel->setInternalContainer($this->container([EventDispatcherInterface::class => $dispatcher]));

        self::assertSame($event, Kernel::dispatch($event));
    }

    #[Test]
    public function dispatchWrapsAThrowableInARuntimeException(): void
    {
        $dispatcher = new class implements EventDispatcherInterface {
            public function dispatch(object $event, ?string $eventName = null): object
            {
                throw new RuntimeException('dispatch exploded');
            }
        };
        $kernel = $this->injectKernel();
        $kernel->setInternalContainer($this->container([EventDispatcherInterface::class => $dispatcher]));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Dispatch failed: dispatch exploded');

        Kernel::dispatch(new stdClass());
    }

    // --------------------------------------------------------------------- //
    // routing()                                                             //
    // --------------------------------------------------------------------- //

    #[Test]
    public function routingReturnsTheRouterWhenBooted(): void
    {
        $router = $this->stubRouter();
        $this->injectKernel($router);

        self::assertSame($router, Kernel::routing());
    }

    #[Test]
    public function routingThrowsWhenTheRouterIsNull(): void
    {
        $this->injectKernel();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Router not initialized');

        Kernel::routing();
    }

    // --------------------------------------------------------------------- //
    // handle()                                                              //
    // --------------------------------------------------------------------- //

    #[Test]
    public function handleThrowsWhenTheHttpKernelIsNotInitialised(): void
    {
        // Router present, httpKernel null — the guard must fire.
        $this->injectKernel($this->stubRouter());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('HttpKernel or Router');

        Kernel::handle();
    }

    #[Test]
    public function handleDispatchesTheRequestAndEmitsTheResponseWithTheDefaultBaseUrl(): void
    {
        $this->injectKernel($this->stubRouter(), $this->realHttpKernel());

        ob_start();
        Kernel::handle();
        $output = ob_get_clean();

        // Empty route collection => the framework kernel renders its 404 body,
        // which emitResponse() echoes to the SAPI.
        self::assertStringContainsString('Route not found', (string) $output);
    }

    #[Test]
    public function handleAppliesAProvidedBaseUrlToTheRoutingContext(): void
    {
        $context = new RequestContext();
        $this->injectKernel($this->stubRouter($context), $this->realHttpKernel());

        ob_start();
        Kernel::handle('/local/custom/index.php');
        $output = ob_get_clean();

        self::assertSame('/local/custom/index.php', $context->getBaseUrl());
        self::assertStringContainsString('Route not found', (string) $output);
    }

    // --------------------------------------------------------------------- //
    // emitResponse() (private static, exercised via reflection)             //
    // --------------------------------------------------------------------- //

    #[Test]
    public function emitResponseEchoesANonEmptyBody(): void
    {
        $psr17 = new Psr17Factory();
        $response = $psr17->createResponse(201)
            ->withHeader('X-Test', 'yes')
            ->withBody($psr17->createStream('emitted-body'));

        ob_start();
        $this->emitResponse($response);
        $output = ob_get_clean();

        self::assertSame('emitted-body', $output);
    }

    #[Test]
    public function emitResponseWritesNothingForAnEmptyBody(): void
    {
        $response = (new Psr17Factory())->createResponse(204);

        ob_start();
        $this->emitResponse($response);
        $output = ob_get_clean();

        self::assertSame('', $output);
    }

    // --------------------------------------------------------------------- //
    // shutdown()                                                            //
    // --------------------------------------------------------------------- //

    #[Test]
    public function shutdownIsANoopWhenNoInstanceExists(): void
    {
        self::assertNull($this->currentInstance());

        Kernel::shutdown();

        self::assertNull($this->currentInstance());
    }

    #[Test]
    public function shutdownClearsTheInstanceAndTheFailedExtensionRegistry(): void
    {
        $this->injectKernel();
        Kernel::registerFailedExtension('mod_gone', new RuntimeException('x'), 'CUSTOM');
        self::assertNotSame([], Kernel::failedExtensions());

        Kernel::shutdown();

        self::assertNull($this->currentInstance());
        self::assertSame([], Kernel::failedExtensions());
    }

    // --------------------------------------------------------------------- //
    // init() / boot() / ensureBooted()                                      //
    // --------------------------------------------------------------------- //

    #[Test]
    public function initIsIdempotentOnceBooted(): void
    {
        Kernel::init();
        $first = $this->currentInstance();
        self::assertInstanceOf(Kernel::class, $first);

        Kernel::init();

        // The early-return kept the same singleton object.
        self::assertSame($first, $this->currentInstance());
    }

    #[Test]
    public function bootInvokesTheAlreadyRegisteredHostAutoloadFunction(): void
    {
        $GLOBALS['__middag_test_local_example_autoload_calls'] = 0;

        Kernel::init();

        self::assertGreaterThanOrEqual(1, $GLOBALS['__middag_test_local_example_autoload_calls']);
        self::assertInstanceOf(ContainerInterface::class, Kernel::container());
    }

    #[Test]
    public function containerAccessAutoBootsAnUnbootedKernel(): void
    {
        // No instance yet — ensureBooted() must trigger init()/boot().
        self::assertNull($this->currentInstance());

        $container = Kernel::container();

        self::assertInstanceOf(ContainerInterface::class, $container);
        self::assertInstanceOf(Kernel::class, $this->currentInstance());
    }

    #[Test]
    public function initResetsAFailedAttemptAndRebootsOnTheNextCall(): void
    {
        $calls = 0;
        ContainerFactory::setBuilder(static function () use (&$calls): ContainerBuilder {
            ++$calls;
            if ($calls === 1) {
                throw new RuntimeException('first-boot-fail');
            }

            return new ContainerBuilder();
        });

        // First init: boot() catches, handleBootError() (web branch) rethrows.
        try {
            Kernel::init();
            self::fail('The first init() must surface the boot failure.');
        } catch (RuntimeException $runtimeException) {
            self::assertStringContainsString('first-boot-fail', $runtimeException->getMessage());
        }

        // A non-booted singleton lingers from the failed attempt.
        self::assertInstanceOf(Kernel::class, $this->currentInstance());
        self::assertFalse($this->instanceIsBooted());

        // Second init: the lingering attempt is reset and boot() succeeds.
        Kernel::init();

        self::assertTrue($this->instanceIsBooted());
        self::assertInstanceOf(ContainerInterface::class, Kernel::container());
        self::assertSame(2, $calls);
    }

    // --------------------------------------------------------------------- //
    // ensureAutoload() branches (driven through real boots)                 //
    // --------------------------------------------------------------------- //

    #[Test]
    public function ensureAutoloadRequiresTheHostLibAndCallsTheResolvedAutoloader(): void
    {
        // A component whose autoload function is NOT yet defined; its lib.php
        // defines that function so ensureAutoload() require_once's then calls it.
        ComponentContext::configure('local_klautoloada', 'local_klautoloada_autoload');
        $GLOBALS['__middag_test_kl_autoloada'] = 0;

        $dir = $this->makeTempDir();
        file_put_contents(
            $dir . '/lib.php',
            "<?php if (!function_exists('local_klautoloada_autoload')) { function local_klautoloada_autoload() { \$GLOBALS['__middag_test_kl_autoloada'] = (\$GLOBALS['__middag_test_kl_autoloada'] ?? 0) + 1; } }",
        );
        $GLOBALS['__middag_test_component_dir'] = $dir;

        Kernel::init();

        self::assertTrue(function_exists('local_klautoloada_autoload'));
        self::assertSame(1, $GLOBALS['__middag_test_kl_autoloada']);
    }

    #[Test]
    public function ensureAutoloadToleratesAnUnresolvableHostDirectory(): void
    {
        // No component directory registered => hostDirectory() throws; the catch
        // leaves consumer_lib null and boot() still completes.
        ComponentContext::configure('local_klnodir', 'local_klnodir_autoload');
        unset($GLOBALS['__middag_test_component_dir']);

        Kernel::init();

        self::assertTrue($this->instanceIsBooted());
        self::assertFalse(function_exists('local_klnodir_autoload'));
    }

    #[Test]
    public function ensureAutoloadSkipsWhenTheHostLibIsAbsent(): void
    {
        // Host directory resolves but contains no lib.php; require is skipped.
        ComponentContext::configure('local_klnolib', 'local_klnolib_autoload');
        $GLOBALS['__middag_test_component_dir'] = $this->makeTempDir();

        Kernel::init();

        self::assertTrue($this->instanceIsBooted());
        self::assertFalse(function_exists('local_klnolib_autoload'));
    }

    #[Test]
    public function bootReturnsImmediatelyWhenAlreadyBooted(): void
    {
        // Reentrant-boot guard: a detached kernel already flagged booted must
        // return from the private boot() before constructing any collaborator,
        // so the container stays null (no router/httpKernel wiring happens).
        $reflection = new ReflectionClass(Kernel::class);
        $kernel = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('booted')->setValue($kernel, true);

        (new ReflectionMethod(Kernel::class, 'boot'))->invoke($kernel);

        self::assertTrue($reflection->getProperty('booted')->getValue($kernel));
        self::assertNull($reflection->getProperty('container')->getValue($kernel));
    }

    // --------------------------------------------------------------------- //
    // Helpers                                                               //
    // --------------------------------------------------------------------- //

    /**
     * Inject a pre-booted Kernel singleton wired with the given collaborators.
     */
    private function injectKernel(
        ?RouterInterface $router = null,
        ?MoodleHttpKernel $httpKernel = null,
    ): Kernel {
        $reflection = new ReflectionClass(Kernel::class);
        $kernel = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('booted')->setValue($kernel, true);

        if ($router instanceof RouterInterface) {
            $reflection->getProperty('router')->setValue($kernel, $router);
        }
        if ($httpKernel instanceof MoodleHttpKernel) {
            $reflection->getProperty('httpKernel')->setValue($kernel, $httpKernel);
        }

        $reflection->getProperty('instance')->setValue(null, $kernel);

        return $kernel;
    }

    /**
     * A genuine (final, non-mockable) MoodleHttpKernel over an empty route
     * collection: any path resolves to the framework's rendered 404 response.
     */
    private function realHttpKernel(): MoodleHttpKernel
    {
        $psr17 = new Psr17Factory();

        return new MoodleHttpKernel(
            new ContainerBuilder(),
            new RouteCollection(),
            new RequestContext(),
            new HttpFoundationFactory(),
            new PsrHttpFactory($psr17, $psr17, $psr17, $psr17),
        );
    }

    /**
     * A RouterInterface stand-in returning the given (or a fresh) context.
     */
    private function stubRouter(?RequestContext $context = null): RouterInterface
    {
        return new class($context ?? new RequestContext()) implements RouterInterface {
            public function __construct(private readonly RequestContext $context) {}

            public function initializeContext(): void {}

            public function getRoutes(): RouteCollection
            {
                return new RouteCollection();
            }

            public function getContext(): RequestContext
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
     * A PSR-11 container returning mapped services. A mapped Throwable is thrown
     * (to exercise the ContainerExceptionInterface branch); an unmapped id throws
     * a NotFoundExceptionInterface.
     *
     * @param array<string, mixed> $map
     */
    private function container(array $map): ContainerInterface
    {
        return new class($map) implements ContainerInterface {
            /** @param array<string, mixed> $map */
            public function __construct(private array $map) {}

            public function get(string $id)
            {
                if (!array_key_exists($id, $this->map)) {
                    throw new class('not found: ' . $id) extends RuntimeException implements NotFoundExceptionInterface {};
                }

                $value = $this->map[$id];
                if ($value instanceof Throwable) {
                    throw $value;
                }

                return $value;
            }

            public function has(string $id): bool
            {
                return array_key_exists($id, $this->map);
            }
        };
    }

    private function emitResponse(object $response): void
    {
        (new ReflectionMethod(Kernel::class, 'emitResponse'))->invoke(null, $response);
    }

    private function currentInstance(): ?Kernel
    {
        return (new ReflectionClass(Kernel::class))->getProperty('instance')->getValue();
    }

    private function instanceIsBooted(): bool
    {
        $instance = $this->currentInstance();
        if (!$instance instanceof Kernel) {
            return false;
        }

        return (bool) (new ReflectionClass(Kernel::class))->getProperty('booted')->getValue($instance);
    }

    private function makeTempDir(): string
    {
        $dir = sys_get_temp_dir() . '/mdl_kernel_' . uniqid('', true);
        mkdir($dir, 0o777, true);
        $this->tempDirs[] = $dir;

        return $dir;
    }

    /**
     * Reset every static seam the kernel touches so no state leaks between tests
     * (or into sibling suites): the singleton, the container-factory seam
     * (container/builder/reset callbacks), the failed-extension registry, the
     * host context, and the Inertia shared-props registry.
     */
    private function resetKernelWorld(): void
    {
        Kernel::shutdown();

        $factory = new ReflectionClass(ContainerFactory::class);
        $factory->setStaticPropertyValue('container', null);
        $factory->setStaticPropertyValue('builder', null);
        $factory->setStaticPropertyValue('resetCallbacks', []);

        (new ReflectionClass(Kernel::class))->setStaticPropertyValue('failedExtensions', []);

        HostContext::reset();
        InertiaManager::flush();

        // Restore the default composition-root component the bootstrap configures
        // (individual tests may reconfigure it to a scenario-specific component).
        ComponentContext::configure('local_example', 'local_example_autoload');
    }
}
