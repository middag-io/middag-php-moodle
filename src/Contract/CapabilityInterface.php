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

use Middag\Framework\Exception\MiddagAuthorizationException as middag_authorization_exception;
use Middag\Moodle\Enum\ContextLevel as context_level;

/**
 * Capability contract — permission checking.
 *
 * Responsible for verifying whether a user has a specific capability
 * in a given context. Implementations delegate to the platform's
 * access control mechanism.
 *
 * @api
 */
interface CapabilityInterface
{
    /**
     * Check if a user has a capability.
     *
     * @param string        $capability   capability identifier (e.g. 'moodle/course:view')
     * @param context_level $contextlevel context level
     * @param int           $instanceid   instance ID for the context (e.g. course ID)
     * @param null|int      $userid       user ID, or null for current user
     */
    public function can(string $capability, context_level $contextlevel = context_level::SYSTEM, int $instanceid = 0, ?int $userid = null): bool;

    /**
     * Require a capability, throwing an exception if not met.
     *
     * @param string        $capability   capability identifier
     * @param context_level $contextlevel context level
     * @param int           $instanceid   instance ID for the context
     * @param null|int      $userid       user ID, or null for current user
     *
     * @throws middag_authorization_exception
     */
    public function authorize(string $capability, context_level $contextlevel = context_level::SYSTEM, int $instanceid = 0, ?int $userid = null): void;
}
