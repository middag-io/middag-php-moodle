<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Support;

use core\task\scheduled_task;
use Middag\Moodle\Domain\Task\AdhocTaskDto;
use Middag\Moodle\Domain\Task\RunningTaskDto;
use Middag\Moodle\Domain\Task\ScheduledTaskDto;
use Middag\Moodle\Domain\Task\TaskSummaryDto;
use Middag\Moodle\Support\TaskSupport;
use middag_test_adhoc_task;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(TaskSupport::class)]
final class TaskSupportCoverageTest extends TestCase
{
    private TaskSupport $support;

    protected function setUp(): void
    {
        $this->support = new TaskSupport();

        foreach ([
            '__middag_test_scheduled_task',
            '__middag_test_all_scheduled_tasks',
            '__middag_test_adhoc_tasks',
            '__middag_test_queue_result',
            '__middag_test_rescheduled',
            '__middag_test_next_scheduled',
            '__middag_test_next_adhoc',
            '__middag_test_run_from_cli',
            '__middag_test_ran_adhoc_cli',
            '__middag_test_configured',
            '__middag_test_reset_component',
            '__middag_test_adhoc_summary',
            '__middag_test_running_tasks',
            '__middag_test_running_sort',
        ] as $key) {
            unset($GLOBALS[$key]);
        }
    }

    #[Test]
    public function testGetScheduledTaskMapsTheTask(): void
    {
        $GLOBALS['__middag_test_scheduled_task'] = $this->scheduledTask([
            'component' => 'local_example',
            'lastruntime' => 1000,
            'nextruntime' => 2000,
            'disabled' => true,
            'minute' => '5',
            'customised' => true,
            'faildelay' => 10,
        ]);

        $dto = $this->support->getScheduledTask('\some\task');

        self::assertInstanceOf(ScheduledTaskDto::class, $dto);
        self::assertSame('\core\task\scheduled_task', $dto->classname);
        self::assertSame('local_example', $dto->component);
        self::assertSame(1000, $dto->lastruntime);
        self::assertSame(2000, $dto->nextruntime);
        self::assertTrue($dto->disabled);
        self::assertTrue($dto->customised);
    }

    #[Test]
    public function testGetScheduledTaskReturnsNullWhenMissing(): void
    {
        self::assertNull($this->support->getScheduledTask('\missing'));
    }

    #[Test]
    public function testGetScheduledTasksMapsListAndNullLastRunTime(): void
    {
        $GLOBALS['__middag_test_all_scheduled_tasks'] = [
            $this->scheduledTask(['component' => 'a', 'lastruntime' => 1000]),
            $this->scheduledTask(['component' => 'b', 'lastruntime' => 0]),
        ];

        $dtos = $this->support->getScheduledTasks();

        self::assertCount(2, $dtos);
        self::assertSame(1000, $dtos[0]->lastruntime);
        // lastruntime 0 → null.
        self::assertNull($dtos[1]->lastruntime);
    }

    #[Test]
    public function testGetAdhocTasksMapsRunningAndNonRunningTasks(): void
    {
        $notRunning = new middag_test_adhoc_task();
        $notRunning->comp = 'local_example';
        $notRunning->nextrun = 4000;
        $notRunning->customstr = '{"k":1}';
        $notRunning->faildelay = 3;
        $notRunning->timestarted = null;
        $notRunning->taskid = 5;

        $running = new middag_test_adhoc_task();
        $running->timestarted = 999;
        $running->taskid = null;
        $running->set_userid(9);

        $GLOBALS['__middag_test_adhoc_tasks'] = [$notRunning, $running];

        $dtos = $this->support->getAdhocTasks('\some\adhoc', true);

        self::assertCount(2, $dtos);
        self::assertInstanceOf(AdhocTaskDto::class, $dtos[0]);
        self::assertSame('\middag_test_adhoc_task', $dtos[0]->classname);
        self::assertSame(4000, $dtos[0]->nextruntime);
        self::assertSame('{"k":1}', $dtos[0]->customdata);
        self::assertFalse($dtos[0]->running);
        self::assertSame(5, $dtos[0]->id);
        // Running task: timestarted set → running true; taskid null → id 0; userid set.
        self::assertTrue($dtos[1]->running);
        self::assertSame(0, $dtos[1]->id);
        self::assertSame(9, $dtos[1]->userid);
    }

    #[Test]
    public function testQueueAdhocReturnsTrueWhenQueued(): void
    {
        $GLOBALS['__middag_test_queue_result'] = 12;

        self::assertTrue($this->support->queueAdhoc(new middag_test_adhoc_task(), true));
    }

