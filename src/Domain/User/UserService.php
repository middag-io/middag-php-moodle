<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Domain\User;

use core\exception\moodle_exception;
use Middag\Moodle\Domain\User\Contract\UserServiceInterface;
use Middag\Moodle\Support\UserSupport;
use stdClass;

/**
 * User Service.
 *
 * Provides orchestration logic for user lifecycle operations (create, update, delete).
 * Applies sensible defaults and validation before delegating to the Support layer.
 *
 * For simple user lookups (get_user, get_user_by_email, etc.) and profile field
 * operations, use the Support layer directly:
 *
 * @see UserSupport
 *
 * @internal
 *
 * @see UserServiceInterface
 */
class UserService implements UserServiceInterface
{
    /**
     * Create a new Moodle user with sensible defaults.
     *
     * Applies default values for auth, confirmed, and mnethostid if not provided,
     * then delegates to the Support layer.
     *
     * @param stdClass $userobj        Object containing user properties (username, email, etc)
     * @param bool     $updatepassword Force password change on first login
     * @param bool     $nologin        If true, user cannot login
     *
     * @return int New User ID
     */
    public function createUser(stdClass $userobj, bool $updatepassword = false, bool $nologin = false): int
    {
        // Delegate to Moodle Support (Wrapper) which now handles defaults.
        return UserSupport::createUser($userobj, $updatepassword, $nologin);
    }

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
    public function updateUser(stdClass $userobj, bool $updatepassword = true, bool $triggerevent = true): bool
    {
        if (empty($userobj->id)) {
            throw new moodle_exception('missingparam', 'error', '', 'id');
        }

        // Delegate to Moodle Support (Wrapper)
        UserSupport::updateUser($userobj, $updatepassword, $triggerevent);

        return true;
    }

    /**
     * Delete (soft-delete) a user with existence check.
     *
     * Verifies the user exists before attempting deletion.
     *
     * @param int $userid
     *
     * @return bool True on success, false if user not found
     */
    public function deleteUser(int $userid): bool
    {
        $user = UserSupport::getUser($userid);
        if (!$user instanceof User) {
            return false;
        }

        // Delegate to Moodle Support (Wrapper)
        return UserSupport::deleteUser($user->toRecord());
    }
}
