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

use cm_info;
use core\context\course as context_course;
use core\exception\moodle_exception;
use core\url as moodle_url;
use dml_exception;
use Exception;
use Middag\Framework\Shared\Util\Typing as typing;
use Middag\Moodle\Entity\Category as category_entity;
use Middag\Moodle\Entity\Course as course_entity;
use Middag\Moodle\Entity\CourseModule as course_module;
use Middag\Moodle\Shared\Util\Debug as debug;
use stdClass;

/**
 * Utility functions for Moodle courses.
 *
 * @internal
 */
class CourseSupport
{
    /**
     * Retrieves a course entity by its ID.
     *
     * @param null|int $courseid   Course ID
     * @param int      $strictness Moodle strictness constant (IGNORE_MISSING, IGNORE_MULTIPLE, MUST_EXIST)
     *
     * @return null|course_entity Course entity or null if not found
     */
    public static function getCourse(?int $courseid, int $strictness = IGNORE_MISSING): ?course_entity
    {
        global $DB;

        if ($courseid === 0 || $courseid === null) {
            return null;
        }

        try {
            $course = $strictness !== IGNORE_MISSING ? get_course($courseid) : $DB->get_record('course', ['id' => $courseid]);

            return $course ? course_entity::fromRecord($course) : null;
        } catch (dml_exception $dmlexception) {
            debug::traceException($dmlexception);

            return null;
        }
    }

    /**
     * Retrieves visible course options as an options list [courseid => label].
     *
     * Uses a single query with JOIN on categories to reduce overhead and
     * avoid multiple calls to core_course_category::get().
     *
     * @return array<int, string> Map of course ID to formatted label
     */
    public static function getCoursesOptions(): array
    {
        global $DB;

        $options = [];

        try {
            $sql = 'SELECT c.id, c.fullname, cc.name AS categoryname
                      FROM {course} c
                      JOIN {course_categories} cc ON cc.id = c.category
                     WHERE c.visible = :visible';

            $records = $DB->get_records_sql($sql, ['visible' => 1]);
            foreach ($records as $record) {
                $id = typing::toInt($record->id);
                $options[$id] = 'ID: ' . $id . ' - ' . typing::toString($record->fullname) . ' - '
                    . LangSupport::getString('category') . ': ' . typing::toString($record->categoryname);
            }
        } catch (dml_exception $dmlexception) {
            debug::traceException($dmlexception);
        }

        return $options;
    }

    /**
     * Retrieves course options indexed by context ID.
     *
     * @return array<int, string> Map of context ID to course label
     */
    public static function getCourseWithContextidOptions(): array
    {
        $options = [];
        $courses = get_courses();
        unset($courses[1]);
        foreach ($courses as $course) {
            $coursecontext = context_course::instance($course->id);
            $contextid = typing::toInt($coursecontext->id);
            $options[$contextid] = 'ID ' . typing::toInt($course->id) . ' - ' . typing::toString($course->fullname);
        }

        return $options;
    }

    /**
     * Retrieves visible course modules within a specific section.
     *
     * @param int $courseid      Course ID
     * @param int $sectionnumber Section number
     *
     * @return array<int, cm_info> List of visible course modules in the section
     */
    public static function getCmsBySection(int $courseid, int $sectionnumber): array
    {
        global $CFG;

        require_once $CFG->dirroot . '/course/format/lib.php';

        try {
            $cms = [];
            $course = get_course($courseid);
            $numsections = course_get_format($course)->get_last_section_number();
            $format = course_get_format($course);
            $modinfo = $format->get_modinfo();
            $section = $modinfo->get_section_info($sectionnumber);

            if (is_null($section)) {
                return [];
            }

            $numsection = $section->section;
            if (!empty($modinfo->sections[$numsection])) {
                foreach ($modinfo->sections[$numsection] as $modnumber) {
                    if ($sectionnumber > $numsections) {
                        continue;
                    }
                    if (!$format->is_section_visible($section)) {
                        continue;
                    }

                    $mod = $modinfo->cms[$modnumber];
                    if ($section->uservisible && $mod->is_visible_on_course_page()) {
                        $cms[] = $mod;
                    }
                }
            }
        } catch (moodle_exception $moodleexception) {
            debug::traceException($moodleexception);

            return [];
        }

        return $cms;
    }

