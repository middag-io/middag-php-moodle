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

use core\context;
use dml_exception;
use Exception;
use Middag\Framework\Shared\Util\Typing;
use Middag\Moodle\Domain\Group\GroupMemberDto;
use Middag\Moodle\Shared\Util\Debug;
use stdClass;

// File-scope host-library include: runs at autoload, before any test's coverage window.
// @codeCoverageIgnoreStart
global $CFG;

require_once $CFG->dirroot . '/group/lib.php';
// @codeCoverageIgnoreEnd

/**
 * Utility functions for Moodle groups.
 *
 * @api
 */
class GroupSupport
{
    /**
     * Retrieves groups that a user belongs to within a course.
     *
     * @param int $courseid Course ID
     * @param int $userid   User ID
     *
     * @return array<int, stdClass> list of group records
     */
    public static function getGroups(int $courseid, int $userid): array
    {
        global $DB;

        try {
            $sql = 'SELECT g.*
                      FROM {groups_members} gm
                      JOIN {groups} g ON g.id = gm.groupid
                     WHERE g.courseid = :courseid AND gm.userid = :userid';

            return $DB->get_records_sql($sql, ['courseid' => $courseid, 'userid' => $userid]);
        } catch (dml_exception $dmlexception) {
            Debug::traceException($dmlexception);

            return [];
        }
    }

    /**
     * Retrieves options for group selection [name => name].
     *
     * @param null|context $coursecontext optional course context to filter groups
     *
     * @return array<string, string> Map of group name to group name
     */
    public static function getGroupOptions(?context $coursecontext = null): array
    {
        global $DB;

        try {
            $params = [];
            $where = '';

            if (!is_null($coursecontext)) {
                $where = 'WHERE courseid = :courseid';
                $params['courseid'] = $coursecontext->instanceid;
            }

            $sql = sprintf('SELECT MIN(id) as id, name FROM {groups} %s GROUP BY name ORDER BY name', $where);
            $records = $DB->get_records_sql($sql, $params);
        } catch (Exception $exception) {
            Debug::traceException($exception);
            $records = [];
        }

        $groups = [];
        foreach ($records as $record) {
            $groups[$record->name] = $record->name;
        }
        $groups[0] = '-- ' . LangSupport::getString('none') . ' --';

        return array_reverse($groups, true);
    }

    /**
     * Checks if a specific user is a member of a group.
     *
     * @param int $groupid Group ID
     * @param int $userid  User ID
     *
     * @return bool True if member, false otherwise
     */
    public static function isMember(int $groupid, int $userid): bool
    {
        return groups_is_member($groupid, $userid);
    }

    /**
     * Adds a user to a group.
     *
     * @param int $groupid Group ID
     * @param int $userid  User ID
     *
     * @return bool True on success, false otherwise
     */
    public static function addMember(int $groupid, int $userid): bool
    {
        try {
            return groups_add_member($groupid, $userid);
        } catch (Exception $exception) {
            Debug::traceException($exception);

            return false;
        }
    }

    /**
     * Creates a new group in a course.
     *
     * @param int    $courseid    Course ID
     * @param string $groupname   Group name
     * @param string $idnumber    optional idnumber
     * @param string $description optional description (HTML)
     *
     * @return null|int the created group ID or null on failure
     */
    public static function createGroup(int $courseid, string $groupname, string $idnumber = '', string $description = ''): ?int
    {
        $group = new stdClass();
        $group->courseid = $courseid;
        $group->name = $groupname;
        $group->idnumber = $idnumber;
        $group->description = $description;
        $group->descriptionformat = FORMAT_HTML;

        $groupid = groups_create_group($group);

        return Typing::normalizeId($groupid);
    }

    /**
     * Retrieves a group ID by its name within a specific course.
     *
     * @param int    $courseid  Course ID
     * @param string $groupname Group name
     *
     * @return bool|int Group ID or false if not found
     */
    public static function getGroupByName(int $courseid, string $groupname): bool|int
    {
        $id = groups_get_group_by_name($courseid, $groupname);

        return Typing::normalizeIdOrZero($id);
    }

    /**
     * Returns group members as typed DTOs.
     *
     * @return array<int, GroupMemberDto> indexed by user ID
     */
    public static function getMembers(int $groupid): array
    {
        global $DB;

        try {
            $records = $DB->get_records('groups_members', ['groupid' => $groupid]);
            $result = [];

            foreach ($records as $record) {
                $result[(int) $record->userid] = new GroupMemberDto(
                    groupid: (int) $record->groupid,
                    userid: (int) $record->userid,
                    timeadded: (int) $record->timeadded,
                    component: (string) ($record->component ?? ''),
                    itemid: (int) ($record->itemid ?? 0),
                );
            }

            return $result;
        } catch (dml_exception) {
            return [];
        }
    }
}
