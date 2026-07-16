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

use core\context;
use dml_exception;
use Middag\Moodle\Domain\Group\GroupMemberDto;
use Middag\Moodle\Support\GroupSupport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

/**
 * GroupSupport wraps group/lib.php and the global $DB. The DB is replaced with a
 * recording double; groups_* functions are driven from
 * tests/stubs/support/groups.php. The get_string() handler is neutralised so
 * LangSupport::getString() yields the deterministic marker.
 *
 * @internal
 */
#[CoversClass(GroupSupport::class)]
final class GroupSupportCoverageTest extends TestCase
{
    private mixed $prevCfg;

    private mixed $prevDb;

    private mixed $prevGetString;

    private object $db;

    protected function setUp(): void
    {
        $this->prevCfg = $GLOBALS['CFG'] ?? null;
        $this->prevDb = $GLOBALS['DB'] ?? null;
        $this->prevGetString = $GLOBALS['__middag_test_get_string'] ?? null;

        // GroupSupport require_once's $CFG->dirroot . '/group/lib.php' at file scope.
        $base = sys_get_temp_dir() . '/middag_support_groups_stubs';
        if (!is_dir($base . '/group')) {
            mkdir($base . '/group', 0o777, true);
        }
        file_put_contents($base . '/group/lib.php', "<?php\n");
        $GLOBALS['CFG'] = (object) ['dirroot' => $base, 'libdir' => $base . '/lib'];

        $this->db = $this->makeDb();
        $GLOBALS['DB'] = $this->db;

        unset(
            $GLOBALS['__middag_test_get_string'],
            $GLOBALS['__middag_test_groups_is_member'],
            $GLOBALS['__middag_test_groups_add_member'],
            $GLOBALS['__middag_test_throw_groups_add_member'],
            $GLOBALS['__middag_test_groups_create_group'],
            $GLOBALS['__middag_test_created_group'],
            $GLOBALS['__middag_test_groups_get_group_by_name'],
            $GLOBALS['__middag_test_throw_groups_get_group_by_name'],
        );
    }

    protected function tearDown(): void
    {
        $GLOBALS['CFG'] = $this->prevCfg;
        $GLOBALS['DB'] = $this->prevDb;
        $GLOBALS['__middag_test_get_string'] = $this->prevGetString;

        unset(
            $GLOBALS['__middag_test_groups_is_member'],
            $GLOBALS['__middag_test_groups_add_member'],
            $GLOBALS['__middag_test_throw_groups_add_member'],
            $GLOBALS['__middag_test_groups_create_group'],
            $GLOBALS['__middag_test_created_group'],
            $GLOBALS['__middag_test_groups_get_group_by_name'],
            $GLOBALS['__middag_test_throw_groups_get_group_by_name'],
        );
    }

    #[Test]
    public function testGetGroupsReturnsTheQueryResult(): void
    {
        $this->db->recordsSql = [(object) ['id' => 1], (object) ['id' => 2]];

        self::assertCount(2, GroupSupport::getGroups(3, 5));
    }

    #[Test]
    public function testGetGroupsReturnsEmptyWhenTheQueryThrows(): void
    {
        $this->db->throwSql = new dml_exception('db failure');

        self::assertSame([], GroupSupport::getGroups(3, 5));
    }

    #[Test]
    public function testGetGroupOptionsWithoutContextListsAllGroups(): void
    {
        $this->db->recordsSql = [(object) ['name' => 'G1'], (object) ['name' => 'G2']];

        $options = GroupSupport::getGroupOptions();

        self::assertSame('G1', $options['G1']);
        self::assertSame('G2', $options['G2']);
        self::assertSame('-- [/none] --', $options[0]);
        self::assertSame([], $this->db->lastSqlParams);
    }

    #[Test]
    public function testGetGroupOptionsWithContextFiltersByCourse(): void
    {
        $this->db->recordsSql = [(object) ['name' => 'G1']];
        $courseContext = new class extends context {
            public int $instanceid = 55;
        };

        $options = GroupSupport::getGroupOptions($courseContext);

        self::assertSame('G1', $options['G1']);
        self::assertStringContainsString('WHERE courseid = :courseid', $this->db->lastSql);
        self::assertSame(55, $this->db->lastSqlParams['courseid']);
    }

