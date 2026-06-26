<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Adapter;

use Middag\Moodle\Contract\AuthorizerInterface as authorizer_interface;
use Middag\Moodle\Enum\ContextLevel as context_level;

/**
 * Composed Moodle authorizer — delegates to dedicated adapters.
 *
 * Implements the composed `authorizer_interface` by delegating to
 * `authentication` and `capability` adapters.
 *
 * @internal
 */
class Authorizer implements authorizer_interface
{
    private readonly authentication $authentication;

    private readonly capability $capability;

    public function __construct()
    {
        $this->authentication = new authentication();
        $this->capability = new capability();
    }

    public function requireLogin(?int $courseid = null, bool $autologinguest = true): void
    {
        $this->authentication->requireLogin($courseid, $autologinguest);
    }

    public function isLoggedIn(): bool
    {
        return $this->authentication->isLoggedIn();
    }

    public function isGuest(): bool
    {
        return $this->authentication->isGuest();
    }

    public function requireSesskey(): void
    {
        $this->authentication->requireSesskey();
    }

    public function can(string $capability, context_level $contextlevel = context_level::SYSTEM, int $instanceid = 0, ?int $userid = null): bool
    {
        return $this->capability->can($capability, $contextlevel, $instanceid, $userid);
    }

    public function authorize(string $capability, context_level $contextlevel = context_level::SYSTEM, int $instanceid = 0, ?int $userid = null): void
    {
        $this->capability->authorize($capability, $contextlevel, $instanceid, $userid);
    }
}
