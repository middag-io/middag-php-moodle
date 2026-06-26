<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Service;

use core\task\adhoc_task;
use Middag\Moodle\Contract\AdhocServiceInterface as adhoc_service_interface;
use Middag\Moodle\Dto\AdhocTaskDto as adhoc_task_dto;
use Middag\Moodle\Support\TaskSupport as task_support;

/**
 * High-level service for creating and managing adhoc tasks.
 *
 * @internal
 */
class AdhocService implements adhoc_service_interface
{
    /**
     * Constructor.
     *
     * @param task_support $taskSupport
     */
    public function __construct(
        private readonly task_support $taskSupport
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
     * @return adhoc_task_dto[]
     */
    public function list(string $classname, bool $failedOnly = false): array
    {
        return $this->taskSupport->getAdhocTasks($classname, $failedOnly);
    }
}
