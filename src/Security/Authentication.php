<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Security;

use Middag\Moodle\Security\Contract\AuthenticationInterface as authentication_interface;
use Middag\Moodle\Support\AuthSupport as auth_support;
use Middag\Moodle\Support\SessionSupport as session_support;

/**
 * Moodle authentication adapter.
 *
 * Delegates session and login checks to Moodle via the boundary support layer.
 *
 * @internal
 */
class Authentication implements authentication_interface
{
    public function requireLogin(?int $courseid = null, bool $autologinguest = true): void
    {
        auth_support::requireLogin($courseid, $autologinguest);
    }

    public function isLoggedIn(): bool
    {
        return auth_support::isLoggedIn();
    }

    public function isGuest(): bool
    {
        return auth_support::isGuest();
    }

    public function requireSesskey(): void
    {
        session_support::requireSesskey();
    }
}
