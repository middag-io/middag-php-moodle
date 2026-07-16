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

use core\exception\moodle_exception;
use Middag\Moodle\Domain\Enrolment\UserEnrolment;
use Middag\Moodle\Support\EnrolSupport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * EnrolSupport wraps the global $DB and enrollib.php helpers. The DB is replaced
 * with a recording double; enrol_get_plugin()/enrol_get_instances()/is_enrolled()
 * are driven from the support stubs.
 *
 * @internal
 */
#[CoversClass(EnrolSupport::class)]
final class EnrolSupportCoverageTest extends TestCase
{
    private mixed $prevCfg;

    private mixed $prevDb;

    private object $db;

    protected function setUp(): void
    {
        $this->prevCfg = $GLOBALS['CFG'] ?? null;
        $this->prevDb = $GLOBALS['DB'] ?? null;

        // enrolUser() require_once's $CFG->libdir . '/enrollib.php'; point libdir
        // at a temp dir holding an empty lib.
        $base = sys_get_temp_dir() . '/middag_support_groups_stubs';
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
            $GLOBALS['__middag_test_user_has_role'],
        );
    }

    protected function tearDown(): void
    {
        $GLOBALS['CFG'] = $this->prevCfg;
        $GLOBALS['DB'] = $this->prevDb;

        unset(
            $GLOBALS['__middag_test_enrol_plugin'],
            $GLOBALS['__middag_test_enrol_instances'],
            $GLOBALS['__middag_test_is_enrolled'],
            $GLOBALS['__middag_test_user_has_role'],
        );
    }

    #[Test]
    public function testGetEnrolReturnsAnEntityWhenARecordIsFound(): void
    {
        $this->db->recordSql = (object) ['id' => 1, 'status' => 1, 'enrolid' => 2, 'userid' => 5];

        $enrol = EnrolSupport::getEnrol(3, 5);

        self::assertInstanceOf(UserEnrolment::class, $enrol);
        self::assertSame(5, $enrol->get_userid());
        self::assertSame(1, $enrol->get_status());
    }

    #[Test]
    public function testGetEnrolReturnsNullWhenNoRecordIsFound(): void
    {
        $this->db->recordSql = false;

        self::assertNull(EnrolSupport::getEnrol(3, 5));
    }

    #[Test]
    public function testGetEnrolCohortsNormalizesIds(): void
    {
        $this->db->recordsSql = [(object) ['id' => '9', 'name' => 'Cohort C', 'idnumber' => 'X']];

        $cohorts = EnrolSupport::getEnrolCohorts(3, 5);

        self::assertSame(9, $cohorts[0]->id);
        self::assertSame('Cohort C', $cohorts[0]->name);
    }

    #[Test]
    public function testUserIsEnrolledIsTrueWhenAnEnrolmentExists(): void
    {
        $this->db->recordSql = (object) ['id' => 1, 'userid' => 5];

        self::assertTrue(EnrolSupport::userIsEnrolled(3, 5));
    }

    #[Test]
    public function testUserIsEnrolledIsFalseWhenNoEnrolmentExists(): void
    {
        $this->db->recordSql = false;

        self::assertFalse(EnrolSupport::userIsEnrolled(3, 5));
    }

    #[Test]
    public function testEnrolUserThrowsWhenTheCourseIsMissing(): void
    {
        $this->db->recordExistsMap = ['course' => false];

        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage('course_not_found');

        EnrolSupport::enrolUser(3, 50, 5);
    }

    #[Test]
    public function testEnrolUserThrowsWhenTheUserIsMissing(): void
    {
        $this->db->recordExistsMap = ['course' => true, 'user' => false];

        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage('user_not_found');

        EnrolSupport::enrolUser(3, 50, 5);
    }

    #[Test]
    public function testEnrolUserThrowsWhenTheManualPluginIsUnavailable(): void
    {
        $this->db->recordExistsMap = ['course' => true, 'user' => true];
        $GLOBALS['__middag_test_enrol_plugin'] = false;

        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage('manual_enrol_not_found');

        EnrolSupport::enrolUser(3, 50, 5);
    }

    #[Test]
    public function testEnrolUserSkipsOnlyWhenTheManualEnrolmentAndRoleBothExist(): void
    {
        // Skip the enrolment call only when the specific requested outcome
        // already holds: enrolled on the manual instance AND the role assigned.
        $this->db->recordExistsMap = ['course' => true, 'user' => true, 'user_enrolments' => true];
        $plugin = $this->makePlugin();
        $GLOBALS['__middag_test_enrol_plugin'] = $plugin;
        $GLOBALS['__middag_test_enrol_instances'] = [
            (object) ['id' => 60, 'enrol' => 'self'],
            (object) ['id' => 77, 'enrol' => 'manual'],
        ];
        $GLOBALS['__middag_test_user_has_role'] = true;

        self::assertTrue(EnrolSupport::enrolUser(3, 50, 5));
        self::assertSame([], $plugin->enrolCalls);
        self::assertSame([], $this->db->insertCalls);
    }

    #[Test]
    public function testEnrolUserGrantsTheRoleWhenEnrolledWithoutIt(): void
    {
        // The user already has a manual enrolment but not the requested role.
        // is_enrolled() would wrongly short-circuit; the specific check must
        // still call enrol_user() so the role gets assigned.
        $this->db->recordExistsMap = ['course' => true, 'user' => true, 'user_enrolments' => true];
        $plugin = $this->makePlugin();
        $GLOBALS['__middag_test_enrol_plugin'] = $plugin;
        $GLOBALS['__middag_test_enrol_instances'] = [
            (object) ['id' => 77, 'enrol' => 'manual'],
        ];
        $GLOBALS['__middag_test_user_has_role'] = false;

        self::assertTrue(EnrolSupport::enrolUser(3, 50, 5));
        self::assertCount(1, $plugin->enrolCalls);
        self::assertSame(77, $plugin->enrolCalls[0][0]->id);
        self::assertSame(50, $plugin->enrolCalls[0][1]);
        self::assertSame(5, $plugin->enrolCalls[0][2]);
        self::assertSame([], $this->db->insertCalls);
    }

    #[Test]
    public function testEnrolUserCreatesAManualInstanceAndEnrolsTheUser(): void
    {
        $this->db->recordExistsMap = ['course' => true, 'user' => true];
        $this->db->insertId = 42;
        $this->db->getRecord = (object) ['id' => 42, 'enrol' => 'manual'];

        $plugin = $this->makePlugin();
        $GLOBALS['__middag_test_enrol_plugin'] = $plugin;
        $GLOBALS['__middag_test_enrol_instances'] = [];

        self::assertTrue(EnrolSupport::enrolUser(3, 50, 7));

        self::assertCount(1, $this->db->insertCalls);
        self::assertSame('enrol', $this->db->insertCalls[0][0]);
        self::assertCount(1, $plugin->enrolCalls);
        self::assertSame(42, $plugin->enrolCalls[0][0]->id);
        self::assertSame(50, $plugin->enrolCalls[0][1]);
        self::assertSame(7, $plugin->enrolCalls[0][2]);
    }

    private function makePlugin(): object
    {
        return new class {
            /** @var array<int, array<int, mixed>> */
            public array $enrolCalls = [];

            public function enrol_user($instance, $userid, $roleid = null, $timestart = 0, $timeend = 0, $status = null, $recovergrades = null): void
            {
                $this->enrolCalls[] = [$instance, $userid, $roleid, $timestart];
            }
        };
    }

    private function makeDb(): object
    {
        return new class {
            /** @var array<string, bool> */
            public array $recordExistsMap = [];

            public mixed $recordSql = false;

            /** @var array<int|string, mixed> */
            public array $recordsSql = [];

            public int $insertId = 0;

            public mixed $getRecord = null;

            /** @var array<int, array<int, mixed>> */
            public array $insertCalls = [];

            public function record_exists(string $table, $conditions = null): bool
            {
                return $this->recordExistsMap[$table] ?? false;
            }

            public function get_record_sql($sql, $params = null, $strictness = 0): mixed
            {
                return $this->recordSql;
            }

            /**
             * @param mixed      $sql
             * @param null|mixed $params
             * @param mixed      $limitfrom
             * @param mixed      $limitnum
             *
             * @return array<int|string, mixed>
             */
            public function get_records_sql($sql, $params = null, $limitfrom = 0, $limitnum = 0): array
            {
                return $this->recordsSql;
            }

            public function insert_record($table, $dataobject, $returnid = true, $bulk = false): int
            {
                $this->insertCalls[] = [$table, $dataobject];

                return $this->insertId;
            }

            public function get_record($table, $conditions = null, $fields = '*', $strictness = 0): mixed
            {
                return $this->getRecord;
            }
        };
    }
}