    /**
     * Retrieves courses belonging to a category.
     *
     * @param int  $categoryid Category ID
     * @param bool $includesub whether to include courses from subcategories
     *
     * @return array<int, stdClass> List of course records
     */
    public static function getCoursesFromCategoryid(int $categoryid, bool $includesub = true): array
    {
        global $DB;

        $categories = [$categoryid];

        if ($includesub) {
            $subcategories = [];
            CategorySupport::getSubcategoriesRecursive($categoryid, $subcategories);
            if ($subcategories !== []) {
                $categories = array_merge($categories, $subcategories);
            }
        }

        try {
            [$insql, $params] = $DB->get_in_or_equal($categories, SQL_PARAMS_NAMED);
            $where = 'c.category ' . $insql;

            $sql = 'SELECT c.* FROM {course} c WHERE ' . $where;

            return $DB->get_records_sql($sql, $params);
        } catch (Exception $exception) {
            debug::traceException($exception);

            return [];
        }
    }

    /**
     * Retrieves a course entity by its context ID.
     *
     * This is useful when you have a context ID and need to find the associated course.
     *
     * @param int $contextid  Context ID
     * @param int $strictness Moodle strictness constant (IGNORE_MISSING, IGNORE_MULTIPLE, MUST_EXIST)
     *
     * @return null|course_entity Course entity or null on failure
     */
    public static function getCourseByContextid(int $contextid, int $strictness = IGNORE_MISSING): ?course_entity
    {
        global $DB;

        if ($contextid === 0) {
            return null;
        }

        try {
            // Get the context record
            $context = $DB->get_record('context', ['id' => $contextid], '*', $strictness);

            if (!$context) {
                return null;
            }

            // Verify it's a course context
            if ((int) $context->contextlevel !== CONTEXT_COURSE) {
                return null;
            }

            // Get the course using the instanceid
            $courseid = (int) $context->instanceid;

            return self::getCourse($courseid, $strictness);
        } catch (dml_exception $dmlexception) {
            debug::traceException($dmlexception);

            return null;
        }
    }

    /**
     * Retrieves the course context for a given course ID.
     *
     * @param int $courseid Course ID
     *
     * @return null|context_course Course context or null on failure
     */
    public static function getCourseContext(int $courseid): ?context_course
    {
        if ($courseid === 0) {
            return null;
        }

        try {
            return context_course::instance($courseid);
        } catch (Exception $exception) {
            debug::traceException($exception);

            return null;
        }
    }

    /**
     * Retrieves the URL to view a course.
     *
     * @param int         $courseid  Course ID
     * @param null|int    $sectionid Optional section ID to jump to
     * @param null|string $anchor    Optional URL anchor (e.g., 'module-123')
     *
     * @return moodle_url Course view URL
     */
    public static function getCourseUrl(int $courseid, ?int $sectionid = null, ?string $anchor = null): moodle_url
    {
        $params = ['id' => $courseid];

        if ($sectionid !== null) {
            $params['section'] = $sectionid;
        }

        $url = new moodle_url('/course/view.php', $params);

        if ($anchor !== null) {
            $url->set_anchor($anchor);
        }

        return $url;
    }

    /**
     * Checks if a course is currently visible.
     *
     * @param int $courseid Course ID
     *
     * @return bool True if visible, false otherwise
     */
    public static function isCourseVisible(int $courseid): bool
    {
        $course = self::getCourse($courseid);

        if (!$course instanceof course_entity) {
            return false;
        }

        return $course->visible === 1;
    }

    /**
     * Retrieves all course modules for a specific course.
     *
     * @param int         $courseid Course ID
     * @param null|string $modname  Optional module name filter by module name (e.g., 'forum', 'quiz')
     *
     * @return array<int, stdClass> List of course module records
     */
    public static function getCourseModules(int $courseid, ?string $modname = null): array
    {
        global $DB;

        try {
            $params = ['course' => $courseid];
            $sql = 'SELECT cm.*, m.name as modname
                      FROM {course_modules} cm
                      JOIN {modules} m ON m.id = cm.module
                     WHERE cm.course = :course';

            if ($modname !== null) {
                $sql .= ' AND m.name = :modname';
                $params['modname'] = $modname;
            }

            $sql .= ' ORDER BY cm.section, cm.id';

            return $DB->get_records_sql($sql, $params);
        } catch (dml_exception $dmlexception) {
            debug::traceException($dmlexception);

            return [];
        }
    }

