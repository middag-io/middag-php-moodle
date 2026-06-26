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

use core\exception\moodle_exception;
use stdClass;

/**
 * Authentication support wrapper for Moodle's authentication functions.
 *
 * @internal
 */
class AuthSupport
{
    /**
     * Ensures that the user is logged in.
     *
     * @param mixed $courseorid     Course object or ID to check login for
     * @param bool  $autologinguest Whether to automatically login guest users
     * @param mixed $cm             Course module object or ID
     * @param bool  $setocoinhibit  Whether to inhibit the setocoin
     * @param bool  $displaymessage Whether to display a message
     *
     * @throws moodle_exception if user is not logged in
     */
    public static function requireLogin($courseorid = null, bool $autologinguest = true, $cm = null, bool $setocoinhibit = true, bool $preventredirect = false): void
    {
        require_login($courseorid, $autologinguest, $cm, $setocoinhibit, $preventredirect);
    }

    /**
     * Checks if the user is currently logged in.
     *
     * @return bool True if logged in, false otherwise
     */
    public static function isLoggedIn(): bool
    {
        return isloggedin();
    }

    /**
     * Checks if the current user is a guest.
     *
     * @return bool True if user is a guest, false otherwise
     */
    public static function isGuest(): bool
    {
        return isguestuser();
    }

    /**
     * Completes the user login process.
     *
     * @param stdClass $user User object to login
     *
     * @return bool True on success, false otherwise
     */
    public static function completeUserLogin(stdClass $user): bool
    {
        return (bool) complete_user_login($user);
    }

    /**
     * Retrieves the site administrator user record.
     *
     * @return null|stdClass Admin user object or null if not found
     */
    public static function getAdmin(): ?stdClass
    {
        return get_admin();
    }
}
