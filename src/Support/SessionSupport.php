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
use core\session\manager;
use Middag\Moodle\Domain\User\User;
use Middag\Moodle\Security\ValueObject\Sesskey;
use stdClass;

/**
 * Session support wrapper for Moodle's session management.
 *
 * @api
 */
class SessionSupport
{
    /**
     * Confirms that the current sesskey is valid.
     *
     * @param null|string $sesskey sesskey to validate (null for current request)
     *
     * @return bool True if valid, false otherwise
     */
    public static function confirmSesskey(?string $sesskey = null): bool
    {
        return confirm_sesskey($sesskey);
    }

    /**
     * Confirms sesskey validity using the typed value object.
     */
    public static function confirm(Sesskey $key): bool
    {
        return self::confirmSesskey((string) $key);
    }

    /**
     * Ensures that the current sesskey is valid.
     *
     * @throws moodle_exception if the sesskey is invalid
     */
    public static function requireSesskey(): void
    {
        require_sesskey();
    }

    /**
     * Retrieves the current sesskey.
     *
     * @return string sesskey
     */
    public static function sesskey(): string
    {
        // Moodle's sesskey() returns false when $_SESSION['USER'] isn't set yet
        // (early bootstrap / CLI / webservice). Under strict_types that false
        // would TypeError against this ': string' return, so normalise it to ''
        // — a downstream === comparison fails on '' just as it would on false.
        return sesskey() ?: '';
    }

    /**
     * Destroys all active sessions for a specific user.
     *
     * @param int    $userid User ID
     * @param string $except current session ID to keep
     */
    public static function destroyUserSessions(int $userid, string $except = ''): void
    {
        manager::destroy_user_sessions($userid, $except);
    }

    /**
     * Sets the current user in the session.
     *
     * @param stdClass|User $user User object or entity
     */
    public static function setUser(stdClass|User $user): void
    {
        $record = $user instanceof User ? $user->toRecord() : $user;
        manager::set_user($record);
    }

    /**
     * Retrieves the URL the user was trying to access before login.
     *
     * @return null|string the intended URL or null if not set
     */
    public static function getWantsUrl(): ?string
    {
        global $SESSION;

        return $SESSION->wantsurl ?? null;
    }

    /**
     * Removes the wantsurl from the session.
     */
    public static function unsetWantsUrl(): void
    {
        global $SESSION;
        unset($SESSION->wantsurl);
    }

    /**
     * Retrieves the current PHP session ID.
     *
     * @return string Session ID
     */
    public static function getId(): string
    {
        return session_id();
    }
}
