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

use Middag\Moodle\Domain\Enrolment\EnrolmentDto;
use Middag\Moodle\Domain\Enrolment\EnrolmentService;
use Middag\Moodle\Domain\Enrolment\Enum\EnrolmentStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * EnrolmentService composes EnrolSupport / DbSupport / CourseSupport over the
 * global $DB. The $DB is replaced with a table-keyed recording double, and the
 * enrol/context Moodle helpers come from the shared support stubs, so every
 * branch of the service is exercised without a Moodle runtime.
 *
 * @internal
 */
#[CoversClass(EnrolmentService::class)]
final class EnrolmentServiceCoverageTest extends TestCase
{
    private mixed $prevDb;

    private mixed $prevCfg;

    private object $db;

    protected function setUp(): void
    {
        $this->prevDb = $GLOBALS['DB'] ?? null;
        $this->prevCfg = $GLOBALS['CFG'] ?? null;

        // enrolUser() require_once's $CFG->libdir . '/enrollib.php'.
        $base = sys_get_temp_dir() . '/middag_enrolment_service_stubs';
        if (!is_dir($base . '/lib')) {
            mkdir($base . '/lib', 0o777, true);
        }
        file_put_contents($base . '/lib/enrollib.php', "<?php\n");
        $GLOBALS['CFG'] = (object) ['dirroot' => $base, 'libdir' => $base . '/lib'];

        $this->db = $this->makeDb();
        $GLOBALS['DB'] = $this->db;

        unset(
            $GLOBALS['__middag_test_enrol_plugin'],
            $GLOBALS['__middag_test_enrol_instances'],
            $GLOBALS['__middag_test_is_enrolled'],
            $GLOBALS['__middag_test_context_course_instance'],
        );
    }

    protected function tearDown(): void
    {
        $GLOBALS['DB'] = $this->prevDb;
        $GLOBALS['CFG'] = $this->prevCfg;

        unset(
            $GLOBALS['__middag_test_enrol_plugin'],
            $GLOBALS['__middag_test_enrol_instances'],
            $GLOBALS['__middag_test_is_enrolled'],
            $GLOBALS['__middag_test_context_course_instance'],
        );
    }

    #[Test]
    public function testEnrolDelegatesToEnrolSupportAndReturnsTrue(): void
    {
        $this->db->recordExistsResult = true;
        $GLOBALS['__middag_test_enrol_plugin'] = new class {
            public function enrol_user($instance, $userid, $roleid = null, $timestart = 0): void {}
        };
        $GLOBALS['__middag_test_enrol_instances'] = [(object) ['id' => 7, 'enrol' => 'manual']];
        $GLOBALS['__middag_test_is_enrolled'] = true;

        $service = new EnrolmentService();

        self::assertTrue($service->enrol(5, 3, 7));
    }

    #[Test]
    public function testIsEnrolledIsTrueWhenAnEnrolmentRecordExists(): void
    {
        $this->db->recordSql = (object) ['id' => 10, 'enrolid' => 2, 'userid' => 5, 'status' => 0];

        $service = new EnrolmentService();

        self::assertTrue($service->isEnrolled(5, 3));
    }

    #[Test]
    public function testIsEnrolledIsFalseWhenNoEnrolmentRecordExists(): void
    {
        $this->db->recordSql = false;

        $service = new EnrolmentService();

        self::assertFalse($service->isEnrolled(5, 3));
    }

    #[Test]
    public function testGetEnrolmentReturnsNullWhenUserIsNotEnrolled(): void
    {
        $this->db->recordSql = false;

        $service = new EnrolmentService();

        self::assertNull($service->getEnrolment(5, 3));
    }

    #[Test]
    public function testGetEnrolmentReturnsNullWhenTheUserEnrolmentRecordCannotBeReloaded(): void
    {
        // getEnrol() finds the enrolment, but the follow-up get_record() misses.
        $this->db->recordSql = (object) ['id' => 10, 'enrolid' => 2, 'userid' => 5, 'status' => 0];
        $this->db->recordByTable = []; // 'user_enrolments' get_record returns false

        $service = new EnrolmentService();

        self::assertNull($service->getEnrolment(5, 3));
    }

