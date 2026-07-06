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

use core\exception\moodle_exception;
use Middag\Moodle\Domain\Task\ScheduledService;
use Middag\Moodle\Domain\Task\ScheduledTaskDto;
use Middag\Moodle\Support\TaskSupport;
use middag_test_runnow_scheduled_task;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * ScheduledService centralises scheduled-task business logic, delegating raw
 * lookups to TaskSupport (mocked here) and applying filtering/overdue logic on
 * ScheduledTaskDto snapshots. runNow() instantiates the class named in the DTO
 * and forwards it to TaskSupport::runScheduledFromCli(); the
 * `middag_test_runnow_scheduled_task` fixture (tests/stubs/areas/domain2-task.php)
 * is a real scheduled_task subclass satisfying that parameter type.
 *
 * @internal
 */
#[CoversClass(ScheduledService::class)]
final class ScheduledServiceCoverageTest extends TestCase
{
    #[Test]
    public function testListDelegatesToTaskSupport(): void
    {
        $dto = $this->dto();

        $support = $this->createMock(TaskSupport::class);
        $support->expects(self::once())->method('getScheduledTasks')->willReturn([$dto]);

        self::assertSame([$dto], (new ScheduledService($support))->list());
    }

    #[Test]
    public function testGetReturnsTheTaskWhenFound(): void
    {
        $dto = $this->dto();

        $support = $this->createMock(TaskSupport::class);
        $support->expects(self::once())
            ->method('getScheduledTask')
            ->with('\core\task\cron')
            ->willReturn($dto);

        self::assertSame($dto, (new ScheduledService($support))->get('\core\task\cron'));
    }

    #[Test]
    public function testGetThrowsWhenTheTaskDoesNotExist(): void
    {
        $support = $this->createMock(TaskSupport::class);
        $support->method('getScheduledTask')->willReturn(null);

        $this->expectException(moodle_exception::class);

        (new ScheduledService($support))->get('\missing');
    }

    #[Test]
    public function testExistsIsTrueWhenTheTaskIsPresent(): void
    {
        $support = $this->createMock(TaskSupport::class);
        $support->method('getScheduledTask')->willReturn($this->dto());

        self::assertTrue((new ScheduledService($support))->exists('\core\task\cron'));
    }

    #[Test]
    public function testExistsIsFalseWhenTheTaskIsAbsent(): void
    {
        $support = $this->createMock(TaskSupport::class);
        $support->method('getScheduledTask')->willReturn(null);

        self::assertFalse((new ScheduledService($support))->exists('\missing'));
    }

    #[Test]
    public function testResetComponentDelegatesToTaskSupport(): void
    {
        $support = $this->createMock(TaskSupport::class);
        $support->expects(self::once())->method('resetScheduledTasks')->with('local_example');

        (new ScheduledService($support))->resetComponent('local_example');
    }

    #[Test]
    public function testListByComponentFiltersOnComponent(): void
    {
        $core = $this->dto(component: 'core');
        $mine = $this->dto(component: 'local_example');

        $support = $this->createMock(TaskSupport::class);
        $support->method('getScheduledTasks')->willReturn([$core, $mine]);

        $result = (new ScheduledService($support))->listByComponent('local_example');

        self::assertSame([1 => $mine], $result);
    }

    #[Test]
    public function testNextDelegatesToTaskSupport(): void
    {
        $dto = $this->dto();

        $support = $this->createMock(TaskSupport::class);
        $support->expects(self::once())->method('nextScheduled')->with(500)->willReturn($dto);

        self::assertSame($dto, (new ScheduledService($support))->next(500));
    }

    #[Test]
    public function testNextReturnsNullWhenNoTaskIsDue(): void
    {
        $support = $this->createMock(TaskSupport::class);
        $support->method('nextScheduled')->willReturn(null);

        self::assertNull((new ScheduledService($support))->next(500));
    }

