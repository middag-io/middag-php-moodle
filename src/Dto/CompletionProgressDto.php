<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Dto;

use Middag\Framework\Shared\Dto\AbstractDto as abstract_dto;
use stdClass;

/**
 * Data Transfer Object for course completion progress of a user.
 *
 * Flat projection suitable for lists, APIs and UI rendering. Aggregates the
 * counts of trackable vs. completed activities plus a derived percentage and
 * the course-wide completion timestamp.
 *
 * @api
 */
final class CompletionProgressDto extends abstract_dto
{
    public function __construct(
        /** Course ID being reported on. */
        public int $courseid = 0,
        /** User ID being reported on. */
        public int $userid = 0,
        /** Total trackable activities in the course. */
        public int $totalActivities = 0,
        /** Activities completed by the user. */
        public int $completedActivities = 0,
        /** Activities pending (trackable but not yet completed). */
        public int $pendingActivities = 0,
        /** Completion percentage in the 0..100 range. */
        public float $percentage = 0.0,
        /** Unix timestamp of course completion (null if not complete). */
        public ?int $timecompleted = null,
        /** Whether course-level completion is enabled for this course. */
        public bool $enabled = false,
    ) {}

    /**
     * Convenience factory that computes percentage and pending from totals.
     */
    public static function fromCounts(
        int $courseid,
        int $userid,
        int $total,
        int $completed,
        ?int $timecompleted = null,
        bool $enabled = false,
    ): self {
        $total = max(0, $total);
        $completed = max(0, min($completed, $total));
        $pending = max(0, $total - $completed);
        $percentage = $total > 0 ? round(($completed / $total) * 100.0, 2) : 0.0;

        return new self(
            courseid: $courseid,
            userid: $userid,
            totalActivities: $total,
            completedActivities: $completed,
            pendingActivities: $pending,
            percentage: $percentage,
            timecompleted: $timecompleted,
            enabled: $enabled,
        );
    }

    /**
     * Whether the user has fully completed the course.
     */
    public function isComplete(): bool
    {
        return $this->timecompleted !== null && $this->timecompleted > 0;
    }

    /**
     * Whether the user has not completed any tracked activity yet.
     */
    public function isEmpty(): bool
    {
        return $this->completedActivities === 0;
    }

    /**
     * @return array<string, null|bool|float|int>
     */
    public function toArray(): array
    {
        return [
            'courseid' => $this->courseid,
            'userid' => $this->userid,
            'total_activities' => $this->totalActivities,
            'completed_activities' => $this->completedActivities,
            'pending_activities' => $this->pendingActivities,
            'percentage' => $this->percentage,
            'timecompleted' => $this->timecompleted,
            'enabled' => $this->enabled,
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
