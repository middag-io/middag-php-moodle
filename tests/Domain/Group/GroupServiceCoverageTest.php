<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Domain\Group;

use Middag\Moodle\Domain\Group\GroupService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * GroupService::addUserToGroup orchestrates the GroupSupport primitives
 * (lookup, create, membership) that are driven from
 * tests/stubs/support/groups.php via the __middag_test_groups_* globals.
 * GroupSupport require_once's $CFG->dirroot . '/group/lib.php' at file scope,
 * so a stub CFG is supplied defensively.
 *
 * @internal
 */
#[CoversClass(GroupService::class)]
final class GroupServiceCoverageTest extends TestCase
{
    private mixed $prevCfg;

    private mixed $prevDb;

    private GroupService $service;

    protected function setUp(): void
    {
        $this->prevCfg = $GLOBALS['CFG'] ?? null;
        $this->prevDb = $GLOBALS['DB'] ?? null;

        $base = sys_get_temp_dir() . '/middag_group_service_stubs';
        if (!is_dir($base . '/group')) {
            mkdir($base . '/group', 0o777, true);
        }
        file_put_contents($base . '/group/lib.php', "<?php\n");
        $GLOBALS['CFG'] = (object) ['dirroot' => $base, 'libdir' => $base . '/lib'];

        // GroupSupport::isMember() now queries groups_members directly; drive it
        // through a record_exists() double keyed on a membership flag.
        $GLOBALS['DB'] = new class {
            public function record_exists($table, $conditions = null): bool
            {
                return !empty($GLOBALS['__middag_test_group_membership_exists']);
            }
        };

        $this->service = new GroupService();

        unset(
            $GLOBALS['__middag_test_group_membership_exists'],
            $GLOBALS['__middag_test_groups_is_member'],
            $GLOBALS['__middag_test_groups_add_member'],
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

        unset(
            $GLOBALS['__middag_test_group_membership_exists'],
            $GLOBALS['__middag_test_groups_is_member'],
            $GLOBALS['__middag_test_groups_add_member'],
            $GLOBALS['__middag_test_groups_create_group'],
            $GLOBALS['__middag_test_created_group'],
            $GLOBALS['__middag_test_groups_get_group_by_name'],
            $GLOBALS['__middag_test_throw_groups_get_group_by_name'],
        );
    }

    #[Test]
    public function addUserToGroupReturnsTrueWhenAlreadyAMember(): void
    {
        $GLOBALS['__middag_test_groups_get_group_by_name'] = 7;
        $GLOBALS['__middag_test_group_membership_exists'] = true;

        self::assertTrue($this->service->addUserToGroup(3, 5, 'Team'));
    }

    #[Test]
    public function addUserToGroupAddsTheUserWhenNotYetAMember(): void
    {
        $GLOBALS['__middag_test_groups_get_group_by_name'] = 7;
        $GLOBALS['__middag_test_group_membership_exists'] = false;
        $GLOBALS['__middag_test_groups_add_member'] = true;

        self::assertTrue($this->service->addUserToGroup(3, 5, 'Team'));
    }

    #[Test]
    public function addUserToGroupCreatesTheGroupWhenMissing(): void
    {
        $GLOBALS['__middag_test_groups_get_group_by_name'] = false;
        $GLOBALS['__middag_test_groups_create_group'] = 9;
        $GLOBALS['__middag_test_group_membership_exists'] = false;
        $GLOBALS['__middag_test_groups_add_member'] = true;

        self::assertTrue($this->service->addUserToGroup(3, 5, 'Team'));
        self::assertSame('Team', $GLOBALS['__middag_test_created_group']->name);
    }

    #[Test]
    public function addUserToGroupReturnsFalseWhenGroupCreationFails(): void
    {
        $GLOBALS['__middag_test_groups_get_group_by_name'] = false;
        $GLOBALS['__middag_test_groups_create_group'] = 0;

        self::assertFalse($this->service->addUserToGroup(3, 5, 'Team'));
    }

    #[Test]
    public function addUserToGroupReturnsFalseWhenTheLookupThrows(): void
    {
        $GLOBALS['__middag_test_throw_groups_get_group_by_name'] = true;

        self::assertFalse($this->service->addUserToGroup(3, 5, 'Team'));
    }
}
