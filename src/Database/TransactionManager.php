<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Database;

use core\exception\moodle_exception;
use Exception;
use Middag\Moodle\Database\Contract\TransactionManagerInterface as transaction_manager_interface;
use Middag\Moodle\Support\DbSupport as db_support;
use Throwable;

/**
 * Concrete Transaction Manager mapped over Moodle's native Delegated Transactions.
 *
 * Provides safe exception interception globally bypassing the PHP 7+ Error issues
 * and cleanly avoiding fatal transaction nested crashes.
 *
 * @internal
 */
class TransactionManager implements transaction_manager_interface
{
    /**
     * {@inheritDoc}
     */
    public function executeAtomic(callable $operation): mixed
    {
        global $DB;

        $transaction = db_support::startDelegatedTransaction();

        try {
            $result = $operation();
            $transaction->allow_commit();

            return $result;
        } catch (Throwable $throwable) {
            // Cast strictly to moodle_exception logic because moodle_transaction->rollback() fails on generic non-Exception Throwables
            $cast_e = $throwable instanceof Exception ? $throwable : new moodle_exception('transactionerror', 'error', '', null, $throwable->getMessage(), $throwable);

            try {
                $transaction->rollback($cast_e);
            } catch (Exception) {
                // Intentionally suppressed: Moodle rollback may throw internally; original exception is re-thrown below.
            }

            throw $throwable; // Re-throw original for strict stack traces
        }
    }

    /**
     * {@inheritDoc}
     */
    public function executeGraceful(callable $operation): mixed
    {
        global $DB;

        $transaction = db_support::startDelegatedTransaction();

        try {
            $result = $operation();
            $transaction->allow_commit();

            return $result;
        } catch (Throwable $throwable) {
            $cast_e = $throwable instanceof Exception ? $throwable : new moodle_exception('transationalerror', 'error', '', null, $throwable->getMessage(), $throwable);

            try {
                // Moodle unconditionally forces this rollback call to throw!
                $transaction->rollback($cast_e);
            } catch (Exception) {
                // Intentionally suppressed: Moodle forces rollback() to throw; exception is evaluated below.
            }

            // Central Intelligence:
            // - If $DB->is_transaction_started() is now false, the DB has completely unwound (Outermost wrapper) => safe to swallow!
            // - If $DB->is_transaction_started() is true, the DB is at a nested point and forced to be aborted => unsafe to swallow!

            if ($DB->is_transaction_started()) {
                throw $throwable;
            }

            // Returns the graceful failure bypassing the fatal framework halt
            return $throwable;
        }
    }
}
