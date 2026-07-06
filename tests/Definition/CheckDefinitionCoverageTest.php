<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Definition;

use Middag\Moodle\Definition\CheckDefinition;
use Middag\Moodle\Definition\Contract\DefinitionInterface;
use Middag\Moodle\Domain\Platform\CheckType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * CheckDefinition is a readonly typed metadata value object for Moodle's Check
 * API. It has no Moodle runtime dependency: toMoodleArray() assembles a fixed
 * array from the classname and the CheckType->toMoodleValue() mapping, and
 * isCompatible() gates on version_compare() against optional min/max bounds.
 *
 * @internal
 */
#[CoversClass(CheckDefinition::class)]
final class CheckDefinitionCoverageTest extends TestCase
{
    #[Test]
    public function constructorAppliesDocumentedDefaults(): void
    {
        $check = new CheckDefinition(name: 'eav_integrity', classname: 'local_example\check\eav_integrity');

        self::assertSame('eav_integrity', $check->name);
        self::assertSame('local_example\check\eav_integrity', $check->classname);
        self::assertSame(CheckType::STATUS, $check->type);
        self::assertNull($check->min_moodle);
        self::assertNull($check->max_moodle);
    }

    #[Test]
    public function constructorRetainsAllExplicitArguments(): void
    {
        $check = new CheckDefinition(
            name: 'perf_probe',
            classname: 'local_example\check\perf_probe',
            type: CheckType::PERFORMANCE,
            min_moodle: '4.0',
            max_moodle: '5.0',
        );

        self::assertSame('perf_probe', $check->name);
        self::assertSame('local_example\check\perf_probe', $check->classname);
        self::assertSame(CheckType::PERFORMANCE, $check->type);
        self::assertSame('4.0', $check->min_moodle);
        self::assertSame('5.0', $check->max_moodle);
    }

    #[Test]
    public function implementsDefinitionInterface(): void
    {
        $check = new CheckDefinition(name: 'eav_integrity', classname: 'local_example\check\eav_integrity');

        self::assertInstanceOf(DefinitionInterface::class, $check);
    }

    #[Test]
    public function getNameReturnsTheCheckIdentifier(): void
    {
        $check = new CheckDefinition(name: 'eav_integrity', classname: 'local_example\check\eav_integrity');

        self::assertSame('eav_integrity', $check->getName());
    }

    #[Test]
    public function toMoodleArrayBuildsClassnameAndDefaultStatusType(): void
    {
        $check = new CheckDefinition(name: 'eav_integrity', classname: 'local_example\check\eav_integrity');

        // The $plugin_name argument is not consumed by the body; the array is
        // built solely from the classname and the mapped type value.
        $result = $check->toMoodleArray('local_example');

        self::assertSame(
            [
                'classname' => 'local_example\check\eav_integrity',
                'type' => 'status',
            ],
            $result,
        );
    }

    #[Test]
    public function toMoodleArrayMapsSecurityType(): void
    {
        $check = new CheckDefinition(
            name: 'lockout',
            classname: 'local_example\check\lockout',
            type: CheckType::SECURITY,
        );

        $result = $check->toMoodleArray('local_example');

        self::assertSame('local_example\check\lockout', $result['classname']);
        self::assertSame('security', $result['type']);
    }

    #[Test]
    public function toMoodleArrayMapsPerformanceType(): void
    {
        $check = new CheckDefinition(
            name: 'cachestats',
            classname: 'local_example\check\cachestats',
            type: CheckType::PERFORMANCE,
        );

        $result = $check->toMoodleArray('local_example');

        self::assertSame('performance', $result['type']);
    }

    #[Test]
    public function isCompatibleReturnsTrueWhenNoBoundsAreSet(): void
    {
        // Both min_moodle and max_moodle null: the two guards short-circuit on
        // their `!== null` clause and control falls through to `return true`.
        $check = new CheckDefinition(name: 'eav_integrity', classname: 'local_example\check\eav_integrity');

        self::assertTrue($check->isCompatible('4.5'));
        self::assertTrue($check->isCompatible('1.0'));
    }

    #[Test]
    public function isCompatibleRejectsVersionsBelowTheMinimum(): void
    {
        $check = new CheckDefinition(
            name: 'eav_integrity',
            classname: 'local_example\check\eav_integrity',
            min_moodle: '4.0',
        );

        // Below the minimum: first guard's version_compare('<') is true.
        self::assertFalse($check->isCompatible('3.11'));
        // At and above the minimum: version_compare('<') is false, no max set.
        self::assertTrue($check->isCompatible('4.0'));
        self::assertTrue($check->isCompatible('4.5'));
    }

    #[Test]
    public function isCompatibleRejectsVersionsAboveTheMaximum(): void
    {
        $check = new CheckDefinition(
            name: 'eav_integrity',
            classname: 'local_example\check\eav_integrity',
            max_moodle: '4.5',
        );

        // Above the maximum: second guard's version_compare('>') is true.
        self::assertFalse($check->isCompatible('4.6'));
        // At and below the maximum: version_compare('>') is false.
        self::assertTrue($check->isCompatible('4.5'));
        self::assertTrue($check->isCompatible('4.0'));
    }

    #[Test]
    public function isCompatibleHonoursBothBoundsTogether(): void
    {
        $check = new CheckDefinition(
            name: 'eav_integrity',
            classname: 'local_example\check\eav_integrity',
            min_moodle: '4.0',
            max_moodle: '5.0',
        );

        self::assertFalse($check->isCompatible('3.9'));
        self::assertTrue($check->isCompatible('4.0'));
        self::assertTrue($check->isCompatible('4.7'));
        self::assertTrue($check->isCompatible('5.0'));
        self::assertFalse($check->isCompatible('5.1'));
    }
}
