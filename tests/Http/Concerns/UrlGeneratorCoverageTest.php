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

use Middag\Moodle\Http\Concerns\UrlGenerator;
use Middag\Moodle\Http\Contract\RouterInterface;
use Middag\Moodle\Kernel\Kernel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use TypeError;

/**
 * UrlGenerator is a static-helper trait that delegates URL generation to the
 * booted Kernel's router. The tests inject a pre-booted Kernel singleton wired
 * to a recording router (the real Kernel::routing() path, without a Moodle
 * runtime) and exercise the trait through an anonymous class that `use`s it.
 *
 * @internal
 */
#[CoversClass(UrlGenerator::class)]
final class UrlGeneratorCoverageTest extends TestCase
{
    protected function setUp(): void
    {
        Kernel::shutdown();
    }

    protected function tearDown(): void
    {
        // The injected singleton lives in a static property — drop it so the
        // next test (or suite) never sees this test's fake booted kernel.
        Kernel::shutdown();
    }

    #[Test]
    public function testUrlGeneratorReturnsTheRouterGeneratedUrl(): void
    {
        $router = $this->bootKernelWithRouter(
            static fn (): string => '/local/example/index.php/api/ping',
        );
        $subject = new class {
            use UrlGenerator;
        };

        self::assertSame('/local/example/index.php/api/ping', $subject::urlGenerator('api_ping'));

        // Default reference type is Symfony's ABSOLUTE_PATH; empty parameters
        // are forwarded verbatim.
        self::assertSame(
            [['route' => 'api_ping', 'parameters' => [], 'reference_type' => UrlGeneratorInterface::ABSOLUTE_PATH]],
            $router->calls,
        );
    }

    #[Test]
    public function testUrlGeneratorForwardsParametersAndReferenceType(): void
    {
        $router = $this->bootKernelWithRouter(
            static fn (): string => 'https://moodle.test/local/example/index.php/course/7',
        );
        $subject = new class {
            use UrlGenerator;
        };

        $result = $subject::urlGenerator('course_view', ['id' => 7], UrlGeneratorInterface::ABSOLUTE_URL);

        self::assertSame('https://moodle.test/local/example/index.php/course/7', $result);
        self::assertSame(
            [['route' => 'course_view', 'parameters' => ['id' => 7], 'reference_type' => UrlGeneratorInterface::ABSOLUTE_URL]],
            $router->calls,
        );
    }

    #[Test]
    public function testWebhookUrlGeneratorThrowsTypeErrorReturningAMoodleUrl(): void
    {
        // webhookUrlGenerator() declares a `string` return type under
        // strict_types, but returns UrlSupport::get(...) — a moodle_url object.
        // The return coercion is rejected with a TypeError (see sourceBug).
        $router = $this->bootKernelWithRouter(
            static fn (): string => 'https://moodle.test/local/example/index.php/api/hook',
        );
        $subject = new class {
            use UrlGenerator;
        };

        try {
            $subject::webhookUrlGenerator('api_hook');
            self::fail('Expected a TypeError from the string return type, none thrown.');
        } catch (TypeError $typeError) {
            self::assertStringContainsString('must be of type string', $typeError->getMessage());
        }

        // Proves the body executed up to the return: the inner urlGenerator()
        // delegated to the router (line 56) before the moodle_url produced on
        // line 58 tripped the string return type.
        self::assertSame(
            [['route' => 'api_hook', 'parameters' => [], 'reference_type' => UrlGeneratorInterface::ABSOLUTE_PATH]],
            $router->calls,
        );
    }

    /**
     * Inject a pre-booted Kernel singleton whose router records generateUrl()
     * calls and returns whatever $generate yields, then return that router for
     * assertions. Mirrors the reflection recipe in
     * tests/Support/RouterBridgeSupportCoverageTest.php::bootInjectedKernel.
     */
    private function bootKernelWithRouter(callable $generate): object
    {
        $router = new class($generate) implements RouterInterface {
            /** @var callable */
            private $generate;

            /** @var list<array{route: string, parameters: array<string, mixed>, reference_type: int}> */
            public array $calls = [];

            public function __construct(callable $generate)
            {
                $this->generate = $generate;
            }

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

            public function generateUrl(string $route, array $parameters = [], int $reference_type = 1): string
            {
                $this->calls[] = ['route' => $route, 'parameters' => $parameters, 'reference_type' => $reference_type];

                return ($this->generate)($route, $parameters, $reference_type);
            }
        };

        $reflection = new ReflectionClass(Kernel::class);
        $kernel = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('booted')->setValue($kernel, true);
        $reflection->getProperty('router')->setValue($kernel, $router);
        $reflection->getProperty('instance')->setValue(null, $kernel);

        return $router;
    }
}
