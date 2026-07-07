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

use context_course;
use core\context;
use core\user as core_user;
use dml_exception;
use Exception;
use Middag\Framework\Shared\Util\Typing;
use Middag\Moodle\Domain\Role\RoleAssignment;
use Middag\Moodle\Shared\Util\Debug;
use stdClass;
use Throwable;

/**
 * Utility functions for Moodle roles and assignments.
 *
 * @api
 */
class RoleSupport
{
    /**
     * Retrieves assignable roles as an options list [roleid => label].
     *
     * @param null|context       $coursecontext optional course context to filter assignable roles
     * @param array<int, string> $excluderoles  List of role shortnames to exclude
     *
     * @return array<int|string, string> Map of role ID to localized name
     */
    public static function getRoleOptions(?context $coursecontext = null, array $excluderoles = ['guest']): array
    {
        $roles = get_all_roles();
        foreach ($roles as $key => $role) {
            if (in_array($role->shortname, $excluderoles, true)) {
                unset($roles[$key]);
            }
        }
        $roles = role_fix_names($roles, $coursecontext, ROLENAME_ALIAS, true);

        if ($coursecontext instanceof context) {
            $roles = get_assignable_roles($coursecontext, ROLENAME_BOTH);
        }

        $roles[0] = '-- ' . LangSupport::getString('none') . ' --';

        return array_reverse($roles, true);
    }

    /**
     * Retrieves roles options for the current course.
     *
     * @return array<int|string, string> Map of role ID to localized name
     */
    public static function getRolesOptions(): array
    {
        global $COURSE;

        $context = ContextSupport::course(Typing::toInt($COURSE->id));
        $roles = get_assignable_roles($context, ROLENAME_BOTH);
        $roles[0] = LangSupport::getString('none');

        return array_reverse($roles, true);
    }

    /**
     * Checks if a user has a teacher role in any enrolled course.
     *
     * @param int $userid User ID
     *
     * @return bool True if teacher, false otherwise
     */
    public static function isteacher(int $userid): bool
    {
        global $DB;

        $sql = "SELECT DISTINCT u.*
                FROM {user} u
                JOIN {user_enrolments} ue ON ue.userid = u.id
                JOIN {enrol} e ON e.id = ue.enrolid
                JOIN {role_assignments} ra ON ra.userid = u.id
                JOIN {context} ct ON ct.id = ra.contextid AND ct.contextlevel = 50
                JOIN {course} c ON c.id = ct.instanceid AND e.courseid = c.id
                JOIN {role} r ON r.id = ra.roleid AND r.shortname IN ('editingteacher', 'teacher')
                WHERE e.status = 0 AND u.id = :userid
                ORDER BY u.id";

        try {
            return $DB->record_exists_sql($sql, ['userid' => $userid]);
        } catch (dml_exception $dmlexception) {
            Debug::traceException($dmlexception);

            return false;
        }
    }

    /**
     * Retrieves a teacher user record for a specific course.
     *
     * Tries to find role by shortname prioritizing 'editingteacher' and,
     * if not found, falls back to 'teacher'. Avoids using numeric indexes from
     * get_all_roles() as they can vary between installations.
     *
     * @param int $courseid Course ID
     *
     * @return bool|stdClass the user record or false if none found
     *
     * @throws dml_exception if a database error occurs
     */
    public static function getTeacher(int $courseid): bool|stdClass
    {
        global $DB;

        $context = ContextSupport::course($courseid);

        // Try to find the teacher role (editingteacher -> teacher) via database.
        $role = $DB->get_record('role', ['shortname' => 'editingteacher']);
        if (!$role) {
            $role = $DB->get_record('role', ['shortname' => 'teacher']);
        }

        if (!$role) {
            return false;
        }

        // Retrieve users with the role in the course context.
        $assignments = get_users_from_role_on_context($role, $context);
        if ($assignments === []) {
            return false;
        }

        $assignment = array_shift($assignments);
        if (!isset($assignment->userid)) {
            return false;
        }

        // Load full user record.
        try {
            return core_user::get_user(Typing::toInt($assignment->userid));
        } catch (Throwable $throwable) {
            $code = Typing::toInt($throwable->getCode()) ?? 0;
            Debug::traceException(new Exception($throwable->getMessage(), $code));

            return false;
        }
    }

    /**
     * Returns the primary teacher role assignment for a course.
     *
     * @return null|RoleAssignment the teacher assignment or null
     */
    public static function getTeacherAssignment(int $courseid): ?RoleAssignment
    {
        global $DB;

        try {
            $ctx = context_course::instance($courseid, IGNORE_MISSING);
            if (!$ctx) {
                return null;
            }

            $sql = "SELECT ra.*
                      FROM {role_assignments} ra
                      JOIN {role} r ON r.id = ra.roleid
                     WHERE ra.contextid = :contextid
                       AND r.archetype IN ('editingteacher', 'teacher')
                  ORDER BY ra.timemodified ASC";

            $record = $DB->get_record_sql($sql, ['contextid' => $ctx->id], IGNORE_MULTIPLE);

            return $record ? RoleAssignment::fromRecord($record) : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Returns all teacher role assignments for a course.
     *
     * @return array<int, RoleAssignment> indexed by user ID
     */
    public static function getTeachers(int $courseid): array
    {
        global $DB;

        try {
            $ctx = context_course::instance($courseid, IGNORE_MISSING);
            if (!$ctx) {
                return [];
            }

            $sql = "SELECT ra.*
                      FROM {role_assignments} ra
                      JOIN {role} r ON r.id = ra.roleid
                     WHERE ra.contextid = :contextid
                       AND r.archetype IN ('editingteacher', 'teacher')
                  ORDER BY ra.timemodified ASC";

            $result = [];
            foreach ($DB->get_records_sql($sql, ['contextid' => $ctx->id]) as $record) {
                $result[(int) $record->userid] = RoleAssignment::fromRecord($record);
            }

            return $result;
        } catch (Throwable) {
            return [];
        }
    }
}
