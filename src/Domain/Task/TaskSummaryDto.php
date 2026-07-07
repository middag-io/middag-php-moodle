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

use Middag\Framework\Shared\Dto\AbstractDto;
use stdClass;

/**
 * Summary of adhoc tasks aggregated by component and classname.
 *
 * @internal
 */
final class TaskSummaryDto extends AbstractDto
{
    public function __construct(
        /** Frankenstyle component name (e.g. 'core_course'). */
        public string $component,
        /** Fully qualified classname of the task. */
        public string $classname,
        /** Total number of pending tasks of this type. */
        public int $count,
        /** Number of tasks currently running. */
        public int $running,
        /** Number of failed tasks. */
        public int $failed,
        /** Number of overdue tasks (nextruntime <= now). */
        public int $due,
        /** Timestamp for next available execution. */
        public int $nextruntime,
        /** Whether tasks of this class have been stopped (attempts = 0). */
        public bool $stopped,
    ) {}

    /**
     * Convert DTO to associative array.
     *
     * @return array<string, bool|int|string>
     */
    public function toArray(): array
    {
        return [
            'component' => $this->component,
            'classname' => $this->classname,
            'count' => $this->count,
            'running' => $this->running,
            'failed' => $this->failed,
            'due' => $this->due,
            'nextruntime' => $this->nextruntime,
            'stopped' => $this->stopped,
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
