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

use core\context;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;

/**
 * Contract for the Privacy API Data Extractor (Repository).
 *
 * Isolates the intense raw queries requested by Moodle's Privacy Subsystem
 * from the Domain logic.
 *
 * @api
 */
interface PrivacyRepositoryInterface
{
    /**
     * Appends all contexts where the user has data to the given contextlist.
     *
     * @param int         $userid      User ID
     * @param contextlist $contextlist Moodle context list
     */
    public function addContextsForUserid(int $userid, contextlist $contextlist): void;

    /**
     * Exports the user data under the contexts specified.
     * Must write to core_privacy\local\request\writer.
     *
     * @param approved_contextlist $contextlist
     */
    public function exportUserData(approved_contextlist $contextlist): void;

    /**
     * Deletes all data for all users in a specific context.
     *
     * @param context $context The context representing the boundary
     */
    public function deleteDataForAllUsersInContext(context $context): void;

    /**
     * Deletes data for a specific user across one or more contexts.
     *
     * @param approved_contextlist $contextlist List of contexts where data should be deleted
     */
    public function deleteDataForUser(approved_contextlist $contextlist): void;
}
