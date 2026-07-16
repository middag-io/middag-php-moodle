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

use Middag\Moodle\Shared\Util\Debug;
use Throwable;

/**
 * User preference wrapper for Moodle's Preference API.
 *
 * Encapsulates get_user_preferences/set_user_preference/unset_user_preference,
 * providing typed returns and safe error handling within the Moodle boundary.
 *
 * Preferences are per-user key-value pairs stored in the database for authenticated
 * users and in the session for guests.
 *
 * @api
 */
class PreferenceSupport
{
    /**
     * Retrieves a single user preference value.
     *
     * @param string   $name    the preference key
     * @param mixed    $default value returned when the preference is not set
     * @param null|int $userid  target user ID (null = current user)
     *
     * @return mixed the preference value, or $default if not set
     */
    public static function get(string $name, mixed $default = null, ?int $userid = null): mixed
    {
        try {
            $user = $userid ?? null;

            return get_user_preferences($name, $default, $user);
        } catch (Throwable $throwable) {
            Debug::traceException($throwable);

            return $default;
        }
    }

    /**
     * Retrieves all preferences for a user as an object.
     *
     * @param null|int $userid target user ID (null = current user)
     *
     * @return null|object object with preference name => value pairs, or null on error
     */
    public static function getAll(?int $userid = null): ?object
    {
        try {
            $user = $userid ?? null;
            $result = get_user_preferences(null, null, $user);

            // get_user_preferences(null, ...) returns a plain array (never a
            // stdClass), so the old is_object() check made this always return
            // null. Convert the array — stripping the internal _lastloaded
            // cache-freshness key so it does not leak as a fake preference.
            if (is_array($result)) {
                unset($result['_lastloaded']);

                return (object) $result;
            }

            return is_object($result) ? $result : null;
        } catch (Throwable $throwable) {
            Debug::traceException($throwable);

            return null;
        }
    }

    /**
     * Sets a single user preference.
     *
     * @param string   $name   the preference key
     * @param mixed    $value  the value to store (will be cast to string by
     *                         Moodle). CAVEAT: null does NOT store anything —
     *                         Moodle's set_user_preference() treats null as
     *                         "delete current value" and delegates to
     *                         unset_user_preference().
     * @param null|int $userid target user ID (null = current user)
     *
     * @return bool true on success, false on failure
     */
    public static function set(string $name, mixed $value, ?int $userid = null): bool
    {
        try {
            $user = $userid ?? null;
            set_user_preference($name, $value, $user);

            return true;
        } catch (Throwable $throwable) {
            Debug::traceException($throwable);

            return false;
        }
    }

    /**
     * Sets multiple user preferences at once.
     *
     * @param array<string, mixed> $preferences associative array of name => value pairs
     * @param null|int             $userid      target user ID (null = current user)
     *
     * @return bool true on success, false on failure
     */
    public static function setMany(array $preferences, ?int $userid = null): bool
    {
        try {
            $user = $userid ?? null;
            set_user_preferences($preferences, $user);

            return true;
        } catch (Throwable $throwable) {
            Debug::traceException($throwable);

            return false;
        }
    }

    /**
     * Removes a single user preference.
     *
     * @param string   $name   the preference key to remove
     * @param null|int $userid target user ID (null = current user)
     *
     * @return bool true on success, false on failure
     */
    public static function remove(string $name, ?int $userid = null): bool
    {
        try {
            $user = $userid ?? null;

            return unset_user_preference($name, $user);
        } catch (Throwable $throwable) {
            Debug::traceException($throwable);

            return false;
        }
    }
}
