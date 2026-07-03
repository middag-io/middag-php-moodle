<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Domain\Task;

use Middag\Framework\Shared\Dto\AbstractDto as abstract_dto;
use stdClass;

/**
 * Data Transfer Object for scheduled tasks.
 *
 * Represents a configured and persistent scheduled task.
 *
 * @internal
 */
final class ScheduledTaskDto extends abstract_dto
{
    public function __construct(
        /** Fully qualified class name of the task. */
        public string $classname,
        /** Component that owns the task. */
        public string $component,
        /** Unix timestamp when task last ran, null if never executed. */
        public ?int $lastruntime,
        /** Unix timestamp for the next execution. */
        public int $nextruntime,
        /** Whether the task is disabled. */
        public bool $disabled,
        /** Cron-like schedule expression: minute field. */
        public string $minute,
        /** Cron-like schedule expression: hour field. */
        public string $hour,
        /** Cron-like schedule expression: day field. */
        public string $day,
        /** Cron-like schedule expression: month field. */
        public string $month,
        /** Cron-like schedule expression: day-of-week field. */
        public string $dayofweek,
        /** Whether the task has been customised via config overrides. */
        public bool $customised,
        /** Delay applied after a failed run. */
        public int $faildelay,
    ) {}

    /**
     * Convert DTO to associative array.
     *
     * @return array<string, null|bool|int|string>
     */
    public function toArray(): array
    {
        return [
            'classname' => $this->classname,
            'component' => $this->component,
            'lastruntime' => $this->lastruntime,
            'nextruntime' => $this->nextruntime,
            'disabled' => $this->disabled,
        ];
    }

    /**
     * Convert DTO to stdClass.
     */
    public function toObject(): stdClass
    {
        $obj = new stdClass();
        foreach ($this->toArray() as $key => $value) {
            $obj->{$key} = $value;
        }

        return $obj;
    }
}
