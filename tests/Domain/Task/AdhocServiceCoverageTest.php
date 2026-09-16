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
use Middag\Framework\Kernel\Contract\ComponentNameResolverInterface;
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
        $service = new AdhocService($this->createStub(TaskSupport::class), $this->componentResolver());

        $task = $service->create('middag_test_adhoc_task', ['foo' => 'bar'], 42);

        self::assertInstanceOf(adhoc_task::class, $task);
        self::assertSame(42, $task->get_userid());
        self::assertEquals((object) ['foo' => 'bar'], $task->get_custom_data());
    }

    #[Test]
    public function testCreateDefaultsToEmptyDataAndNullUserid(): void
    {
        $service = new AdhocService($this->createStub(TaskSupport::class), $this->componentResolver());

        $task = $service->create('middag_test_adhoc_task');

        self::assertNull($task->get_userid());
        self::assertSame([], $task->get_custom_data());
    }

    /**
     * Found live: every AsyncCommandTask dispatch triggered Moodle's
     * "Component not set and the class namespace does not match a valid
     * component" debugging() call — nothing ever called set_component() on
     * the queued task. create() now resolves the consuming plugin's own
     * frankenstyle component via ComponentNameResolverInterface (the same
     * mechanism the framework already uses to classify native vs
     * third-party code) and sets it before the task is queued.
     *
     * middag_test_adhoc_task overrides get_component() to return its own
     * fixture property, so this uses a plain adhoc_task subclass that
     * inherits the real (stubbed) set_component()/get_component() pair —
     * see tests/bootstrap.php.
     */
    #[Test]
    public function testCreateSetsTheComponentFromTheResolver(): void
    {
        if (!class_exists('middag_test_plain_adhoc_task', false)) {
            eval('class middag_test_plain_adhoc_task extends \core\task\adhoc_task { public function execute(): void {} public function get_name(): string { return "plain"; } }');
        }

        $service = new AdhocService($this->createStub(TaskSupport::class), $this->componentResolver('local_middag'));

        $task = $service->create('middag_test_plain_adhoc_task');

        self::assertSame('local_middag', $task->get_component());
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

        $service = new AdhocService($support, $this->componentResolver());

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

        $service = new AdhocService($support, $this->componentResolver());

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

        $service = new AdhocService($support, $this->componentResolver());

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

        $service = new AdhocService($support, $this->componentResolver());

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

        $service = new AdhocService($support, $this->componentResolver());

        self::assertSame([], $service->list('some_class'));
    }

    private function componentResolver(string $component = 'local_test'): ComponentNameResolverInterface
    {
        $resolver = $this->createStub(ComponentNameResolverInterface::class);
        $resolver->method('nativeComponent')->willReturn($component);

        return $resolver;
    }
}