    #[Test]
    public function testIsOverdueIsTrueWhenNextRunTimeIsBeforeNow(): void
    {
        $service = new ScheduledService($this->createStub(TaskSupport::class));

        self::assertTrue($service->isOverdue($this->dto(nextruntime: 100), 200));
    }

    #[Test]
    public function testIsOverdueIsFalseWhenNextRunTimeIsAtOrAfterNow(): void
    {
        $service = new ScheduledService($this->createStub(TaskSupport::class));

        self::assertFalse($service->isOverdue($this->dto(nextruntime: 300), 200));
    }

    #[Test]
    public function testIsOverdueDefaultsNowToCurrentTime(): void
    {
        $service = new ScheduledService($this->createStub(TaskSupport::class));

        // A next run time far in the past is overdue relative to time().
        self::assertTrue($service->isOverdue($this->dto(nextruntime: 1)));
    }

    #[Test]
    public function testIsCustomizedReflectsTheDtoFlag(): void
    {
        $service = new ScheduledService($this->createStub(TaskSupport::class));

        self::assertTrue($service->isCustomized($this->dto(customised: true)));
        self::assertFalse($service->isCustomized($this->dto(customised: false)));
    }

    #[Test]
    public function testRunNowInstantiatesTheTaskAndDelegatesToTaskSupport(): void
    {
        $dto = $this->dto(classname: '\middag_test_runnow_scheduled_task');

        $support = $this->createMock(TaskSupport::class);
        $support->method('getScheduledTask')->willReturn($dto);
        $support->expects(self::once())
            ->method('runScheduledFromCli')
            ->with(self::isInstanceOf(middag_test_runnow_scheduled_task::class))
            ->willReturn(true);

        self::assertTrue((new ScheduledService($support))->runNow('\middag_test_runnow_scheduled_task'));
    }

    #[Test]
    public function testListOverdueFiltersToOverdueTasks(): void
    {
        $overdue = $this->dto(classname: '\overdue', nextruntime: 100);
        $future = $this->dto(classname: '\future', nextruntime: 999);

        $support = $this->createMock(TaskSupport::class);
        $support->method('getScheduledTasks')->willReturn([$overdue, $future]);

        $result = (new ScheduledService($support))->listOverdue(200);

        self::assertSame([0 => $overdue], $result);
    }

    #[Test]
    public function testListOverdueDefaultsNowToCurrentTime(): void
    {
        $overdue = $this->dto(nextruntime: 1);

        $support = $this->createMock(TaskSupport::class);
        $support->method('getScheduledTasks')->willReturn([$overdue]);

        self::assertSame([0 => $overdue], (new ScheduledService($support))->listOverdue());
    }

    #[Test]
    public function testListDisabledFiltersToDisabledTasks(): void
    {
        $on = $this->dto(classname: '\on', disabled: false);
        $off = $this->dto(classname: '\off', disabled: true);

        $support = $this->createMock(TaskSupport::class);
        $support->method('getScheduledTasks')->willReturn([$on, $off]);

        self::assertSame([1 => $off], (new ScheduledService($support))->listDisabled());
    }

    #[Test]
    public function testListCustomizedFiltersToCustomisedTasks(): void
    {
        $plain = $this->dto(classname: '\plain', customised: false);
        $custom = $this->dto(classname: '\custom', customised: true);

        $support = $this->createMock(TaskSupport::class);
        $support->method('getScheduledTasks')->willReturn([$plain, $custom]);

        self::assertSame([1 => $custom], (new ScheduledService($support))->listCustomized());
    }

    private function dto(
        string $classname = '\core\task\cron',
        string $component = 'core',
        int $nextruntime = 1_000,
        bool $disabled = false,
        bool $customised = false,
    ): ScheduledTaskDto {
        return new ScheduledTaskDto(
            classname: $classname,
            component: $component,
            lastruntime: null,
            nextruntime: $nextruntime,
            disabled: $disabled,
            minute: '*',
            hour: '*',
            day: '*',
            month: '*',
            dayofweek: '*',
            customised: $customised,
            faildelay: 0,
        );
    }
}