    #[Test]
    public function testGetGroupOptionsReturnsOnlyNoneWhenTheQueryThrows(): void
    {
        $this->db->throwSql = new RuntimeException('db failure');

        $options = GroupSupport::getGroupOptions();

        self::assertCount(1, $options);
        self::assertSame('-- [/none] --', $options[0]);
    }

    #[Test]
    public function testIsMemberDelegatesToGroupsIsMember(): void
    {
        $GLOBALS['__middag_test_groups_is_member'] = true;

        self::assertTrue(GroupSupport::isMember(1, 2));
    }

    #[Test]
    public function testAddMemberReturnsTrueOnSuccess(): void
    {
        $GLOBALS['__middag_test_groups_add_member'] = true;

        self::assertTrue(GroupSupport::addMember(1, 2));
    }

    #[Test]
    public function testAddMemberReturnsFalseWhenTheGroupsApiThrows(): void
    {
        $GLOBALS['__middag_test_throw_groups_add_member'] = true;

        self::assertFalse(GroupSupport::addMember(1, 2));
    }

    #[Test]
    public function testCreateGroupBuildsTheGroupAndNormalizesTheId(): void
    {
        $GLOBALS['__middag_test_groups_create_group'] = 10;

        self::assertSame(10, GroupSupport::createGroup(3, 'Team', 'idn', '<p>desc</p>'));

        $group = $GLOBALS['__middag_test_created_group'];
        self::assertSame(3, $group->courseid);
        self::assertSame('Team', $group->name);
        self::assertSame('idn', $group->idnumber);
        self::assertSame('<p>desc</p>', $group->description);
        self::assertSame(FORMAT_HTML, $group->descriptionformat);
    }

    #[Test]
    public function testCreateGroupReturnsNullWhenCreationFails(): void
    {
        $GLOBALS['__middag_test_groups_create_group'] = 0;

        self::assertNull(GroupSupport::createGroup(3, 'Team'));
    }

    #[Test]
    public function testGetGroupByNameReturnsTheIdWhenFound(): void
    {
        $GLOBALS['__middag_test_groups_get_group_by_name'] = 7;

        self::assertSame(7, GroupSupport::getGroupByName(3, 'Team'));
    }

    #[Test]
    public function testGetGroupByNameReturnsZeroWhenNotFound(): void
    {
        $GLOBALS['__middag_test_groups_get_group_by_name'] = false;

        self::assertSame(0, GroupSupport::getGroupByName(3, 'Team'));
    }

    #[Test]
    public function testGetMembersReturnsDtosIndexedByUserId(): void
    {
        $this->db->records = [
            (object) ['groupid' => 1, 'userid' => 11, 'timeadded' => 100, 'component' => 'mod_x', 'itemid' => 3],
            (object) ['groupid' => 1, 'userid' => 12, 'timeadded' => 200],
        ];

        $members = GroupSupport::getMembers(1);

        self::assertInstanceOf(GroupMemberDto::class, $members[11]);
        self::assertSame('mod_x', $members[11]->component);
        self::assertSame(3, $members[11]->itemid);
        self::assertSame('', $members[12]->component);
        self::assertSame(0, $members[12]->itemid);
    }

    #[Test]
    public function testGetMembersReturnsEmptyWhenTheQueryThrows(): void
    {
        $this->db->throwRecords = new dml_exception('db failure');

        self::assertSame([], GroupSupport::getMembers(1));
    }

    private function makeDb(): object
    {
        return new class {
            /** @var array<int|string, mixed> */
            public array $recordsSql = [];

            /** @var array<int|string, mixed> */
            public array $records = [];

            public ?Throwable $throwSql = null;

            public ?Throwable $throwRecords = null;

            public string $lastSql = '';

            /** @var array<string, mixed> */
            public array $lastSqlParams = [];

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
                $this->lastSql = (string) $sql;
                $this->lastSqlParams = (array) $params;

                if ($this->throwSql instanceof Throwable) {
                    throw $this->throwSql;
                }

                return $this->recordsSql;
            }

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
                if ($this->throwRecords instanceof Throwable) {
                    throw $this->throwRecords;
                }

                return $this->records;
            }
        };
    }
}
