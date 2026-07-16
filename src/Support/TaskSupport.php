<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Support;

use core\task\adhoc_task;
use core\task\manager as core_task_manager;
use core\task\scheduled_task;
use Middag\Moodle\Domain\Task\AdhocTaskDto;
use Middag\Moodle\Domain\Task\RunningTaskDto;
use Middag\Moodle\Domain\Task\ScheduledTaskDto;
use Middag\Moodle\Domain\Task\TaskSummaryDto;

/**
 * Utility wrapper for Moodle's Task API (scheduled and adhoc tasks).
 *
 * This class provides a stable API for interacting with Moodle tasks,
 * converting core entities into framework-specific DTOs.
 *
 * Converts all Moodle entities to DTOs and provides
 * a stable, dependency-safe API for the framework.
 *
 * @api
 */
class TaskSupport
{
    /* ============================================================================
     * SCHEDULED TASKS
     * ==========================================================================*/

    /**
     * Retrieves a specific scheduled task by its class name.
     *
     * @param string $classname the task class name
     *
     * @return null|ScheduledTaskDto the task DTO or null if not found
     */
    public function getScheduledTask(string $classname): ?ScheduledTaskDto
    {
        $task = core_task_manager::get_scheduled_task($classname);

        return $task ? $this->mapScheduled($task) : null;
    }

    /**
     * Retrieves all scheduled tasks defined in the system.
     *
     * @return ScheduledTaskDto[] list of scheduled task DTOs
     */
    public function getScheduledTasks(): array
    {
        $list = core_task_manager::get_all_scheduled_tasks();

        return array_map(
            fn (scheduled_task $task): ScheduledTaskDto => $this->mapScheduled($task),
            $list
        );
    }

    /* ============================================================================
     * ADHOC TASKS
     * ==========================================================================*/

    /**
     * Retrieves pending adhoc tasks for a specific task class.
     *
     * @param string $classname  the task class name
     * @param bool   $failedOnly whether to only retrieve failed tasks
     *
     * @return AdhocTaskDto[] list of adhoc task DTOs
     */
    public function getAdhocTasks(string $classname, bool $failedOnly = false): array
    {
        $list = core_task_manager::get_adhoc_tasks($classname, $failedOnly);

        return array_map(
            fn (adhoc_task $task): AdhocTaskDto => $this->mapAdhoc($task),
            $list
        );
    }

    /**
     * Queues a new adhoc task for execution.
     *
     * @param adhoc_task $task          the adhoc task instance
     * @param bool       $checkExisting whether to avoid queuing if an identical task exists
     *
     * @return bool True if queued successfully
     */
    public function queueAdhoc(adhoc_task $task, bool $checkExisting = false): bool
    {
        return (bool) core_task_manager::queue_adhoc_task($task, $checkExisting);
    }

    /**
     * Reschedules an existing adhoc task or queues it if not present.
     *
     * @param adhoc_task $task the adhoc task instance
     */
    public function rescheduleOrQueue(adhoc_task $task): void
    {
        core_task_manager::reschedule_or_queue_adhoc_task($task);
    }

    /* ============================================================================
     * NEXT EXECUTION HELPERS
     * ==========================================================================*/

    /**
     * Retrieves the next scheduled task due for execution.
     *
     * @param int $timestamp the reference timestamp
     *
     * @return null|ScheduledTaskDto the next task DTO or null
     */
    public function nextScheduled(int $timestamp): ?ScheduledTaskDto
    {
        $task = core_task_manager::get_next_scheduled_task($timestamp);

        return $task ? $this->mapScheduled($task) : null;
    }

    /**
     * Retrieves the next adhoc task due for execution.
     *
     * @param int         $timestamp   the reference timestamp
     * @param null|bool   $checkLimits whether to check concurrency limits
     * @param null|string $classname   optional task class filter
     *
     * @return null|AdhocTaskDto the next task DTO or null
     */
    public function nextAdhoc(int $timestamp, ?bool $checkLimits = true, ?string $classname = null): ?AdhocTaskDto
    {
        $task = core_task_manager::get_next_adhoc_task($timestamp, $checkLimits, $classname);

        return $task ? $this->mapAdhoc($task) : null;
    }

    /* ============================================================================
     * CLI EXECUTION
     * ==========================================================================*/

