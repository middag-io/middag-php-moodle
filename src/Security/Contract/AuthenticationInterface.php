<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Security\Contract;

/**
 * Authentication contract — session and login.
 *
 * Responsible for verifying that the current user has an active session.
 * Implementations delegate to the platform's authentication mechanism.
 *
 * @api
 */
interface AuthenticationInterface
{
    /**
     * Require the user to be logged in.
     *
     * @param null|int $courseid       course ID, or null for system-level login
     * @param bool     $autologinguest whether guest auto-login is allowed
     */
    public function requireLogin(?int $courseid = null, bool $autologinguest = true): void;

    /**
     * Check if there is an active session.
     */
    public function isLoggedIn(): bool;

    /**
     * Check if the current user is a guest.
     */
    public function isGuest(): bool;

    /**
     * Require a valid sesskey for the current request (CSRF protection).
     *
     * Reads the token from the request parameters only (Moodle's
     * `required_param('sesskey')`), so it never sees a token sent as a header
     * or inside a JSON body. Prefer {@see self::confirmSesskey()} for the
     * transport-agnostic check.
     */
    public function requireSesskey(): void;

    /**
     * Compare a caller-supplied token against the current session's sesskey.
     *
     * Transport-agnostic: the caller decides where the token came from (header,
     * body, query). Comparison is constant-time. Returns false when there is no
     * session sesskey yet, so a missing token never passes.
     */
    public function confirmSesskey(string $token): bool;
}
