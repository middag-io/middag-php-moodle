<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Domain\Task\Contract;

use core\task\adhoc_task;
use Middag\Moodle\Domain\Task\AdhocTaskDto as adhoc_task_dto;

/**
 * Contract for the adhoc task service.
 *
 * @api
 */
interface AdhocServiceInterface
{
    /**
     * Create a new adhoc task instance for a given class.
     *
     * @param class-string<adhoc_task> $classname Fully-qualified adhoc task class name
     * @param array<string, mixed>     $data      Custom task data
     * @param null|int                 $userid    Owner user ID
     *
     * @return adhoc_task
     */
    public function create(string $classname, array $data = [], ?int $userid = null): adhoc_task;

    /**
     * Queue a new adhoc task.
     *
     * @param adhoc_task $task
     * @param bool       $avoidDuplicates Skip if a duplicate is already queued
     *
     * @return bool
     */
    public function queue(adhoc_task $task, bool $avoidDuplicates = false): bool;

    /**
     * Reschedule or queue.
     *
     * @param adhoc_task $task
     */
    public function rescheduleOrQueue(adhoc_task $task): void;

    /**
     * Get queued tasks for a class.
     *
     * @return adhoc_task_dto[]
     */
    public function list(string $classname, bool $failedOnly = false): array;
}
