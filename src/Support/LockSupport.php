<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Support;

use core\lock\lock;
use core\lock\lock_config;
use Middag\Moodle\Config\ComponentContext;
use Middag\Moodle\Shared\Util\Debug as debug;
use Throwable;

/**
 * Resource locking wrapper for Moodle's Lock API.
 *
 * Encapsulates lock_config/lock_factory, providing closure-based execution
 * with automatic release as the primary API. Prevents concurrent operations
 * on shared resources (job processing, command bus, bulk imports).
 *
 * The closure-based execute() method is the recommended entry point.
 * Manual acquire()/release() is available for advanced scenarios where
 * the lock must span multiple operations.
 *
 * @internal
 */
class LockSupport
{
    /**
     * Acquires a lock, executes the callback, and releases automatically.
     *
     * This is the primary API. The lock is always released, even if the
     * callback throws an exception.
     *
     * @template T
     *
     * @param string        $resource    unique resource identifier (e.g. 'job_processing_42')
     * @param callable(): T $callback    the work to execute while holding the lock
     * @param int           $timeout     seconds to wait for lock acquisition (0 = fail immediately)
     * @param int           $maxlifetime maximum seconds to hold the lock
     *
     * @return null|T callback return value, or null if the lock could not be acquired
     */
    public static function execute(string $resource, callable $callback, int $timeout = 0, int $maxlifetime = 600): mixed
    {
        $lock = self::acquire($resource, $timeout, $maxlifetime);

        if (!$lock instanceof lock) {
            return null;
        }

        try {
            return $callback();
        } finally {
            self::release($lock);
        }
    }

    /**
     * Acquires a lock on a named resource.
     *
     * Caller is responsible for releasing via release(). Prefer execute()
     * for automatic release.
     *
     * @param string $resource    unique resource identifier
     * @param int    $timeout     seconds to wait for lock acquisition (0 = fail immediately)
     * @param int    $maxlifetime maximum seconds to hold the lock
     *
     * @return null|lock the lock handle, or null if acquisition failed
     */
    public static function acquire(string $resource, int $timeout = 0, int $maxlifetime = 600): ?lock
    {
        try {
            $factory = lock_config::get_lock_factory(self::lockType());
            $lock = $factory->get_lock($resource, $timeout, $maxlifetime);

            return $lock !== false ? $lock : null;
        } catch (Throwable $throwable) {
            debug::traceException($throwable);

            return null;
        }
    }

    /**
     * Releases a previously acquired lock.
     *
     * @param lock $lock the lock handle from acquire()
     */
    public static function release(lock $lock): void
    {
        try {
            $lock->release();
        } catch (Throwable $throwable) {
            debug::traceException($throwable);
        }
    }

    /** Lock factory type identifier (resolved from the {@see ComponentContext} seam). */
    private static function lockType(): string
    {
        return ComponentContext::name();
    }
}
