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

use Middag\Moodle\Domain\Group\Group;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Group is a native Moodle entity whose only own executable member is the
 * getTable() mapping; the accessor/mutator behaviour it exposes is inherited
 * from AbstractMoodleEntity. The table name and the entity-specific property
 * surface are asserted without a Moodle runtime.
 *
 * @internal
 */
#[CoversClass(Group::class)]
final class GroupCoverageTest extends TestCase
{
    #[Test]
    public function getTableMapsToGroups(): void
    {
        self::assertSame('groups', Group::getTable());
    }

    #[Test]
    public function propertyDefaultsMatchMoodleSchema(): void
    {
        $group = new Group();

        self::assertSame(0, $group->get_courseid());
        self::assertSame('', $group->get_idnumber());
        self::assertSame('', $group->get_name());
        self::assertNull($group->get_description());
        self::assertSame(0, $group->get_descriptionformat());
        self::assertNull($group->get_enrolmentkey());
        self::assertSame(0, $group->get_picture());
        self::assertSame(0, $group->get_visibility());
        self::assertSame(1, $group->get_participation());
    }

    #[Test]
    public function fromRecordHydratesGroupSpecificFields(): void
    {
        $group = Group::fromRecord([
            'id' => '42',
            'courseid' => '7',
            'idnumber' => 'G-01',
            'name' => 'Grupo Alfa',
            'enrolmentkey' => 'secret',
            'visibility' => '2',
            'participation' => '0',
        ]);

        self::assertInstanceOf(Group::class, $group);
        self::assertSame(42, $group->getId());
        self::assertSame(7, $group->get_courseid());
        self::assertSame('G-01', $group->get_idnumber());
        self::assertSame('Grupo Alfa', $group->get_name());
        self::assertSame('secret', $group->get_enrolmentkey());
        self::assertSame(2, $group->get_visibility());
        self::assertSame(0, $group->get_participation());
    }
}
