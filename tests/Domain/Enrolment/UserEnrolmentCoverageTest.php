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

use Middag\Moodle\Domain\Enrolment\UserEnrolment;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * UserEnrolment is a native Moodle entity whose only own executable member is
 * the getTable() mapping; the accessor/mutator behaviour it exposes is inherited
 * from AbstractMoodleEntity. The table name and the entity-specific property
 * surface (including the sentinel timeend default) are asserted without a Moodle
 * runtime.
 *
 * @internal
 */
#[CoversClass(UserEnrolment::class)]
final class UserEnrolmentCoverageTest extends TestCase
{
    #[Test]
    public function getTableMapsToUserEnrolments(): void
    {
        self::assertSame('user_enrolments', UserEnrolment::getTable());
    }

    #[Test]
    public function propertyDefaultsMatchMoodleSchema(): void
    {
        $enrolment = new UserEnrolment();

        self::assertSame(0, $enrolment->get_status());
        self::assertSame(0, $enrolment->get_enrolid());
        self::assertSame(0, $enrolment->get_userid());
        self::assertSame(0, $enrolment->get_timestart());
        self::assertSame(2147483647, $enrolment->get_timeend());
        self::assertSame(0, $enrolment->get_modifierid());
    }

    #[Test]
    public function fromRecordHydratesEnrolmentSpecificFields(): void
    {
        $enrolment = UserEnrolment::fromRecord([
            'id' => '15',
            'status' => '1',
            'enrolid' => '4',
            'userid' => '8',
            'timestart' => '1700000000',
            'timeend' => '1800000000',
            'modifierid' => '2',
        ]);

        self::assertInstanceOf(UserEnrolment::class, $enrolment);
        self::assertSame(15, $enrolment->getId());
        self::assertSame(1, $enrolment->get_status());
        self::assertSame(4, $enrolment->get_enrolid());
        self::assertSame(8, $enrolment->get_userid());
        self::assertSame(1700000000, $enrolment->get_timestart());
        self::assertSame(1800000000, $enrolment->get_timeend());
        self::assertSame(2, $enrolment->get_modifierid());
    }
}