    /**
     * Runs a scheduled task from the command line interface.
     *
     * @param scheduled_task $task the task instance
     *
     * @return bool True on success
     */
    public function runScheduledFromCli(scheduled_task $task): bool
    {
        return core_task_manager::run_from_cli($task);
    }

    /**
     * Runs an adhoc task from the command line interface by its ID.
     *
     * @param int $taskid the task record ID
     */
    public function runAdhocFromCli(int $taskid): void
    {
        core_task_manager::run_adhoc_from_cli($taskid);
    }

    /**
     * Reset scheduled tasks for a component to their defaults from db/tasks.php.
     *
     * @param string $component Component name (e.g., 'local_example')
     */
    public function resetScheduledTasks(string $component): void
    {
        // Delegate to the real host API. The previous loop re-persisted each
        // task's CURRENT schedule (set_minute(get_minute()) is an identity
        // write), never reading db/tasks.php, so a plugin upgrade that changed
        // a task's cron never took effect and admin-customised tasks were
        // touched. reset_scheduled_tasks_for_component() loads defaults from
        // db/tasks.php, respects customisations, and upserts/deletes correctly.
        core_task_manager::reset_scheduled_tasks_for_component($component);
    }

    /* ============================================================================
     * SUMMARY AND RUNNING
     * ==========================================================================*/

    /**
     * Retrieves a summary of all pending adhoc tasks.
     *
     * @return TaskSummaryDto[] list of task summary DTOs
     */
    public function getAdhocSummary(): array
    {
        $summary = core_task_manager::get_adhoc_tasks_summary();

        $out = [];

        foreach ($summary as $component => $classes) {
            foreach ($classes as $classname => $info) {
                $out[] = new TaskSummaryDto(
                    component: $component,
                    classname: $classname,
                    count: $info['count'],
                    running: $info['running'],
                    failed: $info['failed'],
                    due: $info['due'],
                    nextruntime: $info['nextruntime'],
                    stopped: $info['stop'],
                );
            }
        }

        return $out;
    }

    /**
     * Retrieves a list of currently running tasks.
     *
     * @param string $sort SQL sort order
     *
     * @return RunningTaskDto[] list of running task DTOs
     */
    public function getRunningTasks(string $sort = ''): array
    {
        $items = core_task_manager::get_running_tasks($sort);

        return array_map(
            fn ($record): RunningTaskDto => new RunningTaskDto(
                id: $record->id,
                classname: $record->classname,
                type: $record->type,
                timestarted: (int) $record->timestarted,
                hostname: (string) $record->hostname,
                pid: (int) $record->pid,
                elapsed: (int) $record->time,
            ),
            $items
        );
    }

    /* ============================================================================
     * MAPPERS → Convert task objects into DTOs
     * ==========================================================================*/

    /**
     * Maps a Moodle scheduled task object to a DTO.
     *
     * @param scheduled_task $task the task object
     *
     * @return ScheduledTaskDto the task DTO
     */
    private function mapScheduled(scheduled_task $task): ScheduledTaskDto
    {
        $lastruntime = $task->get_last_run_time();

        return new ScheduledTaskDto(
            classname: '\\' . $task::class,
            component: $task->get_component(),
            lastruntime: $lastruntime ? (int) $lastruntime : null,
            nextruntime: (int) $task->get_next_run_time(),
            disabled: (bool) $task->get_disabled(),
            minute: $task->get_minute(),
            hour: $task->get_hour(),
            day: $task->get_day(),
            month: $task->get_month(),
            dayofweek: $task->get_day_of_week(),
            customised: (bool) $task->is_customised(),
            faildelay: (int) $task->get_fail_delay()
        );
    }

    /**
     * Maps a Moodle adhoc task object to a DTO.
     *
     * @param adhoc_task $task the task object
     *
     * @return AdhocTaskDto the task DTO
     */
    private function mapAdhoc(adhoc_task $task): AdhocTaskDto
    {
        return new AdhocTaskDto(
            classname: '\\' . $task::class,
            component: $task->get_component(),
            userid: $task->get_userid(),
            nextruntime: $task->get_next_run_time(),
            customdata: $task->get_custom_data_as_string(),
            faildelay: $task->get_fail_delay(),
            running: $task->get_timestarted() !== null,
            id: $task->get_id() ?? 0,
        );
    }
}
