<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Domain\Task;

use core\task\adhoc_task;
use Middag\Moodle\Domain\Task\AdhocService;
use Middag\Moodle\Domain\Task\AdhocTaskDto;
use Middag\Moodle\Support\TaskSupport;
use middag_test_adhoc_task;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * AdhocService builds adhoc task instances and delegates queue/list operations
 * to TaskSupport. TaskSupport is a concrete wrapper over core\task\manager, so
 * it is replaced with a mock to observe delegation without a Moodle runtime.
 * `middag_test_adhoc_task` (tests/stubs/support/msg-file.php) is a real
 * adhoc_task subclass used as the class-string argument to create().
 *
 * @internal
 */
#[CoversClass(AdhocService::class)]
final class AdhocServiceCoverageTest extends TestCase
{
    #[Test]
    public function testCreateBuildsTheTaskWithCustomDataAndUserid(): void
    {
        $service = new AdhocService($this->createStub(TaskSupport::class));

        $task = $service->create('middag_test_adhoc_task', ['foo' => 'bar'], 42);

        self::assertInstanceOf(adhoc_task::class, $task);
        self::assertSame(42, $task->get_userid());
        self::assertEquals((object) ['foo' => 'bar'], $task->get_custom_data());
    }

    #[Test]
    public function testCreateDefaultsToEmptyDataAndNullUserid(): void
    {
        $service = new AdhocService($this->createStub(TaskSupport::class));

        $task = $service->create('middag_test_adhoc_task');

        self::assertNull($task->get_userid());
        self::assertSame([], $task->get_custom_data());
    }

    #[Test]
    public function testQueueDelegatesToTaskSupportAndReturnsItsResult(): void
    {
        $task = new middag_test_adhoc_task();

        $support = $this->createMock(TaskSupport::class);
        $support->expects(self::once())
            ->method('queueAdhoc')
            ->with($task, true)
            ->willReturn(true);

        $service = new AdhocService($support);

        self::assertTrue($service->queue($task, true));
    }

    #[Test]
    public function testQueueDefaultsAvoidDuplicatesToFalse(): void
    {
        $task = new middag_test_adhoc_task();

        $support = $this->createMock(TaskSupport::class);
        $support->expects(self::once())
            ->method('queueAdhoc')
            ->with($task, false)
            ->willReturn(false);

        $service = new AdhocService($support);

        self::assertFalse($service->queue($task));
    }

    #[Test]
    public function testRescheduleOrQueueDelegatesToTaskSupport(): void
    {
        $task = new middag_test_adhoc_task();

        $support = $this->createMock(TaskSupport::class);
        $support->expects(self::once())
            ->method('rescheduleOrQueue')
            ->with($task);

        $service = new AdhocService($support);

        $service->rescheduleOrQueue($task);
    }

    #[Test]
    public function testListDelegatesToTaskSupportAndReturnsTheDtos(): void
    {
        $dto = new AdhocTaskDto('\c', 'core', null, 0, '{}', 0, false, 1);

        $support = $this->createMock(TaskSupport::class);
        $support->expects(self::once())
            ->method('getAdhocTasks')
            ->with('some_class', true)
            ->willReturn([$dto]);

        $service = new AdhocService($support);

        self::assertSame([$dto], $service->list('some_class', true));
    }

    #[Test]
    public function testListDefaultsFailedOnlyToFalse(): void
    {
        $support = $this->createMock(TaskSupport::class);
        $support->expects(self::once())
            ->method('getAdhocTasks')
            ->with('some_class', false)
            ->willReturn([]);

        $service = new AdhocService($support);

        self::assertSame([], $service->list('some_class'));
    }
}
