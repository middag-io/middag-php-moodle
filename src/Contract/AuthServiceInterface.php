<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Contract;

use core\url as moodle_url;
use stdClass;

/**
 * Authentication Service Contract.
 *
 * Defines methods for handling JWT authentication, session management,
 * and secure redirects.
 *
 * @api
 */
interface AuthServiceInterface
{
    /**
     * Initializes the authentication process based on the request token.
     */
    public static function init(): void;

    /**
     * Checks if the user is already logged in.
     */
    public static function authCheck(): void;

    /**
     * Generates a login URL with a JWT token.
     *
     * @param stdClass $user    Moodle user object
     * @param int      $expires Expiration in seconds
     *
     * @return moodle_url
     */
    public static function generateLoginUrl(stdClass $user, int $expires = 120): moodle_url;

    /**
     * Redirects the user safely.
     */
    public static function redirect(): void;
}
