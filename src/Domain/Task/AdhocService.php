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

use core\task\adhoc_task;
use Middag\Moodle\Domain\Task\Contract\AdhocServiceInterface;
use Middag\Moodle\Support\TaskSupport;

/**
 * High-level service for creating and managing adhoc tasks.
 *
 * @internal
 */
class AdhocService implements AdhocServiceInterface
{
    /**
     * Constructor.
     *
     * @param TaskSupport $taskSupport
     */
    public function __construct(
        private readonly TaskSupport $taskSupport
    ) {}

    /**
     * Create a new adhoc task instance for a given class.
     *
     * @param class-string<adhoc_task> $classname Fully-qualified adhoc task class name
     * @param array<string, mixed>     $data      Custom task data
     * @param null|int                 $userid    Owner user ID
     *
     * @return adhoc_task
     */
    public function create(string $classname, array $data = [], ?int $userid = null): adhoc_task
    {
        /** @var adhoc_task $task */
        $task = new $classname();
        $task->set_custom_data($data);
        $task->set_userid($userid);

        return $task;
    }

    /**
     * Queue a new adhoc task.
     *
     * @param adhoc_task $task
     * @param bool       $avoidDuplicates Skip if a duplicate is already queued
     *
     * @return bool
     */
    public function queue(adhoc_task $task, bool $avoidDuplicates = false): bool
    {
        return $this->taskSupport->queueAdhoc($task, $avoidDuplicates);
    }

    /**
     * Reschedule or queue.
     *
     * @param adhoc_task $task
     */
    public function rescheduleOrQueue(adhoc_task $task): void
    {
        $this->taskSupport->rescheduleOrQueue($task);
    }

    /**
     * Get queued tasks for a class.
     *
     * @return AdhocTaskDto[]
     */
    public function list(string $classname, bool $failedOnly = false): array
    {
        return $this->taskSupport->getAdhocTasks($classname, $failedOnly);
    }
}
