<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Database;

use Middag\Framework\Database\Contract\SqlDialectInterface;
use Middag\Moodle\Database\MoodleSqlDialect;
use moodle_database;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Test MoodleSqlDialect.
 *
 * Emits Moodle-flavoured SQL fragments. inClause()/compareText() delegate to the
 * moodle_database stub (tests/bootstrap.php); the rest is pure string logic.
 *
 * @internal
 */
#[CoversClass(MoodleSqlDialect::class)]
final class MoodleSqlDialectCoverageTest extends TestCase
{
    private MoodleSqlDialect $dialect;

    protected function setUp(): void
    {
        $this->dialect = new MoodleSqlDialect(new moodle_database());
    }

    #[Test]
    public function tableWrapsLogicalNameInBraces(): void
    {
        $this->assertSame('{middag_items}', $this->dialect->table('middag_items'));
    }

    #[Test]
    public function inClauseWithEmptyValuesEmitsNeverTruePredicate(): void
    {
        $this->assertSame(['IN (NULL)', []], $this->dialect->inClause([]));
    }

    #[Test]
    public function inClauseDelegatesToGetInOrEqual(): void
    {
        [$sql, $params] = $this->dialect->inClause([1, 2], 'q');

        $this->assertSame('IN (:q1, :q2)', $sql);
        $this->assertSame(['q1' => 1, 'q2' => 2], $params);
    }

    #[Test]
    public function compareTextDelegatesToDbHelper(): void
    {
        $this->assertSame('CAST(col AS TEXT)', $this->dialect->compareText('col'));
    }

    #[Test]
    public function limitOffsetWithNeitherReturnsEmpty(): void
    {
        $this->assertSame('', $this->dialect->limitOffset(null, null));
    }

    #[Test]
    public function limitOffsetWithLimitOnly(): void
    {
        $this->assertSame(' LIMIT 25', $this->dialect->limitOffset(25, null));
    }

    #[Test]
    public function limitOffsetWithOffsetOnlyUsesMaxRowSentinel(): void
    {
        $this->assertSame(' LIMIT 18446744073709551615 OFFSET 10', $this->dialect->limitOffset(null, 10));
    }

    #[Test]
    public function limitOffsetWithBoth(): void
    {
        $this->assertSame(' LIMIT 25 OFFSET 50', $this->dialect->limitOffset(25, 50));
    }

    #[Test]
    public function lockClauseShareEmitsForShare(): void
    {
        $this->assertSame(' FOR SHARE', $this->dialect->lockClause('share'));
    }

    #[Test]
    public function lockClauseDefaultEmitsForUpdate(): void
    {
        $this->assertSame(' FOR UPDATE', $this->dialect->lockClause('exclusive'));
    }

    #[Test]
    public function upsertClauseWithUpdatesEmitsValuesAssignments(): void
    {
        $this->assertSame(
            ' ON DUPLICATE KEY UPDATE a = VALUES(a), b = VALUES(b)',
            $this->dialect->upsertClause(['id'], ['a', 'b'])
        );
    }

    #[Test]
    public function upsertClauseWithNoUpdatesNoOpsThePkColumn(): void
    {
        $this->assertSame(' ON DUPLICATE KEY UPDATE id = id', $this->dialect->upsertClause(['id'], []));
    }

    #[Test]
    public function upsertClauseWithNoUpdatesAndNoUniqueKeyIsEmpty(): void
    {
        $this->assertSame('', $this->dialect->upsertClause([], []));
    }

    #[Test]
    public function implementsSqlDialectInterface(): void
    {
        $this->assertInstanceOf(SqlDialectInterface::class, $this->dialect);
    }
}
