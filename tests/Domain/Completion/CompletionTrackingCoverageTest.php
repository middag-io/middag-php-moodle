<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Domain\Completion;

use Middag\Moodle\Domain\Completion\CompletionTracking;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(CompletionTracking::class)]
final class CompletionTrackingCoverageTest extends TestCase
{
    #[Test]
    public function allCasesExistWithMoodleValues(): void
    {
        $cases = CompletionTracking::cases();

        self::assertCount(3, $cases);
        self::assertSame(0, CompletionTracking::NONE->value);
        self::assertSame(1, CompletionTracking::MANUAL->value);
        self::assertSame(2, CompletionTracking::AUTOMATIC->value);
    }

    #[Test]
    public function isTrackedIsFalseOnlyForNone(): void
    {
        self::assertFalse(CompletionTracking::NONE->isTracked());
        self::assertTrue(CompletionTracking::MANUAL->isTracked());
        self::assertTrue(CompletionTracking::AUTOMATIC->isTracked());
    }

    #[Test]
    public function isManualIsTrueOnlyForManual(): void
    {
        self::assertFalse(CompletionTracking::NONE->isManual());
        self::assertTrue(CompletionTracking::MANUAL->isManual());
        self::assertFalse(CompletionTracking::AUTOMATIC->isManual());
    }

    #[Test]
    public function isAutomaticIsTrueOnlyForAutomatic(): void
    {
        self::assertFalse(CompletionTracking::NONE->isAutomatic());
        self::assertFalse(CompletionTracking::MANUAL->isAutomatic());
        self::assertTrue(CompletionTracking::AUTOMATIC->isAutomatic());
    }

    #[Test]
    public function labelReturnsHumanReadableStringForEachCase(): void
    {
        self::assertSame('Not tracked', CompletionTracking::NONE->label());
        self::assertSame('Manual', CompletionTracking::MANUAL->label());
        self::assertSame('Automatic', CompletionTracking::AUTOMATIC->label());
    }

    #[Test]
    public function resolveReturnsMatchingCase(): void
    {
        self::assertSame(CompletionTracking::NONE, CompletionTracking::resolve(0));
        self::assertSame(CompletionTracking::MANUAL, CompletionTracking::resolve(1));
        self::assertSame(CompletionTracking::AUTOMATIC, CompletionTracking::resolve(2));
    }

    #[Test]
    public function resolveDefaultsToNoneForUnknownValue(): void
    {
        self::assertSame(CompletionTracking::NONE, CompletionTracking::resolve(99));
        self::assertSame(CompletionTracking::NONE, CompletionTracking::resolve(-1));
    }
}
