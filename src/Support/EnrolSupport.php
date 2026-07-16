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

use core\context\course as context_course;
use core\exception\moodle_exception;
use dml_exception;
use Middag\Framework\Shared\Util\Typing;
use Middag\Moodle\Domain\Enrolment\UserEnrolment;
use stdClass;

/**
 * Utility functions for Moodle course enrolments.
 *
 * @api
 */
class EnrolSupport
{
    /**
     * Retrieves a user's enrolment record in a specific course.
     *
     * A user may hold several concurrent enrolments in the same course (e.g. a
     * manual and a cohort-sync instance). This returns only ONE of them — the
     * earliest by user_enrolments.id. The ORDER BY makes that pick deterministic
     * (get_record_sql + IGNORE_MULTIPLE would otherwise return an arbitrary row).
     * Callers that must act on a specific enrolment method should not rely on it.
     *
     * @param int $courseid Course ID
     * @param int $userid   User ID
     *
     * @return null|UserEnrolment the earliest user enrolment entity, or null if none
     *
     * @throws dml_exception if a database error occurs
     */
    public static function getEnrol(int $courseid, int $userid): ?UserEnrolment
    {
        global $DB;

        $sql = 'SELECT ue.*
                FROM {user_enrolments} ue
                JOIN {enrol} e ON e.id = ue.enrolid
                WHERE e.courseid = :courseid AND ue.userid = :userid
                ORDER BY ue.id ASC';

        $record = $DB->get_record_sql($sql, ['courseid' => $courseid, 'userid' => $userid], IGNORE_MULTIPLE);

        return $record ? UserEnrolment::fromRecord($record) : null;
    }

    /**
     * Retrieves all cohort enrolments for a user in a course.
     *
     * @param int $courseid Course ID
     * @param int $userid   User ID
     *
     * @return array<int, stdClass> records with id, name, idnumber from cohort table
     *
     * @throws dml_exception if a database error occurs
     */
    public static function getEnrolCohorts(int $courseid, int $userid): array
    {
        global $DB;

        $sql = "SELECT c.id, c.name, c.idnumber
                FROM {user_enrolments} ue
                JOIN {enrol} e ON e.id = ue.enrolid
                JOIN {cohort} c ON c.id = e.customint1
                WHERE e.enrol = 'cohort' AND e.courseid = :courseid AND ue.userid = :userid
                ORDER BY ue.timecreated DESC";

        $records = $DB->get_records_sql($sql, ['courseid' => $courseid, 'userid' => $userid]);

        foreach ($records as $key => $rec) {
            $records[$key] = Typing::normalizeRecord($rec, ['id' => 'int']);
        }

        return $records;
    }

    /**
     * Checks if a user is currently enrolled in a specific course.
     *
     * @param int $courseid Course ID
     * @param int $userid   User ID
     *
     * @return bool True if the user is enrolled, false otherwise
     */
    public static function userIsEnrolled(int $courseid, int $userid): bool
    {
        return self::getEnrol($courseid, $userid) instanceof UserEnrolment;
    }

    /**
     * Enrols a user into a course with a specific role.
     *
     * Low-level primitive: the role is explicit. Callers that want the default
     * student role use the enrolment domain service, which owns that policy.
     *
     * @param int $courseid Course ID
     * @param int $userid   User ID
     * @param int $roleid   Role ID
     *
     * @return bool True if the user has been successfully enrolled
     *
     * @throws moodle_exception if course or user is not found, or if the manual enrolment plugin is not available
     */
    public static function enrolUser(int $courseid, int $userid, int $roleid): bool
    {
        global $DB, $CFG;

        require_once $CFG->libdir . '/enrollib.php';

        if (!$DB->record_exists('course', ['id' => $courseid])) {
            throw new moodle_exception('course_not_found');
        }

        if (!$DB->record_exists('user', ['id' => $userid])) {
            throw new moodle_exception('user_not_found');
        }

        $enrol = enrol_get_plugin('manual');

        if (!$enrol) {
            throw new moodle_exception('manual_enrol_not_found');
        }

        $instances = enrol_get_instances($courseid, true);
        $manualinstance = null;

        foreach ($instances as $instance) {
            if ($instance->enrol === 'manual') {
                $manualinstance = $instance;

                break;
            }
        }

        if (!$manualinstance) {
            $fields = [
                'courseid' => $courseid,
                'enrol' => 'manual',
                'status' => 0,
            ];
            $instanceid = $DB->insert_record('enrol', $fields);
            $manualinstance = $DB->get_record('enrol', ['id' => $instanceid]);
        }

        // is_enrolled() reports true for ANY enrolment in the course — even a
        // different method or role — so it would skip the specific manual
        // enrolment + role the caller asked for whenever the user already holds
        // some other enrolment. Check the exact requested outcome instead.
        $context = context_course::instance($courseid);
        $alreadyenrolled = $DB->record_exists('user_enrolments', [
            'enrolid' => $manualinstance->id,
            'userid' => $userid,
        ]);
        $hasrole = user_has_role_assignment($userid, $roleid, $context->id);

        if ($alreadyenrolled && $hasrole) {
            return true;
        }

        $enrol->enrol_user(
            $manualinstance,
            $userid,
            $roleid,
            time()
        );

        return true;
    }
}
