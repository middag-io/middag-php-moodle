<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Support;

use Middag\Moodle\Http\Contract\RouterInterface;
use Middag\Moodle\Http\MoodleHttpKernel;
use Middag\Moodle\Runtime\Kernel;
use Middag\Moodle\Support\RouterBridgeSupport;
use Middag\Moodle\Support\VersionSupport;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionProperty;
use stdClass;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;

/**
 * @internal
 */
#[CoversClass(RouterBridgeSupport::class)]
final class RouterBridgeSupportCoverageTest extends TestCase
{
    private mixed $prevCfg;

    /** @var array<string, mixed> */
    private array $prevServer = [];

    protected function setUp(): void
    {
        $this->prevCfg = $GLOBALS['CFG'] ?? null;
        $this->prevServer = $_SERVER;
        $GLOBALS['CFG'] = (object) ['wwwroot' => 'https://moodle.test'];

        Kernel::shutdown();
    }

    protected function tearDown(): void
    {
        Kernel::shutdown();

        $GLOBALS['CFG'] = $this->prevCfg;
        $_SERVER = $this->prevServer;

        // http_response_code() is process-global; restore the benign default so
        // the success-path test never leaks a 404 into a later test's read.
        http_response_code(200);

        (new ReflectionProperty(VersionSupport::class, 'bootstrapped'))->setValue(null, false);
    }

    #[Test]
    public function testIsAvailableReturnsTrueOnMoodle51(): void
    {
        $this->setMoodleBranch(501);

        self::assertTrue(RouterBridgeSupport::isAvailable());
    }

    #[Test]
    public function testIsAvailableReturnsFalseBeforeMoodle51(): void
    {
        $this->setMoodleBranch(500);

        self::assertFalse(RouterBridgeSupport::isAvailable());
    }

    #[Test]
    public function testRegisterIsNoopWhenRouterUnavailable(): void
    {
        $this->setMoodleBranch(500);

        self::assertFalse(RouterBridgeSupport::isAvailable());
        self::assertNull(RouterBridgeSupport::register());
    }

    #[Test]
    public function testRegisterIsNoopWhenRouterAvailable(): void
    {
        $this->setMoodleBranch(501);

        self::assertTrue(RouterBridgeSupport::isAvailable());
        self::assertNull(RouterBridgeSupport::register());
    }

    #[Test]
    public function testProxyRequestReturns500WhenKernelBootFails(): void
    {
        // With no product container builder registered, Kernel::handle() fails to
        // boot and throws — proxy_request() must catch it and return a 500 response.
        $response = $this->makeResponse();
        $level = ob_get_level();

        $result = RouterBridgeSupport::proxyRequest(new stdClass(), $response, 'ping');

        // proxy_request opens an output buffer before Kernel::handle throws; drop it.
        while (ob_get_level() > $level) {
            ob_end_clean();
        }

        self::assertSame(500, $response->status);
        self::assertSame('application/json', $response->headers['Content-Type']);
        self::assertStringContainsString('Internal framework error', $response->body);
        self::assertSame($response, $result);
    }

    #[Test]
    public function testProxyRequestReturnsResponseWhenKernelHandlesSuccessfully(): void
    {
        // Request::createFromGlobals() reads $_SERVER; without a host the PSR-7
        // bridge builds "http://:/" and Nyholm rejects it. Populate a valid host
        // so Kernel::handle() runs its full dispatch instead of throwing early.
        $_SERVER['HTTP_HOST'] = 'moodle.test';
        $_SERVER['SERVER_NAME'] = 'moodle.test';
        $_SERVER['SERVER_PORT'] = '80';
        $_SERVER['REQUEST_URI'] = '/local/middag/index.php/api/ping';
        $_SERVER['SCRIPT_NAME'] = '/local/middag/index.php';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $this->bootInjectedKernel();

        // Baseline the SAPI status so http_response_code() has a defined prior read.
        http_response_code(200);

        $response = $this->makeResponse();
        $level = ob_get_level();

        $result = RouterBridgeSupport::proxyRequest(new stdClass(), $response, 'ping');

        while (ob_get_level() > $level) {
            ob_end_clean();
        }

        // The kernel matches against an empty route collection, so its HttpKernel
        // renders a 404 ("Route not found"). Capturing that emitted body proves
        // proxy_request took the SUCCESS branch (ob_start → Kernel::handle →
        // getBody()->write), not the catch branch (which writes "Internal
        // framework error").
        self::assertSame($response, $result);
        self::assertSame('application/json', $response->headers['Content-Type']);
        self::assertStringContainsString('Route not found', $response->body);
        self::assertContains($response->status, [200, 404]);
    }

    #[Test]
    public function testGetOpenapiJsonUrlUsesWwwroot(): void
    {
        self::assertSame(
            'https://moodle.test/local/example/index.php/api/openapi.json',
            RouterBridgeSupport::getOpenapiJsonUrl(),
        );
    }

    #[Test]
    public function testGetOpenapiYamlUrlUsesWwwroot(): void
    {
        self::assertSame(
            'https://moodle.test/local/example/index.php/api/openapi.yaml',
            RouterBridgeSupport::getOpenapiYamlUrl(),
        );
    }

    /**
     * Inject a pre-booted Kernel singleton wired to a real (empty-route)
     * MoodleHttpKernel, so Kernel::handle() dispatches without a Moodle runtime.
     *
     * The httpKernel property is typed to the final MoodleHttpKernel (not
     * mockable), so a genuine instance over an empty RouteCollection is used: any
     * path resolves to the framework's rendered 404 response — enough to exercise
     * proxy_request()'s success branch end to end.
     */
    private function bootInjectedKernel(): void
    {
        $psr17 = new Psr17Factory();
        $httpKernel = new MoodleHttpKernel(
            new ContainerBuilder(),
            new RouteCollection(),
            new RequestContext(),
            new HttpFoundationFactory(),
            new PsrHttpFactory($psr17, $psr17, $psr17, $psr17),
        );

        $router = new class implements RouterInterface {
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
                return '';
            }
        };

        $reflection = new ReflectionClass(Kernel::class);
        $kernel = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('booted')->setValue($kernel, true);
        $reflection->getProperty('router')->setValue($kernel, $router);
        $reflection->getProperty('httpKernel')->setValue($kernel, $httpKernel);
        $reflection->getProperty('instance')->setValue(null, $kernel);
    }

    private function makeResponse(): object
    {
        return new class {
            public string $body = '';

            /** @var array<string, string> */
            public array $headers = [];

            public int $status = 0;

            public function getBody(): object
            {
                return new class($this) {
                    public function __construct(private readonly object $parent) {}

                    public function write($string): int
                    {
                        $this->parent->body .= (string) $string;

                        return \strlen((string) $string);
                    }
                };
            }

            public function withHeader(string $name, string $value): static
            {
                $this->headers[$name] = $value;

                return $this;
            }

            public function withStatus(int $code, string $reasonPhrase = ''): static
            {
                $this->status = $code;

                return $this;
            }
        };
    }

    private function setMoodleBranch(int $branch): void
    {
        (new ReflectionProperty(VersionSupport::class, 'bootstrapped'))->setValue(null, false);
        $GLOBALS['CFG'] = (object) ['branch' => $branch, 'wwwroot' => 'https://moodle.test'];
    }
}
