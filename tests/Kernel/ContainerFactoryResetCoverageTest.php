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

use Middag\Moodle\Http\Routing\MoodleRouter;
use Middag\Moodle\Kernel\ContainerFactory;
use Middag\Moodle\Kernel\Kernel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Complements ContainerFactoryTest with the seam behaviours it does not assert:
 * that getInstance() forwards the exact kernel + router into the product
 * builder closure, that setBuilder() is last-write-wins, that the not-registered
 * error names the factory FQCN, and that reset() is a safe no-op when nothing is
 * wired. Every method reuses the static-state wipe so the singleton seam never
 * leaks a builder/callback/cache into a sibling suite.
 *
 * @internal
 */
#[CoversClass(ContainerFactory::class)]
final class ContainerFactoryResetCoverageTest extends TestCase
{
    protected function setUp(): void
    {
        $this->wipeSeamState();
    }

    protected function tearDown(): void
    {
        $this->wipeSeamState();
    }

    #[Test]
    public function getInstanceForwardsTheKernelAndRouterIntoTheBuilder(): void
    {
        $kernel = $this->kernel();
        $router = $this->router();
        $received = [];

        $expected = new ContainerBuilder();
        ContainerFactory::setBuilder(
            static function (Kernel $k, MoodleRouter $r) use (&$received, $expected): ContainerBuilder {
                $received = [$k, $r];

                return $expected;
            }
        );

        $container = ContainerFactory::getInstance($kernel, $router);

        self::assertSame($expected, $container);
        self::assertSame($kernel, $received[0]);
        self::assertSame($router, $received[1]);
    }

    #[Test]
    public function getInstanceReturnsTheCachedContainerWithoutRebuilding(): void
    {
        $builds = 0;
        ContainerFactory::setBuilder(static function () use (&$builds): ContainerBuilder {
            ++$builds;

            return new ContainerBuilder();
        });

        $first = ContainerFactory::getInstance($this->kernel(), $this->router());
        $second = ContainerFactory::getInstance($this->kernel(), $this->router());

        self::assertSame($first, $second);
        self::assertSame(1, $builds);
    }

    #[Test]
    public function setBuilderIsLastWriteWins(): void
    {
        ContainerFactory::setBuilder(static function (): ContainerBuilder {
            throw new RuntimeException('the superseded builder must never run');
        });
        $winner = new ContainerBuilder();
        ContainerFactory::setBuilder(static fn (): ContainerBuilder => $winner);

        self::assertSame($winner, ContainerFactory::getInstance($this->kernel(), $this->router()));
    }

    #[Test]
    public function getInstanceErrorNamesTheFactoryClass(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(ContainerFactory::class . '::setBuilder()');

        ContainerFactory::getInstance($this->kernel(), $this->router());
    }

    #[Test]
    public function resetIsANoOpWhenNothingIsWired(): void
    {
        // No builder, no callbacks, no cache: the empty sweep must not throw and
        // must not fabricate a builder — the next getInstance still errors.
        ContainerFactory::reset();

        $this->expectException(RuntimeException::class);

        ContainerFactory::getInstance($this->kernel(), $this->router());
    }

    #[Test]
    public function resetRunsEveryCallbackBeforeRethrowingTheFirstFailureWithMultipleThrowers(): void
    {
        $ranSecond = false;
        ContainerFactory::registerResetCallback('product-a', static function (): never {
            throw new RuntimeException('first failure');
        });
        ContainerFactory::registerResetCallback('product-b', static function () use (&$ranSecond): never {
            $ranSecond = true;

            throw new RuntimeException('second failure');
        });

        try {
            ContainerFactory::reset();
            self::fail('reset() must rethrow after the sweep completes');
        } catch (RuntimeException $runtimeException) {
            // The FIRST failure wins the rethrow (?? keeps the earliest).
            self::assertSame('first failure', $runtimeException->getMessage());
        }

        // Every hook ran despite the earlier throw.
        self::assertTrue($ranSecond);
    }

    /**
     * The seam holds static state; wipe builder/callbacks/cache at every boundary
     * so nothing leaks into sibling tests or other suites.
     */
    private function wipeSeamState(): void
    {
        $reflection = new ReflectionClass(ContainerFactory::class);
        $reflection->setStaticPropertyValue('container', null);
        $reflection->setStaticPropertyValue('builder', null);
        $reflection->setStaticPropertyValue('resetCallbacks', []);
    }

    private function kernel(): Kernel
    {
        return (new ReflectionClass(Kernel::class))->newInstanceWithoutConstructor();
    }

    private function router(): MoodleRouter
    {
        return $this->createMock(MoodleRouter::class);
    }
}
