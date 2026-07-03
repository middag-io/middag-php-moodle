<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Domain\User\Contract;

use core\exception\coding_exception;
use core\exception\moodle_exception;
use stdClass;

/**
 * Contract for User Management Service.
 *
 * This service provides orchestration logic for user lifecycle operations.
 * For simple user lookups (get_user, get_user_by_email, etc.), use the
 * Support layer directly: Middag\Moodle\Support\UserSupport
 *
 * @see user_support For direct user lookups and profile field operations
 *
 * @api
 */
interface UserServiceInterface
{
    /**
     * Create a new Moodle user with sensible defaults.
     *
     * This method applies default values for auth, confirmed, and mnethostid
     * if not provided, then delegates to the Support layer.
     *
     * @param stdClass $userobj        Object containing user properties (username, email, etc)
     * @param bool     $updatepassword Force password change on first login
     * @param bool     $nologin        If true, user cannot login
     *
     * @return int New User ID
     *
     * @throws moodle_exception
     */
    public function createUser(stdClass $userobj, bool $updatepassword = false, bool $nologin = false): int;

    /**
     * Update an existing Moodle user with validation.
     *
     * Validates that the user ID is present before delegating to Support.
     *
     * @param stdClass $userobj        Object with at least 'id' property
     * @param bool     $updatepassword Hash password if changed
     * @param bool     $triggerevent   Fire \core\event\user_updated
     *
     * @return bool True on success
     *
     * @throws moodle_exception If user ID is missing
     */
    public function updateUser(stdClass $userobj, bool $updatepassword = true, bool $triggerevent = true): bool;

    /**
     * Delete (soft-delete) a user with existence check.
     *
     * Verifies the user exists before attempting deletion.
     *
     * @param int $userid
     *
     * @return bool True on success, false if user not found
     *
     * @throws coding_exception
     */
    public function deleteUser(int $userid): bool;
}
