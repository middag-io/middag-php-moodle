<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Domain\Enrolment;

use Middag\Moodle\Domain\Enrolment\Enum\EnrolmentStatus;

/**
 * Composed enrolment data — user + course + enrolment state.
 *
 * Returned by enrolment_service to provide a complete, typed view
 * of a user's enrolment without requiring callers to compose
 * multiple supports manually.
 *
 * @api
 */
final readonly class EnrolmentDto
{
    public function __construct(
        /** User ID. */
        public int $userid,
        /** Course ID. */
        public int $courseid,
        /** Enrolment instance ID. */
        public int $enrolid,
        /** User enrolment ID. */
        public int $user_enrolment_id,
        /** Enrolment method (e.g. 'manual', 'self', 'cohort'). */
        public string $enrol_method,
        /** Enrolment status. */
        public EnrolmentStatus $status,
        /** Role ID assigned in this enrolment. */
        public int $roleid,
        /** Enrolment start timestamp (0 = no restriction). */
        public int $timestart,
        /** Enrolment end timestamp (0 = no restriction). */
        public int $timeend,
        /** When this enrolment was created. */
        public int $timecreated,
        /** When this enrolment was last modified. */
        public int $timemodified,
    ) {}

    /**
     * Whether the enrolment is currently active.
     */
    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    /**
     * Whether the enrolment has a time limit.
     */
    public function hasTimeLimit(): bool
    {
        return $this->timeend > 0;
    }

    /**
     * Whether the enrolment has expired (if time-limited).
     */
    public function isExpired(?int $now = null): bool
    {
        if (!$this->hasTimeLimit()) {
            return false;
        }

        return ($now ?? time()) > $this->timeend;
    }

    /**
     * Convert to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'userid' => $this->userid,
            'courseid' => $this->courseid,
            'enrolid' => $this->enrolid,
            'user_enrolment_id' => $this->user_enrolment_id,
            'enrol_method' => $this->enrol_method,
            'status' => $this->status->value,
            'roleid' => $this->roleid,
            'timestart' => $this->timestart,
            'timeend' => $this->timeend,
            'timecreated' => $this->timecreated,
            'timemodified' => $this->timemodified,
        ];
    }
}
