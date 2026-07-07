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

use Middag\Moodle\Domain\Group\Cohort;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Cohort is a native Moodle entity whose only own executable member is the
 * getTable() mapping; the accessor/mutator behaviour it exposes is inherited
 * from AbstractMoodleEntity. The table name and the entity-specific property
 * surface are asserted without a Moodle runtime.
 *
 * @internal
 */
#[CoversClass(Cohort::class)]
final class CohortCoverageTest extends TestCase
{
    #[Test]
    public function getTableMapsToCohort(): void
    {
        self::assertSame('cohort', Cohort::getTable());
    }

    #[Test]
    public function propertyDefaultsMatchMoodleSchema(): void
    {
        $cohort = new Cohort();

        self::assertSame(0, $cohort->get_contextid());
        self::assertSame('', $cohort->get_name());
        self::assertNull($cohort->get_idnumber());
        self::assertNull($cohort->get_description());
        self::assertSame(0, $cohort->get_descriptionformat());
        self::assertSame(1, $cohort->get_visible());
        self::assertSame('', $cohort->get_component());
        self::assertNull($cohort->get_theme());
    }

    #[Test]
    public function fromRecordHydratesCohortSpecificFields(): void
    {
        $cohort = Cohort::fromRecord([
            'id' => '10',
            'contextid' => '3',
            'name' => 'Turma A',
            'idnumber' => 'C-001',
            'visible' => '0',
            'component' => 'local_middag',
        ]);

        self::assertInstanceOf(Cohort::class, $cohort);
        self::assertSame(10, $cohort->getId());
        self::assertSame(3, $cohort->get_contextid());
        self::assertSame('Turma A', $cohort->get_name());
        self::assertSame('C-001', $cohort->get_idnumber());
        self::assertSame(0, $cohort->get_visible());
        self::assertSame('local_middag', $cohort->get_component());
    }
}
