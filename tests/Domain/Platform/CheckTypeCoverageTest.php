<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Domain\Platform;

use Middag\Moodle\Domain\Platform\CheckType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * CheckType is a pure backed enum mapping onto Moodle's Check API type strings.
 * toMoodleValue() returns the backing value, so it is asserted for every case.
 *
 * @internal
 */
#[CoversClass(CheckType::class)]
final class CheckTypeCoverageTest extends TestCase
{
    #[Test]
    public function testBackingValuesMatchMoodleCheckTypeStrings(): void
    {
        self::assertSame('status', CheckType::Status->value);
        self::assertSame('security', CheckType::Security->value);
        self::assertSame('performance', CheckType::Performance->value);
    }

    #[Test]
    public function testToMoodleValueReturnsTheBackingValueForEveryCase(): void
    {
        self::assertSame('status', CheckType::Status->toMoodleValue());
        self::assertSame('security', CheckType::Security->toMoodleValue());
        self::assertSame('performance', CheckType::Performance->toMoodleValue());
    }
}
