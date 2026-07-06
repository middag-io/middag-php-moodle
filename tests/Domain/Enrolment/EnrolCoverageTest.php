<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Domain\Enrolment;

use Middag\Moodle\Domain\Enrolment\Enrol;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Enrol is a native Moodle entity: its only own member is getTable(); the
 * accessor/mutator behaviour comes from AbstractMoodleEntity. This exercises the
 * table binding and confirms the inherited magic accessors resolve its columns.
 *
 * @internal
 */
#[CoversClass(Enrol::class)]
final class EnrolCoverageTest extends TestCase
{
    #[Test]
    public function testGetTableReturnsTheEnrolTable(): void
    {
        self::assertSame('enrol', Enrol::getTable());
    }

    #[Test]
    public function testTypedColumnsRoundTripThroughTheEntity(): void
    {
        $enrol = (new Enrol())
            ->with_enrol('manual')
            ->with_courseid(3)
            ->with_roleid(5)
            ->with_name('Manual enrolments');

        self::assertSame('manual', $enrol->get_enrol());
        self::assertSame(3, $enrol->get_courseid());
        self::assertSame(5, $enrol->get_roleid());
        self::assertSame('Manual enrolments', $enrol->get_name());
    }
}
