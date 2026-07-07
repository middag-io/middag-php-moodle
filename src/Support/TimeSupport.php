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
use core_date;
use DateTimeZone;

/**
 * Time and timezone wrapper for Moodle's Time API.
 *
 * Encapsulates timezone conversion, user-aware date formatting and timestamp
 * utilities. Isolates direct usage of userdate(), usertime(), core_date and
 * the global $CFG timezone settings.
 *
 * @api
 */
class TimeSupport
{
    /**
     * Returns the current Unix timestamp.
     *
     * @return int current time as Unix timestamp
     */
    public static function now(): int
    {
        return time();
    }

    /**
     * Formats a timestamp for display in the user's timezone and locale.
     *
     * Wraps Moodle's userdate() which handles timezone conversion
     * and locale-aware formatting.
     *
     * @param int         $timestamp Unix timestamp
     * @param string      $format    strftime format string (empty = Moodle default)
     * @param null|string $timezone  timezone identifier (null = user default, '99' = user default in Moodle convention)
     * @param bool        $fixday    strip leading zero from day
     * @param bool        $fixhour   strip leading zero from hour
     *
     * @return string formatted date string
     */
    public static function userdate(
        int $timestamp,
        string $format = '',
        ?string $timezone = null,
        bool $fixday = true,
        bool $fixhour = true,
    ): string {
        $tz = $timezone ?? 99;

        return userdate($timestamp, $format, $tz, $fixday, $fixhour);
    }

    /**
     * Converts a timestamp from server time to user time.
     *
     * @param int         $timestamp Unix timestamp in server timezone
     * @param null|string $timezone  target timezone (null = user default)
     *
     * @return int adjusted Unix timestamp
     */
    public static function usertime(int $timestamp, ?string $timezone = null): int
    {
        $tz = $timezone ?? 99;

        return usertime($timestamp, $tz);
    }

    /**
     * Retrieves the server's configured timezone.
     *
     * @return string timezone identifier (e.g. 'America/Sao_Paulo')
     */
    public static function serverTimezone(): string
    {
        return core_date::get_server_timezone();
    }

    /**
     * Retrieves a user's timezone.
     *
     * @param null|int $userid user ID (null = current user)
     *
     * @return string timezone identifier (e.g. 'America/Sao_Paulo')
     */
    public static function userTimezone(?int $userid = null): string
    {
        if ($userid !== null) {
            $user = core_user::get_user($userid);

            if ($user) {
                return core_date::get_user_timezone($user);
            }
        }

        return core_date::get_user_timezone();
    }

    /**
     * Creates a Unix timestamp from date components in a specific timezone.
     *
     * @param int         $year     the year
     * @param int         $month    the month (1-12)
     * @param int         $day      the day of month (1-31)
     * @param int         $hour     the hour (0-23)
     * @param int         $minute   the minute (0-59)
     * @param int         $second   the second (0-59)
     * @param null|string $timezone timezone identifier (null = user default)
     * @param bool        $applydst whether to apply DST correction
     *
     * @return int Unix timestamp
     */
    public static function makeTimestamp(
        int $year,
        int $month = 1,
        int $day = 1,
        int $hour = 0,
        int $minute = 0,
        int $second = 0,
        ?string $timezone = null,
        bool $applydst = true,
    ): int {
        $tz = $timezone ?? 99;

        return make_timestamp($year, $month, $day, $hour, $minute, $second, $tz, $applydst);
    }

    /**
     * Returns a DateTimeZone object for the server timezone.
     *
     * @return DateTimeZone the server timezone
     */
    public static function serverTimezoneObject(): DateTimeZone
    {
        return core_date::get_server_timezone_object();
    }

    /**
     * Returns a DateTimeZone object for a user's timezone.
     *
     * @param null|int $userid user ID (null = current user)
     *
     * @return DateTimeZone the user timezone
     */
    public static function userTimezoneObject(?int $userid = null): DateTimeZone
    {
        if ($userid !== null) {
            $user = core_user::get_user($userid);

            if ($user) {
                return core_date::get_user_timezone_object($user);
            }
        }

        return core_date::get_user_timezone_object();
    }
}
