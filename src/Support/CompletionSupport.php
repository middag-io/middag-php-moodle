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

use completion_completion;
use completion_info;
use core\exception\moodle_exception;
use dml_exception;
use Exception;
use Middag\Moodle\Dto\CompletionCriteriaDto as completion_criteria_dto;
use Middag\Moodle\Dto\CompletionProgressDto as completion_progress_dto;
use Middag\Moodle\Entity\Completion as completion_entity;
use Middag\Moodle\Entity\CourseCompletion as course_completion_entity;
use Middag\Moodle\Enum\CompletionState as completion_state;
use Middag\Moodle\Enum\CompletionTracking as completion_tracking;
use Middag\Moodle\Shared\Util\Debug as debug;
use stdClass;
use Throwable;

/**
 * Encapsulates Moodle's Course Completion API.
 *
 * Centralises every interaction with `completion_info`, `completion_completion`,
 * `mdl_course_modules_completion`, `mdl_course_completions` and the
 * `COMPLETION_*` constants behind typed, resilient methods. The rest of the
 * framework MUST NOT instantiate `completion_info` directly.
 *
 * Version-compatibility strategy:
 * - All Moodle API calls are wrapped in try/catch and fall back to safe values.
 * - Return types are always framework types (entities, DTOs, enums, scalars).
 * - Raw Moodle objects never cross the boundary of this class.
 * - `completion_info` is instantiated in a single private helper so a future
 *   API rename or signature change only affects one method.
 *
 * @internal
 *
 * @see ADR-201 Moodle boundary consolidation
 * @see ADR-203 Support layer pattern
 */
class CompletionSupport
{
    /**
     * Check if course completion is enabled site-wide.
     *
     * @return bool true if the site setting allows course completion
     */
    public static function isEnabledSite(): bool
    {
        global $CFG;

        try {
            return !empty($CFG->enablecompletion);
        } catch (Throwable $throwable) {
            debug::traceException($throwable);

            return false;
        }
    }

    /**
     * Check if completion is enabled for a given course.
     *
     * @param int $courseid course ID to check
     *
     * @return bool true when completion is enabled both site-wide and for this course
     */
    public static function isEnabledCourse(int $courseid): bool
    {
        if (!self::isEnabledSite() || $courseid <= 0) {
            return false;
        }

        $info = self::infoForCourse($courseid);

        if (!$info instanceof completion_info) {
            return false;
        }

        try {
            return (bool) $info->is_enabled();
        } catch (Throwable $throwable) {
            debug::traceException($throwable);

            return false;
        }
    }

    /**
     * Check if completion is enabled for a specific course module.
     *
     * @param int $courseid course ID that owns the module
     * @param int $cmid     course module ID
     *
     * @return bool true when the module has completion tracking enabled
     */
    public static function isEnabledCm(int $courseid, int $cmid): bool
    {
        $tracking = self::getCmTracking($courseid, $cmid);

        return $tracking instanceof completion_tracking && $tracking->isTracked();
    }

    /**
     * Get the tracking mode configured for a course module.
     *
     * @param int $courseid course ID
     * @param int $cmid     course module ID
     *
     * @return null|completion_tracking tracking mode, or null if the module is not found
     */
    public static function getCmTracking(int $courseid, int $cmid): ?completion_tracking
    {
        if ($courseid <= 0 || $cmid <= 0) {
            return null;
        }

        try {
            $modinfo = get_fast_modinfo($courseid);

            if (!isset($modinfo->cms[$cmid])) {
                return null;
            }

            $cm = $modinfo->cms[$cmid];
            $raw = isset($cm->completion) ? (int) $cm->completion : 0;

            return completion_tracking::resolve($raw);
        } catch (Throwable $throwable) {
            debug::traceException($throwable);

            return null;
        }
    }

    /**
     * Check whether a user has fully completed a course.
     *
     * @param int $courseid course ID to evaluate
     * @param int $userid   user ID to evaluate
     *
     * @return bool true if the course-level completion record reports the user as complete
     */
    public static function isCourseComplete(int $courseid, int $userid): bool
    {
        if ($courseid <= 0 || $userid <= 0) {
            return false;
        }

        $info = self::infoForCourse($courseid);

        if (!$info instanceof completion_info) {
            return false;
        }

        try {
            return (bool) $info->is_course_complete($userid);
        } catch (Throwable $throwable) {
            debug::traceException($throwable);

            return false;
        }
    }

