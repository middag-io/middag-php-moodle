<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Domain\Role;

use Middag\Moodle\Domain\Role\Role;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Role is a native Moodle entity whose only own executable member is the
 * getTable() mapping; the accessor/mutator behaviour it exposes is inherited
 * from AbstractMoodleEntity. The table name and the entity-specific property
 * surface are asserted without a Moodle runtime.
 *
 * @internal
 */
#[CoversClass(Role::class)]
final class RoleCoverageTest extends TestCase
{
    #[Test]
    public function getTableMapsToRole(): void
    {
        self::assertSame('role', Role::getTable());
    }

    #[Test]
    public function propertyDefaultsMatchMoodleSchema(): void
    {
        $role = new Role();

        self::assertSame('', $role->get_name());
        self::assertSame('', $role->get_shortname());
        self::assertSame('', $role->get_description());
        self::assertSame(0, $role->get_sortorder());
        self::assertSame('', $role->get_archetype());
    }

    #[Test]
    public function fromRecordHydratesRoleSpecificFields(): void
    {
        $role = Role::fromRecord([
            'id' => '3',
            'name' => 'Teacher',
            'shortname' => 'editingteacher',
            'description' => 'Course editor',
            'sortorder' => '4',
            'archetype' => 'editingteacher',
        ]);

        self::assertInstanceOf(Role::class, $role);
        self::assertSame(3, $role->getId());
        self::assertSame('Teacher', $role->get_name());
        self::assertSame('editingteacher', $role->get_shortname());
        self::assertSame('Course editor', $role->get_description());
        self::assertSame(4, $role->get_sortorder());
        self::assertSame('editingteacher', $role->get_archetype());
    }
}