    #[Test]
    public function testGetEnrolmentReturnsNullWhenTheEnrolInstanceRecordIsMissing(): void
    {
        $this->db->recordSql = (object) ['id' => 10, 'enrolid' => 2, 'userid' => 5, 'status' => 0];
        $this->db->recordByTable = [
            'user_enrolments' => (object) ['id' => 10, 'enrolid' => 2, 'userid' => 5, 'status' => 0],
            // 'enrol' absent → get_record returns false
        ];

        $service = new EnrolmentService();

        self::assertNull($service->getEnrolment(5, 3));
    }

    #[Test]
    public function testGetEnrolmentBuildsATypedDtoWhenFullyResolved(): void
    {
        $this->db->recordSql = (object) ['id' => 10, 'enrolid' => 2, 'userid' => 5, 'status' => 0];
        $this->db->recordByTable = [
            'user_enrolments' => (object) [
                'id' => 10, 'enrolid' => 2, 'userid' => 5, 'status' => 1,
                'timestart' => 100, 'timeend' => 200, 'timecreated' => 50, 'timemodified' => 60,
            ],
            'enrol' => (object) ['id' => 2, 'courseid' => 3, 'enrol' => 'manual', 'roleid' => 5],
        ];

        $service = new EnrolmentService();
        $dto = $service->getEnrolment(5, 3);

        self::assertInstanceOf(EnrolmentDto::class, $dto);
        self::assertSame(5, $dto->userid);
        self::assertSame(3, $dto->courseid);
        self::assertSame(2, $dto->enrolid);
        self::assertSame(10, $dto->user_enrolment_id);
        self::assertSame('manual', $dto->enrol_method);
        self::assertSame(EnrolmentStatus::Suspended, $dto->status);
        self::assertSame(100, $dto->timestart);
    }

    #[Test]
    public function testGetUserEnrolmentsKeepsEnrolmentsWithAResolvableEnrolInstance(): void
    {
        $this->db->recordsByTable = [
            'user_enrolments' => [
                (object) ['id' => 10, 'enrolid' => 2, 'userid' => 5, 'status' => 0],
            ],
        ];
        $this->db->recordByTable = [
            'enrol' => (object) ['id' => 2, 'courseid' => 3, 'enrol' => 'self', 'roleid' => 5],
        ];

        $service = new EnrolmentService();
        $result = $service->getUserEnrolments(5);

        self::assertArrayHasKey(3, $result);
        self::assertInstanceOf(EnrolmentDto::class, $result[3]);
        self::assertSame('self', $result[3]->enrol_method);
    }

    #[Test]
    public function testGetUserEnrolmentsSkipsEnrolmentsWhoseEnrolInstanceIsMissing(): void
    {
        $this->db->recordsByTable = [
            'user_enrolments' => [
                (object) ['id' => 10, 'enrolid' => 2, 'userid' => 5, 'status' => 0],
            ],
        ];
        $this->db->recordByTable = []; // get_record('enrol') returns false → skipped

        $service = new EnrolmentService();

        self::assertSame([], $service->getUserEnrolments(5));
    }

    #[Test]
    public function testGetCourseEnrolmentsBuildsADtoPerUserEnrolment(): void
    {
        $this->db->recordsByTable = [
            'enrol' => [
                (object) ['id' => 2, 'courseid' => 3, 'enrol' => 'manual', 'roleid' => 5],
            ],
            'user_enrolments' => [
                (object) ['id' => 10, 'enrolid' => 2, 'userid' => 5, 'status' => 0],
                (object) ['id' => 11, 'enrolid' => 2, 'userid' => 6, 'status' => 1],
            ],
        ];

        $service = new EnrolmentService();
        $result = $service->getCourseEnrolments(3);

        self::assertArrayHasKey(5, $result);
        self::assertArrayHasKey(6, $result);
        self::assertSame(3, $result[5]->courseid);
        self::assertSame(EnrolmentStatus::Suspended, $result[6]->status);
    }

