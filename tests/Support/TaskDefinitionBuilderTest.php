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

use Middag\Framework\Bus\Attribute\Schedule;
use Middag\Moodle\Support\TaskDefinitionBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(TaskDefinitionBuilder::class)]
final class TaskDefinitionBuilderTest extends TestCase
{
    #[Test]
    public function buildReturnsCorrectStructure(): void
    {
        $schedule = new Schedule(
            minute: '*/5',
            hour: '3',
            day: '1',
            month: '*',
            dayOfWeek: '*',
        );

        $definition = TaskDefinitionBuilder::build($schedule, '\App\Task\SyncTask');

        $this->assertSame('\App\Task\SyncTask', $definition['classname']);
        $this->assertSame(0, $definition['blocking']);
        $this->assertSame('*/5', $definition['minute']);
        $this->assertSame('3', $definition['hour']);
        $this->assertSame('1', $definition['day']);
        $this->assertSame('*', $definition['month']);
        $this->assertSame('*', $definition['dayofweek']);
        $this->assertSame(0, $definition['disabled']);
    }

    #[Test]
    public function buildWithDefaults(): void
    {
        $definition = TaskDefinitionBuilder::build(new Schedule(), '\App\Task\DefaultTask');

        $this->assertSame('\App\Task\DefaultTask', $definition['classname']);
        $this->assertSame(0, $definition['blocking']);
        $this->assertSame('*', $definition['minute']);
        $this->assertSame('*', $definition['hour']);
        $this->assertSame('*', $definition['day']);
        $this->assertSame('*', $definition['month']);
        $this->assertSame('*', $definition['dayofweek']);
        $this->assertSame(0, $definition['disabled']);
    }

    #[Test]
    public function buildConvertsDisabledToInteger(): void
    {
        $this->assertSame(0, TaskDefinitionBuilder::build(new Schedule(disabled: false), 'Task')['disabled']);
        $this->assertSame(1, TaskDefinitionBuilder::build(new Schedule(disabled: true), 'Task')['disabled']);
    }

    #[Test]
    public function buildIncludesBlocking(): void
    {
        $definition = TaskDefinitionBuilder::build(new Schedule(exclusive: true), 'Task');

        $this->assertSame(1, $definition['blocking']);
    }

    #[Test]
    public function buildReturnsAllRequiredKeys(): void
    {
        $definition = TaskDefinitionBuilder::build(new Schedule(), 'Task');

        $requiredKeys = ['classname', 'blocking', 'minute', 'hour', 'day', 'month', 'dayofweek', 'disabled'];

        foreach ($requiredKeys as $key) {
            $this->assertArrayHasKey($key, $definition, sprintf('Missing key: %s', $key));
        }

        $this->assertCount(count($requiredKeys), $definition);
    }

    #[Test]
    public function buildPropagatesMoodleRandomScheduling(): void
    {
        $definition = TaskDefinitionBuilder::build(new Schedule(minute: 'R', hour: 'R'), 'Task');

        $this->assertSame('R', $definition['minute']);
        $this->assertSame('R', $definition['hour']);
    }
}
