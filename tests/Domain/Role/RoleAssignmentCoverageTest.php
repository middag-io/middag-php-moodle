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

use Middag\Moodle\Domain\Role\RoleAssignment;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * RoleAssignment is a native Moodle entity mapping mdl_role_assignments. Its own
 * executable surface is the getTable() mapping plus the isManual()/isFromPlugin()
 * predicates keyed off the `component` field; the accessor/mutator behaviour is
 * inherited from AbstractMoodleEntity. All branches are exercised without a
 * Moodle runtime.
 *
 * @internal
 */
#[CoversClass(RoleAssignment::class)]
final class RoleAssignmentCoverageTest extends TestCase
{
    #[Test]
    public function getTableMapsToRoleAssignments(): void
    {
        self::assertSame('role_assignments', RoleAssignment::getTable());
    }

    #[Test]
    public function propertyDefaultsMatchMoodleSchema(): void
    {
        $assignment = new RoleAssignment();

        self::assertSame(0, $assignment->get_roleid());
        self::assertSame(0, $assignment->get_contextid());
        self::assertSame(0, $assignment->get_userid());
        self::assertSame(0, $assignment->get_modifierid());
        self::assertSame('', $assignment->get_component());
        self::assertSame(0, $assignment->get_itemid());
        self::assertSame(0, $assignment->get_sortorder());
    }

    #[Test]
    public function isManualIsTrueWhenComponentEmpty(): void
    {
        $assignment = new RoleAssignment();

        self::assertTrue($assignment->isManual());
        self::assertFalse($assignment->isFromPlugin());
    }

    #[Test]
    public function isFromPluginIsTrueWhenComponentSet(): void
    {
        $assignment = (new RoleAssignment())->with_component('enrol_manual');

        self::assertFalse($assignment->isManual());
        self::assertTrue($assignment->isFromPlugin());
    }

    #[Test]
    public function fromRecordHydratesAssignmentSpecificFields(): void
    {
        $assignment = RoleAssignment::fromRecord([
            'id' => '11',
            'roleid' => '5',
            'contextid' => '42',
            'userid' => '7',
            'modifierid' => '3',
            'component' => 'enrol_cohort',
            'itemid' => '99',
            'sortorder' => '2',
        ]);

        self::assertInstanceOf(RoleAssignment::class, $assignment);
        self::assertSame(11, $assignment->getId());
        self::assertSame(5, $assignment->get_roleid());
        self::assertSame(42, $assignment->get_contextid());
        self::assertSame(7, $assignment->get_userid());
        self::assertSame(3, $assignment->get_modifierid());
        self::assertSame('enrol_cohort', $assignment->get_component());
        self::assertSame(99, $assignment->get_itemid());
        self::assertSame(2, $assignment->get_sortorder());
        self::assertTrue($assignment->isFromPlugin());
    }
}