    /**
     * Retrieve course-level completion record for a user.
     *
     * @param int $courseid course ID
     * @param int $userid   user ID
     *
     * @return null|course_completion_entity course completion entity, or null when missing
     */
    public static function getCourseCompletion(int $courseid, int $userid): ?course_completion_entity
    {
        if ($courseid <= 0 || $userid <= 0) {
            return null;
        }

        global $DB;

        try {
            $record = $DB->get_record('course_completions', [
                'userid' => $userid,
                'course' => $courseid,
            ]);

            if (!$record) {
                return null;
            }

            return course_completion_entity::fromRecord($record);
        } catch (dml_exception $dmlexception) {
            debug::traceException($dmlexception);

            return null;
        }
    }

    /**
     * Retrieve activity-level completion state for a specific user/module.
     *
     * @param int $courseid course ID
     * @param int $cmid     course module ID
     * @param int $userid   user ID
     *
     * @return null|completion_entity activity completion entity, or null if the module is not found
     */
    public static function getCmCompletion(int $courseid, int $cmid, int $userid): ?completion_entity
    {
        if ($courseid <= 0 || $cmid <= 0 || $userid <= 0) {
            return null;
        }

        $info = self::infoForCourse($courseid);

        if (!$info instanceof completion_info) {
            return null;
        }

        try {
            $modinfo = get_fast_modinfo($courseid);

            if (!isset($modinfo->cms[$cmid])) {
                return null;
            }

            $cm = $modinfo->cms[$cmid];
            $data = $info->get_data($cm, false, $userid);

            if (!$data) {
                return null;
            }

            $record = $data instanceof stdClass ? $data : (object) (array) $data;

            if (!property_exists($record, 'coursemoduleid')) {
                $record->coursemoduleid = $cmid;
            }
            if (!property_exists($record, 'userid')) {
                $record->userid = $userid;
            }

            return completion_entity::fromRecord($record);
        } catch (Throwable $throwable) {
            debug::traceException($throwable);

            return null;
        }
    }

    /**
     * Aggregate course progress for a user (counts + percentage + completion timestamp).
     *
     * @param int $courseid course ID
     * @param int $userid   user ID
     *
     * @return null|completion_progress_dto progress DTO, or null when the course does not exist or errors out
     */
    public static function getCourseProgress(int $courseid, int $userid): ?completion_progress_dto
    {
        if ($courseid <= 0 || $userid <= 0) {
            return null;
        }

        $enabled = self::isEnabledCourse($courseid);

        if (!$enabled) {
            return completion_progress_dto::fromCounts($courseid, $userid, 0, 0, null, false);
        }

        $info = self::infoForCourse($courseid);

        if (!$info instanceof completion_info) {
            return null;
        }

        try {
            $activities = $info->get_activities();
            $total = is_array($activities) ? count($activities) : 0;
            $completed = 0;

            foreach ($activities as $activity) {
                $data = $info->get_data($activity, false, $userid);

                if (!$data) {
                    continue;
                }

                $state = completion_state::resolve((int) ($data->completionstate ?? 0));

                if ($state->isComplete()) {
                    ++$completed;
                }
            }

            $course_completion = self::getCourseCompletion($courseid, $userid);
            $timecompleted = $course_completion?->get_timecompleted();

            return completion_progress_dto::fromCounts(
                $courseid,
                $userid,
                $total,
                $completed,
                $timecompleted,
                true,
            );
        } catch (Throwable $throwable) {
            debug::traceException($throwable);

            return null;
        }
    }

    /**
     * List every activity-level completion entry for a user in a course.
     *
     * @param int $courseid course ID
     * @param int $userid   user ID
     *
     * @return array<int, completion_entity> indexed by course module ID
     */
    public static function getCourseCmCompletions(int $courseid, int $userid): array
    {
        if ($courseid <= 0 || $userid <= 0) {
            return [];
        }

        $info = self::infoForCourse($courseid);

        if (!$info instanceof completion_info) {
            return [];
        }

        $completions = [];

        try {
            $activities = $info->get_activities();

            foreach ($activities as $activity) {
                $cmid = (int) $activity->id;
                $data = $info->get_data($activity, false, $userid);

                if (!$data) {
                    continue;
                }

                $record = $data instanceof stdClass ? $data : (object) (array) $data;

                if (!property_exists($record, 'coursemoduleid')) {
                    $record->coursemoduleid = $cmid;
                }
                if (!property_exists($record, 'userid')) {
                    $record->userid = $userid;
                }

                $completions[$cmid] = completion_entity::fromRecord($record);
            }
        } catch (Throwable $throwable) {
            debug::traceException($throwable);
        }

        return $completions;
    }

