<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Runtime\Facade;

use BadMethodCallException;
use Middag\Framework\Kernel\Contract\KernelInterface;
use Middag\Moodle\Runtime\Facade\AbstractFacade;
use Middag\Moodle\Runtime\Kernel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

/**
 * AbstractFacade is the static proxy layer that resolves a cached "root" object
 * from the Kernel container. It is abstract, so it is exercised through a
 * concrete anonymous subclass whose accessor points at a fixed service id.
 *
 * The Kernel singleton is injected pre-booted via reflection (container left
 * null, no real boot) so its runtime-instance seam serves the facade root
 * deterministically, and the container-null path drives the resolution-failure
 * branch. Both the facade static state and the Kernel singleton are reset in
 * tearDown to keep the shared statics from leaking across the suite.
 *
 * @internal
 */
#[CoversClass(AbstractFacade::class)]
final class AbstractFacadeCoverageTest extends TestCase
{
    /**
     * Service id the anonymous facade fixture resolves.
     */
    private const ACCESSOR = 'middag.tests.abstract_facade.root';

    protected function setUp(): void
    {
        AbstractFacade::reset();
        $this->injectBootedKernel();
    }

    protected function tearDown(): void
    {
        Kernel::shutdown();
        AbstractFacade::reset();
    }

    #[Test]
    public function testCallStaticInvokesTheExistingMethodOnTheRoot(): void
    {
        $root = new class {
            public function greet(string $name): string
            {
                return 'hello ' . $name;
            }
        };
        $this->registerRoot($root);
        $facade = $this->makeFacade();

        self::assertSame('hello Ada', $facade::greet('Ada'));
    }

    #[Test]
    public function testCallStaticDelegatesToMagicCallWhenTheMethodIsMissing(): void
    {
        $root = new class {
            /** @var array<int, array{0: string, 1: array}> */
            public array $magic = [];

            public function __call(string $name, array $args): string
            {
                $this->magic[] = [$name, $args];

                return 'magic:' . $name;
            }
        };
        $this->registerRoot($root);
        $facade = $this->makeFacade();

        self::assertSame('magic:doThing', $facade::doThing('a', 'b'));
        self::assertSame([['doThing', ['a', 'b']]], $root->magic);
    }

    #[Test]
    public function testCallStaticThrowsBadMethodCallWhenNoMethodAndNoMagicCall(): void
    {
        $root = new class {
            public function known(): void {}
        };
        $this->registerRoot($root);
        $facade = $this->makeFacade();

        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('Method missingMethod does not exist in');

        $facade::missingMethod();
    }

    #[Test]
    public function testGetFacadeRootResolvesTheInstanceFromTheKernel(): void
    {
        $root = new class {};
        $this->registerRoot($root);
        $facade = $this->makeFacade();

        self::assertSame($root, $facade::getFacadeRoot());
    }

    #[Test]
    public function testResolveCachesTheRootAndReturnsItOnSubsequentCalls(): void
    {
        $first = new class {};
        $second = new class {};
        $facade = $this->makeFacade();

        // First resolution is a cache miss: it fetches and stores $first.
        $this->registerRoot($first);
        self::assertSame($first, $facade::getFacadeRoot());

        // Even after the Kernel would hand back a different object, the cached
        // root is returned (cache-hit branch).
        $this->registerRoot($second);
        self::assertSame($first, $facade::getFacadeRoot());
    }

    #[Test]
    public function testDisableCacheBypassesTheCacheOnEveryResolution(): void
    {
        $first = new class {};
        $second = new class {};
        $facade = $this->makeFacade();

        $facade::disableCache();

        $this->registerRoot($first);
        self::assertSame($first, $facade::getFacadeRoot());

        // Caching is off, so a new registration is reflected immediately.
        $this->registerRoot($second);
        self::assertSame($second, $facade::getFacadeRoot());
    }

    #[Test]
    public function testEnableCacheReenablesCachingAfterItWasDisabled(): void
    {
        $first = new class {};
        $second = new class {};
        $facade = $this->makeFacade();

        $facade::disableCache();
        $facade::enableCache();

        $this->registerRoot($first);
        self::assertSame($first, $facade::getFacadeRoot());

        // Caching is back on: the stored root wins over a later registration.
        $this->registerRoot($second);
        self::assertSame($first, $facade::getFacadeRoot());
    }

