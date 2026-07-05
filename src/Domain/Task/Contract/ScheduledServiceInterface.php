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
use Middag\Moodle\Domain\Task\ScheduledTaskDto;

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
     * @return ScheduledTaskDto[]
     */
    public function list(): array;

    /**
     * Get a specific scheduled task.
     *
     * @throws moodle_exception if the task does not exist
     */
    public function get(string $classname): ScheduledTaskDto;

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
     * @return ScheduledTaskDto[]
     */
    public function listByComponent(string $component): array;

    /**
     * Get the next scheduled task that will run after a timestamp.
     */
    public function next(int $timestamp): ?ScheduledTaskDto;

    /**
     * Determine if a scheduled task is overdue.
     */
    public function isOverdue(ScheduledTaskDto $task, ?int $now = null): bool;

    /**
     * Determine if a scheduled task is customized/overridden.
     */
    public function isCustomized(ScheduledTaskDto $task): bool;

    /**
     * Force run a scheduled task via CLI.
     */
    public function runNow(string $classname): bool;

    /**
     * List scheduled tasks that are overdue.
     *
     * @return ScheduledTaskDto[]
     */
    public function listOverdue(?int $now = null): array;

    /**
     * List tasks that are disabled.
     *
     * @return ScheduledTaskDto[]
     */
    public function listDisabled(): array;

    /**
     * List tasks that were modified by config overrides.
     *
     * @return ScheduledTaskDto[]
     */
    public function listCustomized(): array;
}
