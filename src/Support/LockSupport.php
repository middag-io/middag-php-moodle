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
use Middag\Moodle\Shared\Util\Debug;
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
 * @api
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
     * @param int           $maxlifetime seconds before the lock is treated as stale
     *                                   (default 86400 = 24h, erring safe). A no-op except
     *                                   under db_record_lock_factory, where too small a
     *                                   value lets a long job's lock expire mid-run and a
     *                                   concurrent call re-acquire it — size it to the job
     *
     * @return null|T callback return value, or null if the lock could not be
     *                acquired. CAVEAT: null is overloaded — a callback whose T
     *                legitimately includes null is indistinguishable from
     *                "skipped, lock busy". Callers needing to disambiguate must
     *                use a non-nullable T (e.g. wrap the result), or acquire()/
     *                release() manually and branch on the lock handle.
     */
    public static function execute(string $resource, callable $callback, int $timeout = 0, int $maxlifetime = 86400): mixed
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
     * @param int    $maxlifetime seconds before the lock is treated as stale (default
     *                            86400 = 24h, erring safe); a no-op except under
     *                            db_record_lock_factory (see execute())
     *
     * @return null|lock the lock handle, or null if acquisition failed
     */
    public static function acquire(string $resource, int $timeout = 0, int $maxlifetime = 86400): ?lock
    {
        // Resolve the component (ComponentContext) and lock factory OUTSIDE the
        // try. A MoodleConfigurationException (adapter not configured) or a
        // coding_exception (bad $CFG->lock_factory) is misconfiguration that
        // must fail loud, not be swallowed into the same null as ordinary lock
        // contention. Only the acquisition itself may degrade to null.
        $factory = lock_config::get_lock_factory(self::lockType());

        try {
            $lock = $factory->get_lock($resource, $timeout, $maxlifetime);

            return $lock !== false ? $lock : null;
        } catch (Throwable $throwable) {
            Debug::traceException($throwable);

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
            Debug::traceException($throwable);
        }
    }

    /** Lock factory type identifier (resolved from the {@see ComponentContext} seam). */
    private static function lockType(): string
    {
        return ComponentContext::name();
    }
}
