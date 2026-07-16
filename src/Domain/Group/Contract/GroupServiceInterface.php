<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Domain\Group\Contract;

/**
 * Group membership service contract — typed group operations.
 *
 * Composes the low-level GroupSupport primitives (lookup, create, membership)
 * into cohesive group-membership operations, so extensions do not orchestrate
 * the primitives by hand. Extensions consume via facade or DI (never
 * instantiate directly).
 *
 * @api
 */
interface GroupServiceInterface
{
    /**
     * Ensure a user is a member of a named group in a course, creating the
     * group when it does not yet exist. Idempotent: an existing membership
     * counts as success.
     *
     * @param int    $courseid  Course ID
     * @param int    $userid    User ID
     * @param string $groupname Group name
     *
     * @return bool True if the user was confirmed/added to the group, false on failure
     */
    public function addUserToGroup(int $courseid, int $userid, string $groupname): bool;
}
