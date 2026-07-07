<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Domain\Completion;

use Middag\Framework\Shared\Dto\AbstractDto;
use stdClass;

/**
 * Data Transfer Object for a single course completion criterion.
 *
 * Represents one row of `mdl_course_completion_criteria` — a single rule that
 * contributes to whether a user is considered to have completed a course.
 * The criteria type string follows Moodle's `COMPLETION_CRITERIA_TYPE_*`
 * constants (e.g. 'self', 'date', 'activity', 'duration', 'grade', 'role').
 *
 * Using a DTO (not an enum) for `criteria_type` is intentional: Moodle allows
 * third-party plugins to register new criteria types; constraining to a fixed
 * enum would break forward compatibility.
 *
 * @api
 */
final class CompletionCriteriaDto extends AbstractDto
{
    public function __construct(
        /** Criterion row ID (null for new). */
        public ?int $id = null,
        /** Course ID this criterion belongs to. */
        public int $courseid = 0,
        /** Criterion type label (see `COMPLETION_CRITERIA_TYPE_*`). */
        public string $criteriaType = '',
        /** Course module ID, when the criterion is activity-completion based. */
        public ?int $moduleinstance = null,
        /** Required course ID, when the criterion depends on another course. */
        public ?int $courseinstance = null,
        /** Required enrolment period in seconds (for duration-based criteria). */
        public ?int $enrolperiod = null,
        /** Deadline timestamp (for date-based criteria). */
        public ?int $timeend = null,
        /** Required grade (for grade-based criteria). */
        public ?float $gradepass = null,
        /** Required role ID (for role-based criteria). */
        public ?int $role = null,
    ) {}

    /**
     * Convenience flag: whether this criterion involves a course module.
     */
    public function isActivityBased(): bool
    {
        return $this->moduleinstance !== null && $this->moduleinstance > 0;
    }

    /**
     * Convenience flag: whether this criterion involves another course.
     */
    public function isCourseBased(): bool
    {
        return $this->courseinstance !== null && $this->courseinstance > 0;
    }

    /**
     * @return array<string, null|float|int|string>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'courseid' => $this->courseid,
            'criteria_type' => $this->criteriaType,
            'moduleinstance' => $this->moduleinstance,
            'courseinstance' => $this->courseinstance,
            'enrolperiod' => $this->enrolperiod,
            'timeend' => $this->timeend,
            'gradepass' => $this->gradepass,
            'role' => $this->role,
        ];
    }

    public function toObject(): stdClass
    {
        $obj = new stdClass();
        foreach ($this->toArray() as $key => $value) {
            $obj->{$key} = $value;
        }

        return $obj;
    }
}
