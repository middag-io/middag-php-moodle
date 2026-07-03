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
 * Data Transfer Object for running tasks.
 *
 * Represents both scheduled and adhoc tasks that are currently executing.
 *
 * @internal
 */
final class RunningTaskDto extends abstract_dto
{
    public function __construct(
        /** Unique numeric ID of the task record. */
        public int $id,
        /** Fully qualified class name. */
        public string $classname,
        /** Either "scheduled" or "adhoc". */
        public string $type,
        /** Unix timestamp when task started. */
        public int $timestarted,
        /** Hostname where the task is running. */
        public string $hostname,
        /** Process ID of the running task. */
        public int $pid,
        /** Elapsed time in seconds since start. */
        public int $elapsed,
    ) {}

    /**
     * Convert DTO to associative array.
     *
     * @return array<string, int|string>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'classname' => $this->classname,
            'type' => $this->type,
            'timestarted' => $this->timestarted,
            'hostname' => $this->hostname,
            'pid' => $this->pid,
            'elapsed' => $this->elapsed,
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
