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

use coding_exception;
use core\user as core_user;
use Middag\Moodle\Domain\User\User;
use Middag\Moodle\Shared\Util\Debug;
use stdClass;

/**
 * Utility functions for Moodle users and custom profile fields.
 *
 * @api
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
     * @return null|User user entity or null if not found
     */
    public static function getUserByEmail(string $email, string $fields = '*', ?int $mnethostid = null, int $strictness = IGNORE_MISSING): ?User
    {
        $user = core_user::get_user_by_email($email, $fields, $mnethostid, $strictness);

        return $user ? User::fromRecord($user) : null;
    }

    /**
     * Retrieves a user by their database ID.
     *
     * @param int    $userid     User ID
     * @param string $fields     comma-separated list of fields to select
     * @param int    $strictness Moodle strictness constant (IGNORE_MISSING, IGNORE_MULTIPLE, MUST_EXIST)
     *
     * @return null|User user entity or null if not found
     */
    public static function getUser(int $userid, string $fields = '*', int $strictness = IGNORE_MISSING): ?User
    {
        $user = core_user::get_user($userid, $fields, $strictness);

        return $user ? User::fromRecord($user) : null;
    }

    /**
     * Retrieves a user by their username.
     *
     * @param string   $username   username
     * @param null|int $mnethostid MNet host ID
     * @param int      $strictness Moodle strictness constant (IGNORE_MISSING, IGNORE_MULTIPLE, MUST_EXIST)
     *
     * @return null|User user entity or null if not found
     */
    public static function getUserByUsername(string $username, ?int $mnethostid = null, int $strictness = IGNORE_MISSING): ?User
    {
        $user = core_user::get_user_by_username($username, '*', $mnethostid, $strictness);

        return $user ? User::fromRecord($user) : null;
    }

    /**
     * Creates a new Moodle user with sensible defaults.
     *
     * @param stdClass $userobj        user data object
     * @param bool     $updatepassword whether to update the password
     * @param bool     $triggerevent   whether to trigger the user_created event
     *
     * @return int new User ID
     */
    public static function createUser(stdClass $userobj, bool $updatepassword = false, bool $triggerevent = true): int
    {
        global $CFG;

        $userobj->auth ??= 'manual';
        $userobj->confirmed ??= 1;
        $userobj->mnethostid ??= ConfigSupport::getConfig('core', 'mnet_localhost_id');

        require_once $CFG->dirroot . '/user/lib.php';

        $id = user_create_user($userobj, $updatepassword, $triggerevent);

        // `user_create_user()` writes the `user` table and silently ignores every
        // `profile_field_<shortname>` property on the object — those live in
        // `user_info_data` and only `profile_save_data()` puts them there. Without this the
        // caller sets seven profile fields, gets an id back, no error anywhere, and the
        // fields are simply not on the account.
        self::saveProfileFields($userobj, $id);

        return $id;
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

        // Same gap as on create: `user_update_user()` never touches `user_info_data`.
        self::saveProfileFields($userobj, (int) ($userobj->id ?? 0));
    }

    /**
     * Soft-deletes a user.
     *
     * @param stdClass $user user object to delete (must carry 'id' and 'username')
     *
     * @return bool True on success, false otherwise
     */
    public static function deleteUser(stdClass $user): bool
    {
        global $CFG;

        require_once $CFG->dirroot . '/user/lib.php';

        try {
            return delete_user($user);
        } catch (coding_exception $codingexception) {
            // Moodle's delete_user() throws when the record lacks the id or
            // username property (even though it re-fetches the row by id and
            // discards the rest); the documented contract here is bool, so
            // surface that guard as false instead of crashing the caller.
            Debug::traceException($codingexception);

            return false;
        }
    }

    /**
     * Retrieves the full name of a user.
     *
     * @param stdClass|User $user user object or entity
     *
     * @return string formatted full name
     */
    public static function fullname(stdClass|User $user): string
    {
        $userobj = $user instanceof User ? $user->toRecord() : $user;

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
     * @return null|User current user entity or null if not logged in
     */
    public static function getCurrent(): ?User
    {
        global $USER;

        return isset($USER->id) ? User::fromRecord($USER) : null;
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

    /**
     * Persist the custom profile fields carried on a user object, if any.
     *
     * Moodle keeps custom profile fields in `user_info_data`, reached only through
     * `profile_save_data()`, which reads `profile_field_<shortname>` properties off the object
     * and needs the id already on it. Both write paths above hand their object here so a caller
     * can set a profile field the same way it sets a core column, which is what every consumer
     * of this class already assumed.
     *
     * Does nothing when the object carries no profile field — the common case, and the reason
     * this does not cost a file include on every user write.
     */
    private static function saveProfileFields(stdClass $userobj, int $id): void
    {
        global $CFG;

        if ($id <= 0) {
            return;
        }

        $hasProfileField = false;

        foreach (get_object_vars($userobj) as $property => $ignored) {
            if (str_starts_with($property, 'profile_field_')) {
                $hasProfileField = true;

                break;
            }
        }

        if (!$hasProfileField) {
            return;
        }

        require_once $CFG->dirroot . '/user/profile/lib.php';

        // A clone, because `profile_save_data()` writes back onto the object it is given and the
        // caller's object is not this method's to mutate.
        $target = clone $userobj;
        $target->id = $id;

        profile_save_data($target);
    }
}
