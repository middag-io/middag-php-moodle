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

use core\exception\moodle_exception;
use Middag\Moodle\Contract\ScheduledServiceInterface as scheduled_service_interface;
use Middag\Moodle\Dto\ScheduledTaskDto as scheduled_task_dto;
use Middag\Moodle\Kernel\Config\ComponentContext;
use Middag\Moodle\Support\TaskSupport as task_support;

/**
 * High-level service for managing scheduled tasks.
 *
 * This service centralizes all business logic related to scheduled tasks,
 * and provides a clean API for modules, extensions and facades.
 *
 * @internal
 */
final readonly class ScheduledService implements scheduled_service_interface
{
    /**
     * Constructor.
     *
     * @param task_support $task_wrapper
     */
    public function __construct(
        private task_support $task_wrapper
    ) {}

    /* ============================================================================
     * FETCHING TASKS
     * ==========================================================================*/

    /**
     * List all scheduled tasks in the system.
     *
     * @return scheduled_task_dto[]
     */
    public function list(): array
    {
        return $this->task_wrapper->getScheduledTasks();
    }

    /**
     * Get a specific scheduled task.
     *
     * @throws moodle_exception if the task does not exist
     */
    public function get(string $classname): scheduled_task_dto
    {
        $task = $this->task_wrapper->getScheduledTask($classname);

        if (!$task instanceof scheduled_task_dto) {
            throw new moodle_exception('scheduled_task_not_found', ComponentContext::name(), '', $classname);
        }

        return $task;
    }

    /**
     * Check if a task exists.
     */
    public function exists(string $classname): bool
    {
        return $this->task_wrapper->getScheduledTask($classname) instanceof scheduled_task_dto;
    }

    /* ============================================================================
     * COMPONENT OPERATIONS
     * ==========================================================================*/

    /**
     * Reset scheduled tasks for a component to their defaults.
     *
     * This is useful when deploying new versions of plugins that modify db/tasks.php.
     */
    public function resetComponent(string $component): void
    {
        $this->task_wrapper->resetScheduledTasks($component);
    }

    /**
     * List all scheduled tasks for a component only.
     *
     * @return scheduled_task_dto[]
     */
    public function listByComponent(string $component): array
    {
        return array_filter(
            $this->list(),
            fn (scheduled_task_dto $task): bool => $task->component === $component
        );
    }

    /* ============================================================================
     * SCHEDULED TASK RUNTIME OPERATIONS
     * ==========================================================================*/

    /**
     * Get the next scheduled task that will run after a timestamp.
     */
    public function next(int $timestamp): ?scheduled_task_dto
    {
        return $this->task_wrapper->nextScheduled($timestamp);
    }

    /**
     * Determine if a scheduled task is overdue.
     */
    public function isOverdue(scheduled_task_dto $task, ?int $now = null): bool
    {
        $now ??= time();

        return $task->nextruntime !== null && $task->nextruntime < $now;
    }

    /**
     * Determine if a scheduled task is customized/overridden.
     */
    public function isCustomized(scheduled_task_dto $task): bool
    {
        return $task->customised;
    }

    /**
     * Force run a scheduled task via CLI.
     *
     * Useful for admin screens or automation panels.
     */
    public function runNow(string $classname): bool
    {
        $dto = $this->get($classname);

        // We need the real Moodle task object, but wrapper returns DTO.
        // So we call the Moodle API directly for THIS operation only:
        // (You may wrap this in an internal adapter later if preferred.)
        $task = new ('\\' . ltrim($dto->classname, '\\'));

        return $this->task_wrapper->runScheduledFromCli($task);
    }

    /* ============================================================================
     * FILTERING HELPERS
     * ==========================================================================*/

    /**
     * List scheduled tasks that are overdue.
     *
     * @return scheduled_task_dto[]
     */
    public function listOverdue(?int $now = null): array
    {
        $now ??= time();

        return array_filter(
            $this->list(),
            fn (scheduled_task_dto $task): bool => $this->isOverdue($task, $now)
        );
    }

    /**
     * List tasks that are disabled.
     *
     * @return scheduled_task_dto[]
     */
    public function listDisabled(): array
    {
        return array_filter(
            $this->list(),
            fn (scheduled_task_dto $task): bool => $task->disabled
        );
    }

    /**
     * List tasks that were modified by config overrides.
     *
     * @return scheduled_task_dto[]
     */
    public function listCustomized(): array
    {
        return array_filter(
            $this->list(),
            fn (scheduled_task_dto $task): bool => $task->customised
        );
    }
}
