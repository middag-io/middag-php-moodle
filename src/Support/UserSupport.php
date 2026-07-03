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

use core\user as core_user;
use Middag\Moodle\Domain\User\User as user_entity;
use stdClass;

/**
 * Utility functions for Moodle users and custom profile fields.
 *
 * @internal
 */
class UserSupport
{
    /**
     * Retrieves a user by their email address.
     *
     * @param string   $email      the email address
     * @param string   $fields     comma-separated list of fields to select
     * @param null|int $mnethostid MNet host ID
     * @param int      $strictness Moodle strictness constant (IGNORE_MISSING, IGNORE_MULTIPLE or MUST_EXIST)
     *
     * @return null|user_entity user entity or null if not found
     */
    public static function getUserByEmail(string $email, string $fields = '*', ?int $mnethostid = null, int $strictness = IGNORE_MISSING): ?user_entity
    {
        $user = core_user::get_user_by_email($email, $fields, $mnethostid, $strictness);

        return $user ? user_entity::fromRecord($user) : null;
    }

    /**
     * Retrieves a user by their database ID.
     *
     * @param int    $userid     User ID
     * @param string $fields     comma-separated list of fields to select
     * @param int    $strictness Moodle strictness constant (IGNORE_MISSING, IGNORE_MULTIPLE, MUST_EXIST)
     *
     * @return null|user_entity user entity or null if not found
     */
    public static function getUser(int $userid, string $fields = '*', int $strictness = IGNORE_MISSING): ?user_entity
    {
        $user = core_user::get_user($userid, $fields, $strictness);

        return $user ? user_entity::fromRecord($user) : null;
    }

    /**
     * Retrieves a user by their username.
     *
     * @param string   $username   username
     * @param null|int $mnethostid MNet host ID
     * @param int      $strictness Moodle strictness constant (IGNORE_MISSING, IGNORE_MULTIPLE, MUST_EXIST)
     *
     * @return null|user_entity user entity or null if not found
     */
    public static function getUserByUsername(string $username, ?int $mnethostid = null, int $strictness = IGNORE_MISSING): ?user_entity
    {
        $user = core_user::get_user_by_username($username, '*', $mnethostid, $strictness);

        return $user ? user_entity::fromRecord($user) : null;
    }

    /**
     * Creates a new Moodle user with sensible defaults.
     *
     * @param stdClass $userobj        user data object
     * @param bool     $updatepassword whether to update the password
     * @param bool     $nologin        whether to prevent the user from logging in
     *
     * @return int new User ID
     */
    public static function createUser(stdClass $userobj, bool $updatepassword = false, bool $nologin = false): int
    {
        global $CFG;

        // Enforce essential defaults if missing
        if (!isset($userobj->auth)) {
            $userobj->auth = 'manual';
        }
        if (!isset($userobj->confirmed)) {
            $userobj->confirmed = 1;
        }
        if (!isset($userobj->mnethostid)) {
            $userobj->mnethostid = ConfigSupport::getConfig('core', 'mnet_localhost_id');
        }

        require_once $CFG->dirroot . '/user/lib.php';

        return user_create_user($userobj, $updatepassword, $nologin);
    }

    /**
     * Updates an existing Moodle user.
     *
     * @param stdClass $userobj        user data object (must include 'id')
     * @param bool     $updatepassword whether to update the password
     * @param bool     $triggerevent   whether to trigger Moodle events
     */
    public static function updateUser(stdClass $userobj, bool $updatepassword = true, bool $triggerevent = true): void
    {
        global $CFG;

        require_once $CFG->dirroot . '/user/lib.php';

        user_update_user($userobj, $updatepassword, $triggerevent);
    }

    /**
     * Soft-deletes a user.
     *
     * @param stdClass $user user object to delete
     *
     * @return bool True on success, false otherwise
     */
    public static function deleteUser(stdClass $user): bool
    {
        global $CFG;

        require_once $CFG->dirroot . '/user/lib.php';

        return delete_user($user);
    }

    /**
     * Retrieves the full name of a user.
     *
     * @param stdClass|user_entity $user user object or entity
     *
     * @return string formatted full name
     */
    public static function fullname(stdClass|user_entity $user): string
    {
        $userobj = $user instanceof user_entity ? $user->toRecord() : $user;

        return fullname($userobj);
    }

    /**
     * Retrieves the current logged-in user ID.
     *
     * @return int current user ID or 0 if not logged in
     */
    public static function getCurrentUserId(): int
    {
        global $USER;

        return (int) ($USER->id ?? 0);
    }

    /**
     * Retrieves the current user entity.
     *
     * @return null|user_entity current user entity or null if not logged in
     */
    public static function getCurrent(): ?user_entity
    {
        global $USER;

        return isset($USER->id) ? user_entity::fromRecord($USER) : null;
    }

    /**
     * Formats user data into a structured array.
     *
     * @param stdClass $user the user object containing user details
     *
     * @return array an associative array containing formatted user data, including id, name, contact details,
     *               and custom profile fields
     */
    public static function formatUserData(stdClass $user): array
    {
        $return = [
            'id' => $user->id,
            'fullname' => fullname($user),
            'username' => $user->username,
            'firstname' => $user->firstname,
            'lastname' => $user->lastname,
            'email' => $user->email,
            'phone1' => $user->phone1,
            'phone2' => $user->phone2,
            'city' => $user->city,
            'country' => $user->country,
            'profile_fields' => [],
        ];

        $fields = UserFieldSupport::getAllUserData((int) $user->id);
        foreach ($fields as $shortname => $value) {
            $return['profile_fields'][$shortname] = $value;
        }

        return $return;
    }
}
