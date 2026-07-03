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

use core\exception\moodle_exception;
use Middag\Moodle\Domain\Task\ScheduledTaskDto as scheduled_task_dto;

/**
 * Contract for the scheduled task service.
 *
 * @api
 */
interface ScheduledServiceInterface
{
    /**
     * List all scheduled tasks in the system.
     *
     * @return scheduled_task_dto[]
     */
    public function list(): array;

    /**
     * Get a specific scheduled task.
     *
     * @throws moodle_exception if the task does not exist
     */
    public function get(string $classname): scheduled_task_dto;

    /**
     * Check if a task exists.
     */
    public function exists(string $classname): bool;

    /**
     * Reset scheduled tasks for a component to their defaults.
     */
    public function resetComponent(string $component): void;

    /**
     * List all scheduled tasks for a component only.
     *
     * @return scheduled_task_dto[]
     */
    public function listByComponent(string $component): array;

    /**
     * Get the next scheduled task that will run after a timestamp.
     */
    public function next(int $timestamp): ?scheduled_task_dto;

    /**
     * Determine if a scheduled task is overdue.
     */
    public function isOverdue(scheduled_task_dto $task, ?int $now = null): bool;

    /**
     * Determine if a scheduled task is customized/overridden.
     */
    public function isCustomized(scheduled_task_dto $task): bool;

    /**
     * Force run a scheduled task via CLI.
     */
    public function runNow(string $classname): bool;

    /**
     * List scheduled tasks that are overdue.
     *
     * @return scheduled_task_dto[]
     */
    public function listOverdue(?int $now = null): array;

    /**
     * List tasks that are disabled.
     *
     * @return scheduled_task_dto[]
     */
    public function listDisabled(): array;

    /**
     * List tasks that were modified by config overrides.
     *
     * @return scheduled_task_dto[]
     */
    public function listCustomized(): array;
}
