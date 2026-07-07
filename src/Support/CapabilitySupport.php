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

use core\context;
use core\exception\moodle_exception;
use Middag\Moodle\Shared\Util\Debug;
use stdClass;

/**
 * Utility functions for Moodle capabilities and permissions.
 *
 * @api
 */
class CapabilitySupport
{
    /**
     * Checks if a user has a specific capability in a given context.
     *
     * @param string            $capability Capability name
     * @param context           $context    Context object
     * @param null|int|stdClass $user       User object or ID (null for current user)
     * @param bool              $doanything Whether to check for the 'doanything' capability
     *
     * @return null|bool True if user has capability, false if not, or null on error
     */
    public static function has($capability, context $context, $user = null, bool $doanything = true): ?bool
    {
        try {
            return has_capability($capability, $context, $user, $doanything);
        } catch (moodle_exception $moodleexception) {
            Debug::traceException($moodleexception);
        }

        return null;
    }

    /**
     * Retrieves the human-readable name of a capability.
     *
     * @param string $capability Capability name
     *
     * @return string the localized capability name
     */
    public static function getString(string $capability): string
    {
        return get_capability_string($capability);
    }

    /**
     * Requires the user to have a specific capability, throwing an exception if they don't.
     *
     * @param string            $capability   Capability name
     * @param context           $context      Context object
     * @param null|int|stdClass $user         User object or ID (null for current user)
     * @param bool              $doanything   Whether to check for the 'doanything' capability
     * @param string            $errormessage Error message
     * @param string            $stringfile   String file
     *
     * @throws moodle_exception
     */
    public static function require(string $capability, context $context, $user = null, bool $doanything = true, string $errormessage = 'nopermissions', string $stringfile = ''): void
    {
        require_capability($capability, $context, $user, $doanything, $errormessage, $stringfile);
    }

    /**
     * Get all roles assigned to a user in a given context.
     *
     * @param context  $context     the context to check
     * @param null|int $userid      user ID, or null for current user
     * @param bool     $checkparent whether to check parent contexts
     *
     * @return array list of role assignment objects
     */
    public static function getUserRoles(context $context, ?int $userid = null, bool $checkparent = true): array
    {
        try {
            $uid = $userid;

            if ($uid === null) {
                global $USER;
                $uid = (int) $USER->id;
            }

            return get_user_roles($context, $uid, $checkparent);
        } catch (moodle_exception $moodleexception) {
            Debug::traceException($moodleexception);

            return [];
        }
    }
}
