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
 * @internal
 */
#[CoversClass(ContainerFactory::class)]
final class ContainerFactoryTest extends TestCase
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
    public function getInstanceThrowsWhenNoBuilderIsRegistered(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('setBuilder()');

        ContainerFactory::getInstance($this->kernel(), $this->router());
    }

    #[Test]
    public function getInstanceCachesTheBuiltContainer(): void
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
    public function resetDropsTheCachedContainer(): void
    {
        ContainerFactory::setBuilder(static fn (): ContainerBuilder => new ContainerBuilder());

        $first = ContainerFactory::getInstance($this->kernel(), $this->router());
        ContainerFactory::reset();
        $second = ContainerFactory::getInstance($this->kernel(), $this->router());

        self::assertNotSame($first, $second);
    }

    #[Test]
    public function resetFiresRegisteredResetCallbacks(): void
    {
        $fired = 0;
        ContainerFactory::registerResetCallback('product-a', static function () use (&$fired): void {
            ++$fired;
        });

        ContainerFactory::reset();

        self::assertSame(1, $fired);
    }

    #[Test]
    public function registeringTheSameKeyTwiceKeepsOnlyTheLastCallback(): void
    {
        $firstFired = 0;
        $lastFired = 0;
        ContainerFactory::registerResetCallback('product-a', static function () use (&$firstFired): void {
            ++$firstFired;
        });
        ContainerFactory::registerResetCallback('product-a', static function () use (&$lastFired): void {
            ++$lastFired;
        });

        ContainerFactory::reset();

        self::assertSame(0, $firstFired);
        self::assertSame(1, $lastFired);
    }

    #[Test]
    public function resetCallbacksSurviveResetAndFireOnEverySweep(): void
    {
        $fired = 0;
        ContainerFactory::registerResetCallback('product-a', static function () use (&$fired): void {
            ++$fired;
        });

        ContainerFactory::reset();
        ContainerFactory::reset();

        self::assertSame(2, $fired);
    }

    #[Test]
    public function distinctKeysAllFireOnReset(): void
    {
        $log = [];
        ContainerFactory::registerResetCallback('product-a', static function () use (&$log): void {
            $log[] = 'a';
        });
        ContainerFactory::registerResetCallback('product-b', static function () use (&$log): void {
            $log[] = 'b';
        });

        ContainerFactory::reset();

        self::assertSame(['a', 'b'], $log);
    }

    #[Test]
    public function aThrowingCallbackDoesNotStopTheSweep(): void
    {
        $log = [];
        ContainerFactory::registerResetCallback('product-a', static function (): never {
            throw new RuntimeException('product-a reset failed');
        });
        ContainerFactory::registerResetCallback('product-b', static function () use (&$log): void {
            $log[] = 'b';
        });

        try {
            ContainerFactory::reset();
            self::fail('reset() must rethrow the callback failure');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('product-a reset failed', $runtimeException->getMessage());
        }

        self::assertSame(['b'], $log);
    }

    #[Test]
    public function theFirstOfMultipleFailuresIsRethrown(): void
    {
        ContainerFactory::registerResetCallback('product-a', static function (): never {
            throw new RuntimeException('first failure');
        });
        ContainerFactory::registerResetCallback('product-b', static function (): never {
            throw new RuntimeException('second failure');
        });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('first failure');

        ContainerFactory::reset();
    }

    #[Test]
    public function theCachedContainerIsDroppedEvenWhenACallbackThrows(): void
    {
        ContainerFactory::setBuilder(static fn (): ContainerBuilder => new ContainerBuilder());
        $first = ContainerFactory::getInstance($this->kernel(), $this->router());

        ContainerFactory::registerResetCallback('product-a', static function (): never {
            throw new RuntimeException('boom');
        });

        try {
            ContainerFactory::reset();
        } catch (RuntimeException) {
            // expected — the sweep still dropped the cache below.
        }

        ContainerFactory::registerResetCallback('product-a', static function (): void {});
        $second = ContainerFactory::getInstance($this->kernel(), $this->router());

        self::assertNotSame($first, $second);
    }

    /**
     * The seam holds static state; tests must not leak builder/callbacks/cache
     * into each other (or into other suites), so every boundary wipes all three.
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
        $reflection = new ReflectionClass(Kernel::class);

        return $reflection->newInstanceWithoutConstructor();
    }

    private function router(): MoodleRouter
    {
        return $this->createMock(MoodleRouter::class);
    }
}
