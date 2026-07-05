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

use Middag\Moodle\Security\Contract\AuthenticationInterface;
use Middag\Moodle\Support\AuthSupport;
use Middag\Moodle\Support\SessionSupport;

/**
 * Moodle authentication adapter.
 *
 * Delegates session and login checks to Moodle via the boundary support layer.
 *
 * @internal
 */
class Authentication implements AuthenticationInterface
{
    public function requireLogin(?int $courseid = null, bool $autologinguest = true): void
    {
        AuthSupport::requireLogin($courseid, $autologinguest);
    }

    public function isLoggedIn(): bool
    {
        return AuthSupport::isLoggedIn();
    }

    public function isGuest(): bool
    {
        return AuthSupport::isGuest();
    }

    public function requireSesskey(): void
    {
        SessionSupport::requireSesskey();
    }
}
