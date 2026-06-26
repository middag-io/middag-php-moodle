<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Entity;

use stdClass;

/**
 * Course-wide completion entity (Moodle native).
 *
 * Represents a single row of `mdl_course_completions` — the aggregate
 * completion state of a user for a whole course (enrollment timestamps,
 * completion timestamp, RPL, etc).
 *
 * Distinct from `completion` entity which represents activity-level (cm) completion.
 *
 * @method int         get_userid()
 * @method self        with_userid(int $userid)
 * @method int         get_course()
 * @method self        with_course(int $course)
 * @method int         get_timeenrolled()
 * @method self        with_timeenrolled(int $timeenrolled)
 * @method int         get_timestarted()
 * @method self        with_timestarted(int $timestarted)
 * @method null|int    get_timecompleted()
 * @method self        with_timecompleted(?int $timecompleted)
 * @method null|int    get_reaggregate()
 * @method self        with_reaggregate(?int $reaggregate)
 * @method null|string get_rpl()
 * @method self        with_rpl(?string $rpl)
 * @method null|float  get_rplgrade()
 * @method self        with_rplgrade(?float $rplgrade)
 *
 * @api
 */
class CourseCompletion extends AbstractMoodleEntity
{
    protected int $userid = 0;

    protected int $course = 0;

    protected int $timeenrolled = 0;

    protected int $timestarted = 0;

    protected ?int $timecompleted = null;

    protected ?int $reaggregate = null;

    protected ?string $rpl = null;

    protected ?float $rplgrade = null;

    public static function getTable(): string
    {
        return 'course_completions';
    }

    /**
     * Whether the course has been completed by the user.
     */
    public function isComplete(): bool
    {
        return $this->timecompleted !== null && $this->timecompleted > 0;
    }

    /**
     * Whether the user has at least started engaging with the course.
     */
    public function hasStarted(): bool
    {
        return $this->timestarted > 0;
    }

    /**
     * Whether this completion was granted via Recognition of Prior Learning (RPL).
     */
    public function isRpl(): bool
    {
        return $this->rpl !== null && $this->rpl !== '';
    }

    /**
     * Seconds elapsed between enrolment and completion; null if not complete.
     */
    public function durationToComplete(): ?int
    {
        if (!$this->isComplete()) {
            return null;
        }

        return max(0, $this->timecompleted - $this->timeenrolled);
    }

    /**
     * Factory from Moodle's raw course completion record (mdl_course_completions).
     *
     * @param array|stdClass $record
     */
    public static function fromRecord(array|stdClass $record): static
    {
        $data = (object) $record;

        $entity = new static();

        if (property_exists($data, 'id')) {
            $entity->id = (int) $data->id;
        }
        if (property_exists($data, 'userid')) {
            $entity->userid = (int) $data->userid;
        }
        if (property_exists($data, 'course')) {
            $entity->course = (int) $data->course;
        }
        if (property_exists($data, 'timeenrolled')) {
            $entity->timeenrolled = (int) $data->timeenrolled;
        }
        if (property_exists($data, 'timestarted')) {
            $entity->timestarted = (int) $data->timestarted;
        }
        if (property_exists($data, 'timecompleted') && $data->timecompleted !== null) {
            $entity->timecompleted = (int) $data->timecompleted;
        }
        if (property_exists($data, 'reaggregate') && $data->reaggregate !== null) {
            $entity->reaggregate = (int) $data->reaggregate;
        }
        if (property_exists($data, 'rpl') && $data->rpl !== null) {
            $entity->rpl = (string) $data->rpl;
        }
        if (property_exists($data, 'rplgrade') && $data->rplgrade !== null) {
            $entity->rplgrade = (float) $data->rplgrade;
        }

        return $entity;
    }
}