    #[Test]
    public function testResetClearsTheCacheAndReenablesCaching(): void
    {
        $first = new class {};
        $second = new class {};
        $third = new class {};
        $facade = $this->makeFacade();

        $this->registerRoot($first);
        self::assertSame($first, $facade::getFacadeRoot());

        $facade::reset();

        // The cache was cleared, so the next resolution fetches afresh.
        $this->registerRoot($second);
        self::assertSame($second, $facade::getFacadeRoot());

        // Caching is enabled again, so $second is now cached over $third.
        $this->registerRoot($third);
        self::assertSame($second, $facade::getFacadeRoot());
    }

    #[Test]
    public function testClearResolvedInstanceDropsOnlyTheNamedRoot(): void
    {
        $first = new class {};
        $second = new class {};
        $facade = $this->makeFacade();

        $this->registerRoot($first);
        self::assertSame($first, $facade::getFacadeRoot());

        $facade::clearResolvedInstance(self::ACCESSOR);

        // The cached entry was dropped, so the fresh registration is resolved.
        $this->registerRoot($second);
        self::assertSame($second, $facade::getFacadeRoot());
    }

    #[Test]
    public function testClearResolvedInstancesDropsAllCachedRoots(): void
    {
        $first = new class {};
        $second = new class {};
        $facade = $this->makeFacade();

        $this->registerRoot($first);
        self::assertSame($first, $facade::getFacadeRoot());

        $facade::clearresolvedInstances();

        $this->registerRoot($second);
        self::assertSame($second, $facade::getFacadeRoot());
    }

    #[Test]
    public function testSwapOverridesTheRootAndRegistersItInTheKernel(): void
    {
        // Kernel::get(KernelInterface::class) must return an object exposing
        // instance(); a recorder captures the (id, instance) override call.
        $recorder = new class {
            /** @var array<int, array{0: string, 1: object}> */
            public array $calls = [];

            public function instance(string $id, object $instance): void
            {
                $this->calls[] = [$id, $instance];
            }
        };
        Kernel::instance(KernelInterface::class, $recorder);

        $replacement = new class {};
        $facade = $this->makeFacade();

        $facade::swap($replacement);

        // The swapped instance is cached locally...
        self::assertSame($replacement, $facade::getFacadeRoot());
        // ...and mirrored into the Kernel under the facade accessor.
        self::assertSame([[self::ACCESSOR, $replacement]], $recorder->calls);
    }

    #[Test]
    public function testResolveThrowsRuntimeExceptionWhenTheRootCannotBeResolved(): void
    {
        // No root registered + a null container => Kernel::get() raises, which
        // resolveFacadeInstance() rewraps as a RuntimeException chained to the
        // original failure.
        $facade = $this->makeFacade();

        try {
            $facade::getFacadeRoot();
            self::fail('Expected a RuntimeException when the facade root is unresolvable.');
        } catch (RuntimeException $runtimeException) {
            self::assertStringContainsString(
                sprintf('Facade root [%s] not found in container', self::ACCESSOR),
                $runtimeException->getMessage(),
            );
            self::assertInstanceOf(RuntimeException::class, $runtimeException->getPrevious());
        }
    }

    /**
     * Fresh concrete facade fixture bound to the fixed accessor.
     */
    private function makeFacade(): AbstractFacade
    {
        return new class extends AbstractFacade {
            public static function getFacadeAccessor(): string
            {
                return 'middag.tests.abstract_facade.root';
            }
        };
    }

    /**
     * Register the facade root as a Kernel runtime instance for the accessor.
     */
    private function registerRoot(object $root): void
    {
        Kernel::instance(self::ACCESSOR, $root);
    }

    /**
     * Inject a pre-booted Kernel singleton (container left null) so the
     * runtime-instance seam serves facade roots without a real boot.
     */
    private function injectBootedKernel(): void
    {
        $reflection = new ReflectionClass(Kernel::class);
        $kernel = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('booted')->setValue($kernel, true);
        $reflection->getProperty('instance')->setValue(null, $kernel);
    }
}