    /**
     * Retrieves the count of enrolled users in a course.
     *
     * @param int      $courseid   Course ID
     * @param bool     $activeonly Whether to count only active enrolments
     * @param null|int $groupid    Optional group ID filter
     *
     * @return int Number of enrolled users
     */
    public static function getEnrolledUsersCount(int $courseid, ?bool $activeonly = true, ?int $groupid = null): int
    {
        global $DB;

        try {
            $context = self::getCourseContext($courseid);

            if (!$context instanceof context_course) {
                return 0;
            }

            $sql = 'SELECT COUNT(DISTINCT u.id)
                      FROM {user} u
                      JOIN {user_enrolments} ue ON ue.userid = u.id
                      JOIN {enrol} e ON e.id = ue.enrolid
                     WHERE e.courseid = :courseid
                       AND u.deleted = 0';

            $params = ['courseid' => $courseid];

            if ($activeonly) {
                $sql .= ' AND ue.status = :active AND e.status = :enabled';
                $params['active'] = ENROL_USER_ACTIVE;
                $params['enabled'] = ENROL_INSTANCE_ENABLED;
            }

            if ($groupid !== null) {
                $sql .= ' AND EXISTS (
                            SELECT 1 FROM {groups_members} gm
                             WHERE gm.userid = u.id
                               AND gm.groupid = :groupid
                        )';
                $params['groupid'] = $groupid;
            }

            return $DB->count_records_sql($sql, $params);
        } catch (dml_exception $dmlexception) {
            debug::traceException($dmlexception);

            return 0;
        }
    }

    /**
     * Retrieves the category entity for a specific course.
     *
     * @param int $courseid Course ID
     *
     * @return null|category_entity Category entity or null on failure
     */
    public static function getCourseCategory(int $courseid): ?category_entity
    {
        global $DB;

        try {
            $course = self::getCourse($courseid);

            if (!$course instanceof course_entity || !isset($course->category)) {
                return null;
            }

            $record = $DB->get_record('course_categories', ['id' => $course->category]);

            return $record ? category_entity::fromRecord($record) : null;
        } catch (dml_exception $dmlexception) {
            debug::traceException($dmlexception);

            return null;
        }
    }

    /**
     * Checks if a specific user is enrolled in a course.
     *
     * @param int $courseid Course ID
     * @param int $userid   User ID
     *
     * @return bool True if enrolled, false otherwise
     */
    public static function isUserEnrolled(int $courseid, int $userid): bool
    {
        try {
            $context = self::getCourseContext($courseid);

            if (!$context instanceof context_course) {
                return false;
            }

            return is_enrolled($context, $userid);
        } catch (Exception $exception) {
            debug::traceException($exception);

            return false;
        }
    }

    /**
     * Retrieves the course format identifier (e.g., 'topics', 'weeks', 'social').
     *
     * @param int $courseid Course ID
     *
     * @return null|string Course format or null on failure
     */
    public static function getCourseFormat(int $courseid): ?string
    {
        $course = self::getCourse($courseid);

        if (!$course instanceof course_entity || !isset($course->format)) {
            return null;
        }

        return $course->format;
    }

    /**
     * Retrieves the URL for the course group view tool.
     *
     * @return moodle_url the group view tool URL
     */
    public static function courseGroupViewurl(): moodle_url
    {
        return new moodle_url('/local/middag/tool/trilha/view.php');
    }

    /**
     * Returns course modules as typed entities.
     *
     * @return array<int, course_module> indexed by cmid
     */
    public static function getCourseModulesTyped(int $courseid, ?string $modname = null): array
    {
        global $DB;

        try {
            $params = ['course' => $courseid];
            $where = 'course = :course';

            if ($modname !== null) {
                $module = $DB->get_record('modules', ['name' => $modname], 'id');
                if (!$module) {
                    return [];
                }
                $params['module'] = (int) $module->id;
                $where .= ' AND module = :module';
            }

            $records = $DB->get_records_select('course_modules', $where, $params);
            $result = [];

            foreach ($records as $record) {
                $result[(int) $record->id] = course_module::fromRecord($record);
            }

            return $result;
        } catch (dml_exception) {
            return [];
        }
    }
}
