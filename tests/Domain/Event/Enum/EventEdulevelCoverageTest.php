<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Domain\Event\Enum;

use Middag\Moodle\Domain\Event\Enum\EventEdulevel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * EventEdulevel is a backed int enum wrapping \core\event\base::LEVEL_*. The
 * three cases, their backing values, and the toMoodleValue() accessor are
 * asserted without a Moodle runtime.
 *
 * @internal
 */
#[CoversClass(EventEdulevel::class)]
final class EventEdulevelCoverageTest extends TestCase
{
    #[Test]
    public function allCasesExist(): void
    {
        self::assertCount(3, EventEdulevel::cases());
    }

    #[Test]
    public function backingValuesMatchMoodleLevels(): void
    {
        self::assertSame(0, EventEdulevel::Teaching->value);
        self::assertSame(1, EventEdulevel::Participating->value);
        self::assertSame(2, EventEdulevel::Other->value);
    }

    #[Test]
    public function toMoodleValueReturnsBackingValueForEveryCase(): void
    {
        foreach (EventEdulevel::cases() as $case) {
            self::assertSame($case->value, $case->toMoodleValue());
        }
    }

    #[Test]
    public function canBeCreatedFromValue(): void
    {
        self::assertSame(EventEdulevel::Teaching, EventEdulevel::from(0));
        self::assertSame(EventEdulevel::Participating, EventEdulevel::from(1));
        self::assertSame(EventEdulevel::Other, EventEdulevel::from(2));
    }
}
