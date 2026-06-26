<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Enum;

/**
 * Permission check types.
 *
 * Defines the strategy used to validate a user's access rights.
 *
 * @internal
 */
enum PermissionType: string
{
    /** Check a Moodle Capability string (e.g., 'moodle/course:view') */
    case CAPABILITY = 'capability';

    /** Check if the user has a specific Role ID in the context */
    case ROLE = 'role';

    /** Check if the user is enrolled in the course/context */
    case ENROLMENT = 'enrolment';

    /** Check if the user is the owner (creator) of the resource */
    case OWNER = 'owner';

    /** Check if the user is logged in (guest check) */
    case LOGIN = 'login';

    /** Check against a custom callback logic */
    case CALLBACK = 'callback';
}
