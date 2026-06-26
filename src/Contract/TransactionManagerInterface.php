<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Contract;

use Throwable;

/**
 * Contract for the Transaction Manager.
 *
 * Provides a clean and robust execution layer for Moodle's delegated transactions,
 * transparently solving the "Implicit Rollback" conflicts and \Throwable incompatibilities.
 *
 * @api
 */
interface TransactionManagerInterface
{
    /**
     * Executes a callback inside a safe transaction boundary.
     *
     * @template T
     *
     * @param callable(): T $operation Core business logic to run
     *
     * @return T
     *
     * @throws Throwable Any exception triggered by the operation
     */
    public function executeAtomic(callable $operation): mixed;

    /**
     * Executes a callback inside a transaction boundary and gracefully catches exceptions.
     *
     * If the transaction is the Outermost transaction, Moodle's native exception-throwing
     * rollback is neutralized, avoiding LMS crashes and returning the Domain Exception or Result.
     * If it's an Inner transaction (nested), Moodle inherently dooms the DB connection,
     * so it predictably re-throws to honor Moodle's cascading rollback constraint.
     *
     * @template T
     *
     * @param callable(): T $operation Core business logic to run
     *
     * @return T|Throwable returns the Throwable on graceful rollback instead of crashing
     */
    public function executeGraceful(callable $operation): mixed;
}
