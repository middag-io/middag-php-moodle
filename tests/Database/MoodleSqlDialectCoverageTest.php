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
 * Covers the Moodle-specific overrides only: table() bracing and the
 * inClause()/compareText() delegations to the moodle_database stub
 * (tests/bootstrap.php). The inherited MySQL idioms (limitOffset/lockClause/
 * upsertClause) live in and are covered by the framework MysqlSqlDialect.
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
    public function implementsSqlDialectInterface(): void
    {
        $this->assertInstanceOf(SqlDialectInterface::class, $this->dialect);
    }
}
