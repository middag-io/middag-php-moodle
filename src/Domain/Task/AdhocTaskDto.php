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
 * Data Transfer Object for adhoc tasks.
 *
 * Represents a snapshot of an adhoc task, independent of the Moodle core API.
 *
 * @internal
 */
final class AdhocTaskDto extends abstract_dto
{
    /**
     * Constructor.
     *
     * @param string   $classname
     * @param string   $component
     * @param null|int $userid
     * @param int      $nextruntime
     * @param string   $customdata
     * @param int      $faildelay
     * @param bool     $running
     * @param int      $id
     */
    public function __construct(
        /** Fully qualified class name of the task. */
        public string $classname,
        /** Component that owns the task (e.g. 'core_course'). */
        public string $component,
        /** Optional user ID associated with the task. */
        public ?int $userid,
        /** Unix timestamp for next scheduled execution. */
        public int $nextruntime,
        /** Serialized custom data provided by the task. */
        public string $customdata,
        /** Amount of time (seconds) the task waits before retry. */
        public int $faildelay,
        /** Whether the task is currently running or not. */
        public bool $running,
        /** Task unique database ID. */
        public int $id,
    ) {}

    /**
     * Convert the DTO to an associative array.
     *
     * @return array<string, null|bool|int|string>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'classname' => $this->classname,
            'component' => $this->component,
            'userid' => $this->userid,
            'nextruntime' => $this->nextruntime,
            'running' => $this->running,
        ];
    }

    /**
     * Convert the DTO to a stdClass.
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
