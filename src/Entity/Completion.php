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

use Middag\Moodle\Enum\CompletionState as completion_state;
use stdClass;

/**
 * Activity completion entity (Moodle native).
 *
 * Represents a single row of `mdl_course_modules_completion` — the completion
 * state of a specific user for a specific course module.
 *
 * @method int      get_coursemoduleid()
 * @method self     with_coursemoduleid(int $coursemoduleid)
 * @method int      get_userid()
 * @method self     with_userid(int $userid)
 * @method int      get_viewed()
 * @method self     with_viewed(int $viewed)
 * @method null|int get_overrideby()
 * @method self     with_overrideby(?int $overrideby)
 *
 * @api
 */
class Completion extends AbstractMoodleEntity
{
    protected int $coursemoduleid = 0;

    protected int $userid = 0;

    /**
     * Raw completion state (mapped to completion_state enum via accessor).
     */
    protected int $completionstate = 0;

    protected int $viewed = 0;

    protected ?int $overrideby = null;

    /**
     * Table name for the completion records.
     */
    public static function getTable(): string
    {
        return 'course_modules_completion';
    }

    /**
     * Get the typed completion state.
     */
    public function getState(): completion_state
    {
        return completion_state::resolve($this->completionstate);
    }

    /**
     * Set the completion state from the typed enum.
     */
    public function withState(completion_state $state): self
    {
        $this->completionstate = $state->value;

        return $this;
    }

    /**
     * Whether the activity is considered completed.
     */
    public function isComplete(): bool
    {
        return $this->getState()->isComplete();
    }

    /**
     * Whether completion was marked by an override (admin/teacher action).
     */
    public function isOverridden(): bool
    {
        return $this->overrideby !== null && $this->overrideby > 0;
    }

    /**
     * Whether the user has viewed the activity.
     */
    public function hasViewed(): bool
    {
        return $this->viewed > 0;
    }

    /**
     * Factory from Moodle's raw completion data.
     *
     * Accepts the objects returned by `completion_info::get_data()` or raw DB
     * records from `course_modules_completion`, applying safe field coercion.
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

        if (property_exists($data, 'coursemoduleid')) {
            $entity->coursemoduleid = (int) $data->coursemoduleid;
        } elseif (property_exists($data, 'cmid')) {
            $entity->coursemoduleid = (int) $data->cmid;
        }

        if (property_exists($data, 'userid')) {
            $entity->userid = (int) $data->userid;
        }

        if (property_exists($data, 'completionstate')) {
            $entity->completionstate = (int) $data->completionstate;
        }

        if (property_exists($data, 'viewed')) {
            $entity->viewed = (int) $data->viewed;
        }

        if (property_exists($data, 'overrideby') && $data->overrideby !== null) {
            $entity->overrideby = (int) $data->overrideby;
        }

        if (property_exists($data, 'timemodified')) {
            $entity->timemodified = (int) $data->timemodified;
        }

        return $entity;
    }
}
