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

use dml_exception;
use Middag\Moodle\Domain\Group\Cohort;
use Middag\Moodle\Domain\Group\CohortMemberDto;
use Middag\Moodle\Support\CohortSupport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * CohortSupport wraps cohort/lib.php and the global $DB. The DB is replaced with
 * a recording double; cohort_get_cohorts()/cohort_add_cohort() are driven from
 * tests/stubs/support/groups.php.
 *
 * @internal
 */
#[CoversClass(CohortSupport::class)]
final class CohortSupportCoverageTest extends TestCase
{
    private mixed $prevCfg;

    private mixed $prevDb;

    private object $db;

    protected function setUp(): void
    {
        $this->prevCfg = $GLOBALS['CFG'] ?? null;
        $this->prevDb = $GLOBALS['DB'] ?? null;

        // CohortSupport require_once's $CFG->dirroot . '/cohort/lib.php' at file
        // scope; point dirroot at a temp dir holding an empty lib before the class
        // is referenced.
        $base = sys_get_temp_dir() . '/middag_support_groups_stubs';
        if (!is_dir($base . '/cohort')) {
            mkdir($base . '/cohort', 0o777, true);
        }
        file_put_contents($base . '/cohort/lib.php', "<?php\n");
        $GLOBALS['CFG'] = (object) ['dirroot' => $base, 'libdir' => $base . '/lib'];

        $this->db = $this->makeDb();
        $GLOBALS['DB'] = $this->db;

        unset(
            $GLOBALS['__middag_test_cohorts'],
            $GLOBALS['__middag_test_added_cohorts'],
            $GLOBALS['__middag_test_cohort_add_id'],
        );
    }

    protected function tearDown(): void
    {
        $GLOBALS['CFG'] = $this->prevCfg;
        $GLOBALS['DB'] = $this->prevDb;

        unset(
            $GLOBALS['__middag_test_cohorts'],
            $GLOBALS['__middag_test_added_cohorts'],
            $GLOBALS['__middag_test_cohort_add_id'],
        );
    }

    #[Test]
    public function testGetCohortsReturnsTheKeyedStructureFromTheHost(): void
    {
        // cohort_get_cohorts() always returns a keyed structure (never a flat
        // list) across the supported version range; getCohorts() passes it
        // through, so consumers must read the 'cohorts' key.
        $GLOBALS['__middag_test_cohorts'] = [
            'cohorts' => [(object) ['id' => 1], (object) ['id' => 2]],
            'totalcohorts' => 2,
            'allcohorts' => 2,
        ];

        $result = CohortSupport::getCohorts(10);

        self::assertArrayHasKey('cohorts', $result);
        self::assertCount(2, $result['cohorts']);
        self::assertSame(2, $result['totalcohorts']);
    }

    #[Test]
    public function testGetCohortsWithTotalNormalizesAKeyedStructure(): void
    {
        $GLOBALS['__middag_test_cohorts'] = [
            'cohorts' => [(object) ['id' => 1, 'name' => 'Alpha'], (object) ['id' => 2, 'name' => 'Beta']],
            'totalcohorts' => 5,
        ];

        $result = CohortSupport::getCohortsWithTotal(10);

        self::assertCount(2, $result['items']);
        self::assertInstanceOf(Cohort::class, $result['items'][0]);
        self::assertSame('Alpha', $result['items'][0]->get_name());
        self::assertSame(5, $result['total']);
    }

    #[Test]
    public function testGetCohortsWithTotalHandlesAPlainListAndSkipsNonObjects(): void
    {
        $GLOBALS['__middag_test_cohorts'] = [(object) ['id' => 7, 'name' => 'Gamma'], 'not-an-object'];

        $result = CohortSupport::getCohortsWithTotal(10);

        self::assertCount(1, $result['items']);
        self::assertSame(7, $result['items'][0]->getId());
        self::assertSame(2, $result['total']);
    }

    #[Test]
    public function testGetAllReturnsAnIdToNameMap(): void
    {
        $this->db->records = [
            (object) ['id' => 3, 'name' => 'Alpha'],
            (object) ['id' => 4, 'name' => 'Beta'],
        ];

        self::assertSame([3 => 'Alpha', 4 => 'Beta'], CohortSupport::getAll());
    }

    #[Test]
    public function testGetAllReturnsEmptyWhenTheQueryThrows(): void
    {
        $this->db->recordsThrow = true;

        self::assertSame([], CohortSupport::getAll());
    }

    #[Test]
    public function testCreateCohortAddsACohortWhenTheIdnumberIsNew(): void
    {
        $this->db->recordExists = false;

        CohortSupport::createCohort('Team A', 'team-a');

        self::assertArrayHasKey('__middag_test_added_cohorts', $GLOBALS);
        self::assertCount(1, $GLOBALS['__middag_test_added_cohorts']);
        $added = $GLOBALS['__middag_test_added_cohorts'][0];
        self::assertSame('team-a', $added->idnumber);
        self::assertSame('Team A', $added->name);
        self::assertSame(1, $added->contextid);
    }

    #[Test]
    public function testCreateCohortSkipsWhenTheIdnumberAlreadyExists(): void
    {
        $this->db->recordExists = true;

        CohortSupport::createCohort('Team A', 'team-a');

        self::assertArrayNotHasKey('__middag_test_added_cohorts', $GLOBALS);
    }

    #[Test]
    public function testGetMembersReturnsDtosIndexedByUserId(): void
    {
        $this->db->records = [
            (object) ['cohortid' => 7, 'userid' => 11, 'timeadded' => 1000],
            (object) ['cohortid' => 7, 'userid' => 12, 'timeadded' => 2000],
        ];

        $members = CohortSupport::getMembers(7);

        self::assertArrayHasKey(11, $members);
        self::assertArrayHasKey(12, $members);
        self::assertInstanceOf(CohortMemberDto::class, $members[11]);
        self::assertSame(11, $members[11]->userid);
        self::assertSame(2000, $members[12]->timeadded);
    }

    #[Test]
    public function testGetMembersReturnsEmptyWhenTheQueryThrows(): void
    {
        $this->db->recordsThrow = true;

        self::assertSame([], CohortSupport::getMembers(7));
    }

    private function makeDb(): object
    {
        return new class {
            /** @var array<int|string, mixed> */
            public array $records = [];

            public bool $recordsThrow = false;

            public bool $recordExists = false;

            /**
             * @param mixed      $table
             * @param null|mixed $conditions
             * @param mixed      $sort
             * @param mixed      $fields
             * @param mixed      $limitfrom
             * @param mixed      $limitnum
             *
             * @return array<int|string, mixed>
             */
            public function get_records($table, $conditions = null, $sort = '', $fields = '*', $limitfrom = 0, $limitnum = 0): array
            {
                if ($this->recordsThrow) {
                    throw new dml_exception('db failure');
                }

                return $this->records;
            }

            public function record_exists($table, $conditions = null): bool
            {
                return $this->recordExists;
            }
        };
    }
}
