<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace MiddagMoodleTestsDomainnrolment;

use Middag\Moodle\Domain\Enrolment\EnrolmentDto;
use Middag\Moodle\Domain\Enrolment\EnrolmentStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @internal
 */
#[CoversClass(EnrolmentDto::class)]
final class EnrolmentDtoTest extends TestCase
{
    #[Test]
    public function canBeConstructedWithAllArgs(): void
    {
        $dto = new EnrolmentDto(
            userid: 10,
            courseid: 20,
            enrolid: 30,
            user_enrolment_id: 40,
            enrol_method: 'manual',
            status: EnrolmentStatus::ACTIVE,
            roleid: 5,
            timestart: 1700000000,
            timeend: 1703000000,
            timecreated: 1699000000,
            timemodified: 1700000000,
        );

        $this->assertSame(10, $dto->userid);
        $this->assertSame(20, $dto->courseid);
        $this->assertSame(30, $dto->enrolid);
        $this->assertSame(40, $dto->user_enrolment_id);
        $this->assertSame('manual', $dto->enrol_method);
        $this->assertSame(EnrolmentStatus::ACTIVE, $dto->status);
        $this->assertSame(5, $dto->roleid);
        $this->assertSame(1700000000, $dto->timestart);
        $this->assertSame(1703000000, $dto->timeend);
        $this->assertSame(1699000000, $dto->timecreated);
        $this->assertSame(1700000000, $dto->timemodified);
    }

    #[Test]
    public function isActiveDelegatesToStatusEnum(): void
    {
        $active = $this->createActiveEnrolment();
        $this->assertTrue($active->is_active());

        $suspended = new EnrolmentDto(
            userid: 10,
            courseid: 20,
            enrolid: 30,
            user_enrolment_id: 40,
            enrol_method: 'manual',
            status: EnrolmentStatus::SUSPENDED,
            roleid: 5,
            timestart: 0,
            timeend: 0,
            timecreated: 0,
            timemodified: 0,
        );
        $this->assertFalse($suspended->is_active());
    }

    #[Test]
    public function hasTimeLimitReturnsTrueWhenTimeendGreaterThanZero(): void
    {
        $dto = $this->createActiveEnrolment(timeend: 1703000000);
        $this->assertTrue($dto->has_time_limit());
    }

    #[Test]
    public function hasTimeLimitReturnsFalseWhenTimeendIsZero(): void
    {
        $dto = $this->createActiveEnrolment(timeend: 0);
        $this->assertFalse($dto->has_time_limit());
    }

    #[Test]
    public function isExpiredReturnsFalseWhenNoTimeLimit(): void
    {
        $dto = $this->createActiveEnrolment(timeend: 0);
        $this->assertFalse($dto->is_expired());
    }

    #[Test]
    public function isExpiredReturnsTrueWhenNowPastTimeend(): void
    {
        $dto = $this->createActiveEnrolment(timeend: 1700000000);
        $this->assertTrue($dto->is_expired(1700000001));
    }

    #[Test]
    public function isExpiredReturnsFalseWhenNowBeforeTimeend(): void
    {
        $dto = $this->createActiveEnrolment(timeend: 1700000000);
        $this->assertFalse($dto->is_expired(1699999999));
    }

    #[Test]
    public function isExpiredReturnsTrueWhenNowEqualsTimeendPlusOne(): void
    {
        $dto = $this->createActiveEnrolment(timeend: 1700000000);
        // now > timeend means expired, now == timeend is not expired
        $this->assertFalse($dto->is_expired(1700000000));
        $this->assertTrue($dto->is_expired(1700000001));
    }

    #[Test]
    public function toArrayReturnsCompleteRepresentation(): void
    {
        $dto = new EnrolmentDto(
            userid: 10,
            courseid: 20,
            enrolid: 30,
            user_enrolment_id: 40,
            enrol_method: 'self',
            status: EnrolmentStatus::SUSPENDED,
            roleid: 5,
            timestart: 1700000000,
            timeend: 1703000000,
            timecreated: 1699000000,
            timemodified: 1700000000,
        );

        $expected = [
            'userid' => 10,
            'courseid' => 20,
            'enrolid' => 30,
            'user_enrolment_id' => 40,
            'enrol_method' => 'self',
            'status' => 1, // EnrolmentStatus::SUSPENDED->value
            'roleid' => 5,
            'timestart' => 1700000000,
            'timeend' => 1703000000,
            'timecreated' => 1699000000,
            'timemodified' => 1700000000,
        ];

        $this->assertSame($expected, $dto->toArray());
    }

    #[Test]
    public function toArrayStatusIsIntegerValue(): void
    {
        $dto = $this->createActiveEnrolment();
        $array = $dto->toArray();
        $this->assertSame(0, $array['status']);
    }

    #[Test]
    public function isReadonly(): void
    {
        $reflection = new ReflectionClass(EnrolmentDto::class);
        $this->assertTrue($reflection->isReadOnly());
    }

    #[Test]
    public function isFinal(): void
    {
        $reflection = new ReflectionClass(EnrolmentDto::class);
        $this->assertTrue($reflection->isFinal());
    }

    #[Test]
    public function supportsDifferentEnrolMethods(): void
    {
        foreach (['manual', 'self', 'cohort', 'meta', 'guest'] as $method) {
            $dto = new EnrolmentDto(
                userid: 1,
                courseid: 1,
                enrolid: 1,
                user_enrolment_id: 1,
                enrol_method: $method,
                status: EnrolmentStatus::ACTIVE,
                roleid: 5,
                timestart: 0,
                timeend: 0,
                timecreated: 0,
                timemodified: 0,
            );
            $this->assertSame($method, $dto->enrol_method);
        }
    }

    private function createActiveEnrolment(int $timeend = 0): EnrolmentDto
    {
        return new EnrolmentDto(
            userid: 10,
            courseid: 20,
            enrolid: 30,
            user_enrolment_id: 40,
            enrol_method: 'manual',
            status: EnrolmentStatus::ACTIVE,
            roleid: 5,
            timestart: 1700000000,
            timeend: $timeend,
            timecreated: 1699000000,
            timemodified: 1700000000,
        );
    }
}
