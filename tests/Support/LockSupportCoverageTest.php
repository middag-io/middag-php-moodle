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

use core\lock\lock;
use Middag\Moodle\Config\ComponentContext;
use Middag\Moodle\Support\LockSupport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * LockSupport wraps Moodle's lock_config/lock_factory. The factory and lock are
 * recording doubles (tests/stubs/support/output-db.php) driven by $GLOBALS flags,
 * so acquisition success, unavailability, and failure branches are all reachable.
 *
 * @internal
 */
#[CoversClass(LockSupport::class)]
final class LockSupportCoverageTest extends TestCase
{
    protected function setUp(): void
    {
        ComponentContext::configure('local_example', 'local_example_autoload');
        $this->resetLockGlobals();
    }

    protected function tearDown(): void
    {
        $this->resetLockGlobals();
        ComponentContext::configure('local_example', 'local_example_autoload');
    }

    #[Test]
    public function testExecuteRunsTheCallbackAndReleasesTheLock(): void
    {
        $result = LockSupport::execute('job_42', fn (): string => 'work-done');

        self::assertSame('work-done', $result);
        self::assertTrue($GLOBALS['__middag_test_lock_released'] ?? false);
    }

    #[Test]
    public function testExecuteReturnsNullAndSkipsTheCallbackWhenTheLockIsUnavailable(): void
    {
        $GLOBALS['__middag_test_lock_unavailable'] = true;
        $called = false;

        $result = LockSupport::execute('job_42', function () use (&$called): string {
            $called = true;

            return 'work-done';
        });

        self::assertNull($result);
        self::assertFalse($called);
    }

    #[Test]
    public function testExecuteReleasesTheLockEvenWhenTheCallbackThrows(): void
    {
        try {
            LockSupport::execute('job_42', function (): never {
                throw new RuntimeException('boom');
            });
            self::fail('the callback exception should propagate');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('boom', $runtimeException->getMessage());
        }

        self::assertTrue($GLOBALS['__middag_test_lock_released'] ?? false);
    }

    #[Test]
    public function testAcquireReturnsALockHandle(): void
    {
        self::assertInstanceOf(lock::class, LockSupport::acquire('job_42'));
    }

    #[Test]
    public function testAcquireReturnsNullWhenTheLockIsUnavailable(): void
    {
        $GLOBALS['__middag_test_lock_unavailable'] = true;

        self::assertNull(LockSupport::acquire('job_42'));
    }

    #[Test]
    public function testAcquireReturnsNullWhenTheFactoryThrows(): void
    {
        $GLOBALS['__middag_test_lock_factory_throws'] = true;

        self::assertNull(LockSupport::acquire('job_42'));
    }

    #[Test]
    public function testAcquireReturnsNullWhenLockAcquisitionThrows(): void
    {
        $GLOBALS['__middag_test_lock_get_throws'] = true;

        self::assertNull(LockSupport::acquire('job_42'));
    }

    #[Test]
    public function testReleaseReleasesTheLock(): void
    {
        $lock = new lock('job_42');

        LockSupport::release($lock);

        self::assertTrue($lock->released);
    }

    #[Test]
    public function testReleaseSwallowsExceptionsFromTheLock(): void
    {
        $GLOBALS['__middag_test_lock_release_throws'] = true;
        $lock = new lock('job_42');

        LockSupport::release($lock);

        // release() threw before marking the lock released; LockSupport traced and
        // swallowed the throwable rather than letting it escape.
        self::assertFalse($lock->released);
    }

    private function resetLockGlobals(): void
    {
        foreach ([
            '__middag_test_lock_released',
            '__middag_test_lock_unavailable',
            '__middag_test_lock_factory_throws',
            '__middag_test_lock_get_throws',
            '__middag_test_lock_release_throws',
        ] as $key) {
            unset($GLOBALS[$key]);
        }
    }
}