    #[Test]
    public function testSuspendReturnsFalseWhenTheUserIsNotEnrolled(): void
    {
        $this->db->recordSql = false;

        $service = new EnrolmentService();

        self::assertFalse($service->suspend(5, 3));
    }

    #[Test]
    public function testSuspendReturnsFalseWhenTheUserEnrolmentRecordCannotBeReloaded(): void
    {
        $this->db->recordSql = (object) ['id' => 10, 'enrolid' => 2, 'userid' => 5, 'status' => 0];
        $this->db->recordByTable = []; // get_record('user_enrolments') returns false

        $service = new EnrolmentService();

        self::assertFalse($service->suspend(5, 3));
    }

    #[Test]
    public function testSuspendPersistsTheSuspendedStatus(): void
    {
        $this->db->recordSql = (object) ['id' => 10, 'enrolid' => 2, 'userid' => 5, 'status' => 0];
        $this->db->recordByTable = [
            'user_enrolments' => (object) ['id' => 10, 'enrolid' => 2, 'userid' => 5, 'status' => 0],
        ];
        $this->db->updateResult = true;

        $service = new EnrolmentService();

        self::assertTrue($service->suspend(5, 3));
        self::assertCount(1, $this->db->updateCalls);
        self::assertSame('user_enrolments', $this->db->updateCalls[0][0]);
        self::assertSame(EnrolmentStatus::Suspended->value, $this->db->updateCalls[0][1]->status);
    }

    #[Test]
    public function testReactivatePersistsTheActiveStatus(): void
    {
        $this->db->recordSql = (object) ['id' => 10, 'enrolid' => 2, 'userid' => 5, 'status' => 1];
        $this->db->recordByTable = [
            'user_enrolments' => (object) ['id' => 10, 'enrolid' => 2, 'userid' => 5, 'status' => 1],
        ];
        $this->db->updateResult = false;

        $service = new EnrolmentService();

        // update_record returns false here → the whole call reports failure.
        self::assertFalse($service->reactivate(5, 3));
        self::assertSame(EnrolmentStatus::Active->value, $this->db->updateCalls[0][1]->status);
    }

    #[Test]
    public function testCountEnrolledDelegatesToCourseSupport(): void
    {
        $this->db->countSql = 4;

        $service = new EnrolmentService();

        self::assertSame(4, $service->countEnrolled(3));
    }

    private function makeDb(): object
    {
        return new class {
            public bool $recordExistsResult = true;

            public mixed $recordSql = false;

            /** @var array<string, mixed> */
            public array $recordByTable = [];

            /** @var array<string, array<int, object>> */
            public array $recordsByTable = [];

            public bool $updateResult = true;

            /** @var array<int, array<int, mixed>> */
            public array $updateCalls = [];

            public int $countSql = 0;

            public function record_exists($table, $conditions = null): bool
            {
                return $this->recordExistsResult;
            }

            public function get_record_sql($sql, $params = null, $strictness = 0): mixed
            {
                return $this->recordSql;
            }

            public function get_record(string $table, $conditions = null, $fields = '*', $strictness = 0): mixed
            {
                return $this->recordByTable[$table] ?? false;
            }

            /**
             * @param null|mixed $conditions
             * @param mixed      $sort
             * @param mixed      $fields
             * @param mixed      $limitfrom
             * @param mixed      $limitnum
             *
             * @return array<int|string, object>
             */
            public function get_records(string $table, $conditions = null, $sort = '', $fields = '*', $limitfrom = 0, $limitnum = 0): array
            {
                return $this->recordsByTable[$table] ?? [];
            }

            public function update_record($table, $dataobject, $bulk = false): bool
            {
                $this->updateCalls[] = [$table, $dataobject];

                return $this->updateResult;
            }

            public function count_records_sql($sql, $params = null): int
            {
                return $this->countSql;
            }
        };
    }
}