    #[Test]
    public function testQueueAdhocReturnsFalseWhenNotQueued(): void
    {
        $GLOBALS['__middag_test_queue_result'] = 0;

        self::assertFalse($this->support->queueAdhoc(new middag_test_adhoc_task()));
    }

    #[Test]
    public function testRescheduleOrQueueForwardsTheTask(): void
    {
        $task = new middag_test_adhoc_task();

        $this->support->rescheduleOrQueue($task);

        self::assertSame($task, $GLOBALS['__middag_test_rescheduled']);
    }

    #[Test]
    public function testNextScheduledMapsTheTask(): void
    {
        $GLOBALS['__middag_test_next_scheduled'] = $this->scheduledTask(['component' => 'x', 'nextruntime' => 50]);

        $dto = $this->support->nextScheduled(10);

        self::assertInstanceOf(ScheduledTaskDto::class, $dto);
        self::assertSame(50, $dto->nextruntime);
    }

    #[Test]
    public function testNextScheduledReturnsNullWhenNone(): void
    {
        self::assertNull($this->support->nextScheduled(10));
    }

    #[Test]
    public function testNextAdhocMapsTheTask(): void
    {
        $adhoc = new middag_test_adhoc_task();
        $adhoc->nextrun = 60;
        $GLOBALS['__middag_test_next_adhoc'] = $adhoc;

        $dto = $this->support->nextAdhoc(10, true, '\some\adhoc');

        self::assertInstanceOf(AdhocTaskDto::class, $dto);
        self::assertSame(60, $dto->nextruntime);
    }

    #[Test]
    public function testNextAdhocReturnsNullWhenNone(): void
    {
        self::assertNull($this->support->nextAdhoc(10));
    }

    #[Test]
    public function testRunScheduledFromCliReturnsManagerResult(): void
    {
        $GLOBALS['__middag_test_run_from_cli'] = true;

        self::assertTrue($this->support->runScheduledFromCli($this->scheduledTask([])));
    }

    #[Test]
    public function testRunAdhocFromCliForwardsTaskId(): void
    {
        $this->support->runAdhocFromCli(321);

        self::assertSame(321, $GLOBALS['__middag_test_ran_adhoc_cli']);
    }

    #[Test]
    public function testResetScheduledTasksDelegatesToTheHostApi(): void
    {
        // Must delegate to core\task\manager::reset_scheduled_tasks_for_component,
        // which loads db/tasks.php defaults and respects admin customisations —
        // not re-persist the current DB schedule with an identity write.
        $this->support->resetScheduledTasks('local_example');

        self::assertSame('local_example', $GLOBALS['__middag_test_reset_component'] ?? null);
    }

    #[Test]
    public function testGetAdhocSummaryBuildsSummaryDtos(): void
    {
        $GLOBALS['__middag_test_adhoc_summary'] = [
            'local_example' => [
                '\some\adhoc' => [
                    'count' => 2,
                    'running' => 1,
                    'failed' => 0,
                    'due' => 1,
                    'nextruntime' => 123,
                    'stop' => true,
                ],
            ],
        ];

        $dtos = $this->support->getAdhocSummary();

        self::assertCount(1, $dtos);
        self::assertInstanceOf(TaskSummaryDto::class, $dtos[0]);
        self::assertSame('local_example', $dtos[0]->component);
        self::assertSame('\some\adhoc', $dtos[0]->classname);
        self::assertSame(2, $dtos[0]->count);
        self::assertTrue($dtos[0]->stopped);
    }

    #[Test]
    public function testGetRunningTasksBuildsRunningDtosAndForwardsSort(): void
    {
        $GLOBALS['__middag_test_running_tasks'] = [
            (object) [
                'id' => 1,
                'classname' => '\some\task',
                'type' => 'adhoc',
                'timestarted' => 100,
                'hostname' => 'node-1',
                'pid' => 4242,
                'time' => 50,
            ],
        ];

        $dtos = $this->support->getRunningTasks('timestarted DESC');

        self::assertCount(1, $dtos);
        self::assertInstanceOf(RunningTaskDto::class, $dtos[0]);
        self::assertSame('node-1', $dtos[0]->hostname);
        self::assertSame(4242, $dtos[0]->pid);
        self::assertSame(50, $dtos[0]->elapsed);
        self::assertSame('timestarted DESC', $GLOBALS['__middag_test_running_sort']);
    }

    private function scheduledTask(array $data): scheduled_task
    {
        $task = new scheduled_task();
        $task->data = $data;

        return $task;
    }
}
