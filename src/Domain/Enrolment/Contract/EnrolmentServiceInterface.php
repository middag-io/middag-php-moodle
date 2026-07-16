<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Domain\Enrolment\Contract;

use Middag\Moodle\Domain\Enrolment\EnrolmentDto;

/**
 * Enrolment service contract — typed enrolment operations.
 *
 * Composes enrol_support + user_support + course_support + role_support
 * into a single, cohesive API. Eliminates the need for extensions to
 * manually compose 4 supports for common enrolment operations.
 *
 * @api
 */
interface EnrolmentServiceInterface
{
    /**
     * Default role assigned when enrolling without an explicit role.
     *
     * Moodle's standard student role id. The default lives here (the domain
     * seam) rather than in the low-level EnrolSupport primitive, which now
     * requires an explicit role.
     */
    public const DEFAULT_STUDENT_ROLE_ID = 5;

    /**
     * Enrol a user in a course with a given role.
     *
     * @param int $userid   User ID
     * @param int $courseid Course ID
     * @param int $roleid   Role ID (defaults to the student role)
     *
     * @return bool Whether the enrolment was successful
     */
    public function enrol(int $userid, int $courseid, int $roleid = self::DEFAULT_STUDENT_ROLE_ID): bool;

    /**
     * Check whether a user is enrolled in a course.
     */
    public function isEnrolled(int $userid, int $courseid): bool;

    /**
     * Get the typed enrolment data for a user in a course.
     *
     * @return null|EnrolmentDto Null if user is not enrolled
     */
    public function getEnrolment(int $userid, int $courseid): ?EnrolmentDto;

    /**
     * Get all enrolments for a user across courses.
     *
     * @return array<int, EnrolmentDto> Keyed by course ID
     */
    public function getUserEnrolments(int $userid): array;

    /**
     * Get all enrolled users in a course.
     *
     * @return array<int, EnrolmentDto> Keyed by user ID
     */
    public function getCourseEnrolments(int $courseid): array;

    /**
     * Suspend a user's enrolment in a course.
     */
    public function suspend(int $userid, int $courseid): bool;

    /**
     * Reactivate a suspended enrolment.
     */
    public function reactivate(int $userid, int $courseid): bool;

    /**
     * Get the count of enrolled users in a course.
     *
     * @param bool $activeonly Count only active enrolments
     */
    public function countEnrolled(int $courseid, bool $activeonly = true): int;
}
