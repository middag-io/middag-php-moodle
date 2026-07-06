<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Persistence\Query;

use core\exception\coding_exception;
use Middag\Framework\Shared\Enum\Operator;
use Middag\Moodle\Persistence\Query\SqlGenerator;
use moodle_database;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Test SqlGenerator.
 *
 * The generator delegates DB-portable fragments (text comparison, LIKE,
 * IN/NOT IN) to DbSupport, which reads the global $DB. tests/bootstrap.php
 * provides a deterministic moodle_database stand-in installed here as $DB.
 *
 * @internal
 */
#[CoversClass(SqlGenerator::class)]
final class SqlGeneratorCoverageTest extends TestCase
{
    private SqlGenerator $generator;

    private mixed $previousDb;

    protected function setUp(): void
    {
        $this->generator = new SqlGenerator();
        $this->previousDb = $GLOBALS['DB'] ?? null;
        $GLOBALS['DB'] = new moodle_database();
    }

    protected function tearDown(): void
    {
        $GLOBALS['DB'] = $this->previousDb;
    }

    #[Test]
    public function equalsCompilesBoundParameter(): void
    {
        [$sql, $params] = $this->generator->compileCondition('col', Operator::EQ, 5, null, 'px');

        $this->assertSame('col = :px_v', $sql);
        $this->assertSame(['px_v' => 5], $params);
    }

    #[Test]
    public function notEqualsUsesOperatorValue(): void
    {
        [$sql] = $this->generator->compileCondition('col', Operator::NEQ, 5, null, 'px');

        $this->assertSame('col <> :px_v', $sql);
    }

    #[Test]
    public function metaValueColumnIsWrappedForTextComparison(): void
    {
        [$sql] = $this->generator->compileCondition('meta_value', Operator::EQ, 'x', null, 'px');

        $this->assertSame('CAST(meta_value AS TEXT) = :px_v', $sql);
    }

    #[Test]
    public function descriptionSuffixColumnIsWrappedForTextComparison(): void
    {
        [$sql] = $this->generator->compileCondition('item_description', Operator::EQ, 'x', null, 'px');

        $this->assertSame('CAST(item_description AS TEXT) = :px_v', $sql);
    }

    #[Test]
    public function greaterThanCompilesComparison(): void
    {
        [$sql, $params] = $this->generator->compileCondition('n', Operator::GT, 10, null, 'px');

        $this->assertSame('n > :px_v', $sql);
        $this->assertSame(['px_v' => 10], $params);
    }

    #[Test]
    public function lessThanOrEqualCompilesComparison(): void
    {
        [$sql] = $this->generator->compileCondition('n', Operator::LTE, 10, null, 'px');

        $this->assertSame('n <= :px_v', $sql);
    }

    #[Test]
    public function likeDelegatesToDbSupport(): void
    {
        [$sql, $params] = $this->generator->compileCondition('col', Operator::LIKE, 'foo%', null, 'px');

        $this->assertSame('col LIKE :px_v', $sql);
        $this->assertSame(['px_v' => 'foo%'], $params);
    }

    #[Test]
    public function emptyInYieldsAlwaysFalse(): void
    {
        [$sql, $params] = $this->generator->compileCondition('col', Operator::IN, [], null, 'px');

        $this->assertSame('1=0', $sql);
        $this->assertSame([], $params);
    }

    #[Test]
    public function emptyNotInYieldsAlwaysTrue(): void
    {
        [$sql] = $this->generator->compileCondition('col', Operator::NOT_IN, [], null, 'px');

        $this->assertSame('1=1', $sql);
    }

    #[Test]
    public function scalarInValueIsWrappedIntoAnArray(): void
    {
        [$sql, $params] = $this->generator->compileCondition('col', Operator::IN, 5, null, 'px');

        $this->assertSame('col IN (:px1)', $sql);
        $this->assertSame(['px1' => 5], $params);
    }

    #[Test]
    public function arrayInValueBuildsBoundList(): void
    {
        [$sql, $params] = $this->generator->compileCondition('col', Operator::IN, [1, 2], null, 'px');

        $this->assertSame('col IN (:px1, :px2)', $sql);
        $this->assertSame(['px1' => 1, 'px2' => 2], $params);
    }

    #[Test]
    public function betweenCompilesMinMaxParameters(): void
    {
        [$sql, $params] = $this->generator->compileCondition('col', Operator::BETWEEN, 1, 10, 'px');

        $this->assertSame('col BETWEEN :px_min AND :px_max', $sql);
        $this->assertSame(['px_min' => 1, 'px_max' => 10], $params);
    }

    #[Test]
    public function isNullCompilesNullComparison(): void
    {
        [$sql, $params] = $this->generator->compileCondition('col', Operator::IS, null, null, 'px');

        $this->assertSame('col IS NULL', $sql);
        $this->assertSame([], $params);
    }

    #[Test]
    public function isNotNullCompilesNullComparison(): void
    {
        [$sql] = $this->generator->compileCondition('col', Operator::IS_NOT, null, null, 'px');

        $this->assertSame('col IS NOT NULL', $sql);
    }

    #[Test]
    public function isTrueCompilesBooleanLiteral(): void
    {
        [$sql] = $this->generator->compileCondition('col', Operator::IS, true, null, 'px');

        $this->assertSame('col IS TRUE', $sql);
    }

    #[Test]
    public function isFalseCompilesBooleanLiteral(): void
    {
        [$sql] = $this->generator->compileCondition('col', Operator::IS, false, null, 'px');

        $this->assertSame('col IS FALSE', $sql);
    }

    #[Test]
    public function isWithNonNullNonBooleanValueThrows(): void
    {
        $this->expectException(coding_exception::class);

        $this->generator->compileCondition('col', Operator::IS, 'nope', null, 'px');
    }

    #[Test]
    public function rawReturnsValueVerbatim(): void
    {
        [$sql, $params] = $this->generator->compileCondition('ignored', Operator::RAW, '1 = 1', null, 'px');

        $this->assertSame('1 = 1', $sql);
        $this->assertSame([], $params);
    }
}