    /**
     * Update the completion state of an activity for a user.
     *
     * Delegates to Moodle's `completion_info::update_state`, which re-evaluates
     * automatic rules when applicable and preserves audit fields.
     *
     * @param int              $courseid course ID
     * @param int              $cmid     course module ID
     * @param int              $userid   user ID
     * @param completion_state $state    target state (use completion_state enum)
     *
     * @return bool true on success, false when the course module cannot be resolved or an error occurs
     */
    public static function updateCmState(
        int $courseid,
        int $cmid,
        int $userid,
        completion_state $state,
    ): bool {
        if ($courseid <= 0 || $cmid <= 0 || $userid <= 0) {
            return false;
        }

        $info = self::infoForCourse($courseid);

        if (!$info instanceof completion_info) {
            return false;
        }

        try {
            $modinfo = get_fast_modinfo($courseid);

            if (!isset($modinfo->cms[$cmid])) {
                return false;
            }

            $cm = $modinfo->cms[$cmid];

            $info->update_state($cm, $state->value, $userid);

            return true;
        } catch (Throwable $throwable) {
            debug::traceException($throwable);

            return false;
        }
    }

    /**
     * Count users tracked for completion within a course (optionally scoped to a group).
     *
     * @param int      $courseid course ID
     * @param null|int $groupid  optional group ID to scope the count
     *
     * @return int number of tracked users, or 0 when the course is unavailable
     */
    public static function getTrackedUsersCount(int $courseid, ?int $groupid = null): int
    {
        if ($courseid <= 0) {
            return 0;
        }

        $info = self::infoForCourse($courseid);

        if (!$info instanceof completion_info) {
            return 0;
        }

        try {
            return (int) $info->get_num_tracked_users('', [], $groupid ?? 0);
        } catch (Throwable $throwable) {
            debug::traceException($throwable);

            return 0;
        }
    }

    /**
     * Retrieve completion criteria for a course.
     *
     * @param int $courseid course ID
     *
     * @return array<int, completion_criteria_dto> criteria indexed by criterion ID
     */
    public static function getCourseCriteria(int $courseid): array
    {
        if ($courseid <= 0) {
            return [];
        }

        global $DB;

        try {
            $records = $DB->get_records('course_completion_criteria', ['course' => $courseid]);
        } catch (dml_exception $dmlexception) {
            debug::traceException($dmlexception);

            return [];
        }

        $criteria = [];

        foreach ($records as $record) {
            $id = isset($record->id) ? (int) $record->id : 0;

            $criteria[$id] = new completion_criteria_dto(
                id: $id > 0 ? $id : null,
                courseid: (int) ($record->course ?? $courseid),
                criteriaType: (string) ($record->criteriatype ?? ''),
                moduleinstance: isset($record->moduleinstance) ? (int) $record->moduleinstance : null,
                courseinstance: isset($record->courseinstance) ? (int) $record->courseinstance : null,
                enrolperiod: isset($record->enrolperiod) ? (int) $record->enrolperiod : null,
                timeend: isset($record->timeend) ? (int) $record->timeend : null,
                gradepass: isset($record->gradepass) ? (float) $record->gradepass : null,
                role: isset($record->role) ? (int) $record->role : null,
            );
        }

        return $criteria;
    }

    /**
     * Mark a user's course completion as started (creates the tracking row).
     *
     * Delegates to `completion_completion::mark_enrolled()` which is idempotent:
     * repeated calls will not create duplicate rows.
     *
     * @param int $courseid course ID
     * @param int $userid   user ID
     *
     * @return bool true on success, false on error
     */
    public static function markCourseEnrolled(int $courseid, int $userid): bool
    {
        if ($courseid <= 0 || $userid <= 0) {
            return false;
        }

        try {
            $tracker = new completion_completion([
                'userid' => $userid,
                'course' => $courseid,
            ]);
            $tracker->mark_enrolled();

            return true;
        } catch (Throwable $throwable) {
            debug::traceException($throwable);

            return false;
        }
    }

    /**
     * Instantiate `completion_info` for a given course ID, caching nothing.
     *
     * Centralised so that any future change in Moodle's completion API (class
     * rename, constructor signature, etc.) requires updating a single method.
     *
     * @param int $courseid course ID
     *
     * @return null|completion_info moodle completion info instance, or null on error
     */
    private static function infoForCourse(int $courseid): ?completion_info
    {
        global $DB;

        try {
            $course = $DB->get_record('course', ['id' => $courseid], '*', IGNORE_MISSING);

            if (!$course) {
                return null;
            }

            return new completion_info($course);
        } catch (dml_exception $dmlexception) {
            debug::traceException($dmlexception);

            return null;
        } catch (moodle_exception $moodleexception) {
            debug::traceException($moodleexception);

            return null;
        } catch (Exception $exception) {
            debug::traceException($exception);

            return null;
        }
    }
}
