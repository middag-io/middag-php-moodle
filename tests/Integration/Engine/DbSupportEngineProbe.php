<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Integration\Engine;

use Closure;
use Middag\Moodle\Support\DbSupport;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;
use Throwable;
use xmldb_table;

/**
 * Cross-engine probe for the DbSupport `sql*` helpers.
 *
 * Runs inside an installed Moodle site (see run-db-support-probe.php). Every
 * check builds SQL with a DbSupport helper, executes it against a small
 * fixture table on the site's real database and compares the result with the
 * expected value. Where Moodle documents a different result per engine family
 * (accent-insensitive comparison is MySQL/MariaDB only), the expectation is
 * explicit per family and an unknown family fails.
 *
 * The class itself loads without Moodle: only the checks touch `$DB`.
 *
 * @internal
 */
final class DbSupportEngineProbe
{
    public const string TABLE = 'middag_dbsprobe';

    private const string FAMILY_MYSQL = 'mysql';

    private const string FAMILY_POSTGRES = 'postgres';

    /**
     * Fixture rows, keyed by id. Ids are imported as-is.
     *
     * @var array<int, array<string, null|int|string>>
     */
    private const array ROWS = [
        1 => ['firstname' => 'Ana', 'lastname' => 'Silva', 'label' => 'Hello World', 'body' => 'alpha', 'num' => 7, 'numchar' => '42', 'realchar' => '3.5', 'txtnum' => '17', 'flags' => 6],
        2 => ['firstname' => 'bob', 'lastname' => 'Stone', 'label' => 'foo_bar%baz', 'body' => 'gamma', 'num' => 12, 'numchar' => '-5', 'realchar' => '-0.25', 'txtnum' => '-3', 'flags' => 3],
        3 => ['firstname' => 'Zoe', 'lastname' => 'Ação', 'label' => '', 'body' => '', 'num' => 0, 'numchar' => '0', 'realchar' => '0', 'txtnum' => '0', 'flags' => 0],
        4 => ['firstname' => 'Bea', 'lastname' => 'acao', 'label' => null, 'body' => null, 'num' => 5, 'numchar' => '8', 'realchar' => '1.25', 'txtnum' => '8', 'flags' => 1],
        5 => ['firstname' => 'cid', 'lastname' => 'Lee', 'label' => 'fooxbarybaz', 'body' => 'beta', 'num' => 3, 'numchar' => '100', 'realchar' => '2', 'txtnum' => '100', 'flags' => 7],
    ];

    /** @var resource */
    private $out;

    /**
     * @param null|resource $out stream the report is written to (default STDOUT)
     */
    public function __construct($out = null)
    {
        $this->out = $out ?? STDOUT;
    }

    /**
     * Every public static `sql*` method of DbSupport, sorted.
     *
     * @return list<string>
     */
    public static function publicSqlMethods(): array
    {
        $methods = array_filter(
            (new ReflectionClass(DbSupport::class))->getMethods(ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_STATIC),
            static fn (ReflectionMethod $m): bool => $m->isStatic() && str_starts_with($m->getName(), 'sql')
        );
        $names = array_map(static fn (ReflectionMethod $m): string => $m->getName(), $methods);
        sort($names);

        return array_values($names);
    }

    /**
     * The DbSupport methods the checks prove, sorted.
     *
     * @return list<string>
     */
    public function coveredMethods(): array
    {
        $names = array_values(array_unique(array_map(static fn (array $c): string => $c[0], $this->checks())));
        sort($names);

        return $names;
    }

    /**
     * Coverage problems: methods with no check, and checks naming no method.
     *
     * @return list<string>
     */
    public function coverageErrors(): array
    {
        $expected = self::publicSqlMethods();
        $covered = $this->coveredMethods();
        $errors = [];
        foreach (array_diff($expected, $covered) as $missing) {
            $errors[] = "DbSupport::{$missing}() has no check";
        }
        foreach (array_diff($covered, $expected) as $unknown) {
            $errors[] = "check names DbSupport::{$unknown}(), which is not a public static sql* method";
        }

        return $errors;
    }

    /**
     * Runs the coverage gate and every check. Returns the process exit code.
     */
    public function run(): int
    {
        $this->writeHeader();
        $coverage = $this->coverageErrors();
        foreach ($coverage as $error) {
            $this->line("FAIL coverage: {$error}");
        }

        $this->createFixture();

        try {
            $results = $this->runChecks();
        } finally {
            $this->dropFixture();
        }

        return $this->report($results, $coverage);
    }

    /**
     * All checks as [DbSupport method, label, assertion].
     *
     * @return list<array{0: string, 1: string, 2: Closure(): void}>
     */
    public function checks(): array
    {
        return array_merge(
            $this->likeChecks(),
            $this->equalityChecks(),
            $this->regexChecks(),
            $this->stringChecks(),
            $this->orderChecks(),
            $this->numericChecks(),
            $this->castChecks(),
            $this->emptinessChecks(),
            $this->setChecks(),
        );
    }

    /**
     * @return list<array{0: string, 1: string, 2: Closure(): void}>
     */
    private function likeChecks(): array
    {
        return [
            ['sqlLike', 'case-sensitive match', fn () => $this->expectIds([1], DbSupport::sqlLike('label', ':p'), ['p' => 'Hello%'])],
            ['sqlLike', 'case-sensitive rejects other case', fn () => $this->expectIds([], DbSupport::sqlLike('label', ':p'), ['p' => 'hello%'])],
            ['sqlLike', 'case-insensitive match', fn () => $this->expectIds([1], DbSupport::sqlLike('label', ':p', false), ['p' => 'hello%'])],
            ['sqlLike', 'NOT LIKE, case-insensitive', fn () => $this->expectIds([2, 3, 5], DbSupport::sqlLike('label', ':p', false, true, true), ['p' => 'hello%'])],
            ['sqlLike', 'case-insensitive, accent-sensitive', fn () => $this->expectIds([4], DbSupport::sqlLike('lastname', ':p', false, true), ['p' => 'ACAO'])],
            ['sqlLike', 'case- and accent-insensitive', fn () => $this->expectIds(
                $this->perFamily([self::FAMILY_MYSQL => [3, 4], self::FAMILY_POSTGRES => [4]]),
                DbSupport::sqlLike('lastname', ':p', false, false),
                ['p' => 'acao']
            )],
            ['sqlLike', 'custom escape character', fn () => $this->expectIds([2], DbSupport::sqlLike('label', ':p', true, true, false, '|'), ['p' => DbSupport::sqlLikeEscape('foo_bar', '|') . '%'])],
            ['sqlLikeEscape', 'unescaped wildcard control', fn () => $this->expectIds([2, 5], DbSupport::sqlLike('label', ':p'), ['p' => 'foo_bar%'])],
            ['sqlLikeEscape', 'escaped _ and % match literally', fn () => $this->expectIds([2], DbSupport::sqlLike('label', ':p'), ['p' => DbSupport::sqlLikeEscape('foo_bar%') . '%'])],
        ];
    }

    /**
     * @return list<array{0: string, 1: string, 2: Closure(): void}>
     */
    private function equalityChecks(): array
    {
        return [
            ['sqlEqual', 'case-sensitive match', fn () => $this->expectIds([1], DbSupport::sqlEqual('firstname', ':p'), ['p' => 'Ana'])],
            ['sqlEqual', 'case-sensitive rejects other case', fn () => $this->expectIds([], DbSupport::sqlEqual('firstname', ':p'), ['p' => 'ana'])],
            ['sqlEqual', 'case-insensitive match', fn () => $this->expectIds([1], DbSupport::sqlEqual('firstname', ':p', false), ['p' => 'ana'])],
            ['sqlEqual', 'not equal, case-insensitive', fn () => $this->expectIds([2, 3, 4, 5], DbSupport::sqlEqual('firstname', ':p', false, true, true), ['p' => 'ana'])],
            ['sqlEqual', 'case-insensitive, accent-sensitive', fn () => $this->expectIds([4], DbSupport::sqlEqual('lastname', ':p', false, true), ['p' => 'ACAO'])],
            ['sqlEqual', 'case- and accent-insensitive', fn () => $this->expectIds(
                $this->perFamily([self::FAMILY_MYSQL => [3, 4], self::FAMILY_POSTGRES => [4]]),
                DbSupport::sqlEqual('lastname', ':p', false, false),
                ['p' => 'acao']
            )],
            ['sqlCompareText', 'TEXT column compared to a value', fn () => $this->expectIds([1], DbSupport::sqlCompareText('body') . ' = :p', ['p' => 'alpha'])],
        ];
    }

    /**
     * @return list<array{0: string, 1: string, 2: Closure(): void}>
     */
    private function regexChecks(): array
    {
        $word = static fn (string $inner): string => DbSupport::sqlRegexGetWordBeginningBoundaryMarker() . $inner . DbSupport::sqlRegexGetWordEndBoundaryMarker();

        return [
            ['sqlRegexSupported', 'driver supports regex', fn () => $this->expectSame(true, DbSupport::sqlRegexSupported())],
            ['sqlRegex', 'positive match', fn () => $this->expectIds([2, 5], 'label ' . DbSupport::sqlRegex() . ' :p', ['p' => '^foo'])],
            ['sqlRegex', 'negated match', fn () => $this->expectIds([1, 3], 'label ' . DbSupport::sqlRegex(true) . ' :p', ['p' => '^foo'])],
            ['sqlRegex', 'case-insensitive by default', fn () => $this->expectIds([1], 'label ' . DbSupport::sqlRegex() . ' :p', ['p' => '^HELLO'])],
            ['sqlRegexGetWordBeginningBoundaryMarker', 'whole word matches', fn () => $this->expectIds([1], 'label ' . DbSupport::sqlRegex() . ' :p', ['p' => $word('World')])],
            ['sqlRegexGetWordBeginningBoundaryMarker', 'mid-word start does not match', fn () => $this->expectIds([], 'label ' . DbSupport::sqlRegex() . ' :p', ['p' => DbSupport::sqlRegexGetWordBeginningBoundaryMarker() . 'orld'])],
            ['sqlRegexGetWordEndBoundaryMarker', 'word end matches', fn () => $this->expectIds([1], 'label ' . DbSupport::sqlRegex() . ' :p', ['p' => 'Hello' . DbSupport::sqlRegexGetWordEndBoundaryMarker()])],
            ['sqlRegexGetWordEndBoundaryMarker', 'mid-word end does not match', fn () => $this->expectIds([], 'label ' . DbSupport::sqlRegex() . ' :p', ['p' => 'Hell' . DbSupport::sqlRegexGetWordEndBoundaryMarker()])],
        ];
    }

    /**
     * @return list<array{0: string, 1: string, 2: Closure(): void}>
     */
    private function stringChecks(): array
    {
        return [
            ['sqlFullname', 'default columns', fn () => $this->expectValue('Ana Silva', DbSupport::sqlFullname(), 1)],
            ['sqlFullname', 'explicit columns', fn () => $this->expectValue('Silva Ana', DbSupport::sqlFullname('lastname', 'firstname'), 1)],
            ['sqlConcat', 'strings and literals', fn () => $this->expectValue('<Ana>', DbSupport::sqlConcat("'<'", 'firstname', "'>'"), 1)],
            ['sqlConcat', 'string with integer column', fn () => $this->expectValue('Ana-7', DbSupport::sqlConcat('firstname', "'-'", 'num'), 1)],
            ['sqlConcatJoin', 'separator between columns', fn () => $this->expectValue('Ana-Silva', DbSupport::sqlConcatJoin("'-'", ['firstname', 'lastname']), 1)],
            ['sqlLength', 'ASCII length', fn () => $this->expectNumber(11, DbSupport::sqlLength('label'), 1)],
            ['sqlLength', 'multibyte length counts characters', fn () => $this->expectNumber(4, DbSupport::sqlLength('lastname'), 3)],
            ['sqlSubstr', 'start and length', fn () => $this->expectValue('ell', DbSupport::sqlSubstr('label', 2, 3), 1)],
            ['sqlSubstr', 'start to the end', fn () => $this->expectValue('World', DbSupport::sqlSubstr('label', 7), 1)],
            ['sqlSubstr', 'multibyte characters', fn () => $this->expectValue('çã', DbSupport::sqlSubstr('lastname', 2, 2), 3)],
            ['sqlPosition', 'needle found', fn () => $this->expectNumber(7, DbSupport::sqlPosition("'World'", 'label'), 1)],
            ['sqlPosition', 'needle missing', fn () => $this->expectNumber(0, DbSupport::sqlPosition("'World'", 'label'), 2)],
            ['sqlGroupConcat', 'sorted string aggregate', fn () => $this->expectScalar('Ana,bob,Zoe', 'SELECT ' . DbSupport::sqlGroupConcat('firstname', ',', 'id') . ' FROM {' . self::TABLE . '} WHERE id <= 3')],
            ['sqlGroupConcat', 'integer aggregate, descending', fn () => $this->expectScalar('12|7', 'SELECT ' . DbSupport::sqlGroupConcat('num', '|', 'num DESC') . ' FROM {' . self::TABLE . '} WHERE id <= 2')],
            ['sqlNullFromClause', 'SELECT without a table', fn () => $this->expectScalar('1', 'SELECT 1 AS v ' . DbSupport::sqlNullFromClause())],
        ];
    }

    /**
     * @return list<array{0: string, 1: string, 2: Closure(): void}>
     */
    private function orderChecks(): array
    {
        return [
            ['sqlOrderByText', 'TEXT column ordering', fn () => $this->expectOrder([1, 5, 2], 'id IN (1, 2, 5)', DbSupport::sqlOrderByText('body'))],
            ['sqlOrderByNull', 'NULL first when ascending', fn () => $this->expectOrder([4, 3], 'id IN (3, 4)', DbSupport::sqlOrderByNull('label'))],
            ['sqlOrderByNull', 'NULL last when descending', fn () => $this->expectOrder([3, 4], 'id IN (3, 4)', DbSupport::sqlOrderByNull('label', SORT_DESC))],
            ['sqlOrderByNull', 'NULL first among all rows', fn () => $this->expectFirstId(4, DbSupport::sqlOrderByNull('label'))],
        ];
    }

    /**
     * @return list<array{0: string, 1: string, 2: Closure(): void}>
     */
    private function numericChecks(): array
    {
        return [
            ['sqlBitand', 'literals', fn () => $this->expectNumber(2, DbSupport::sqlBitand(6, 3), 1)],
            ['sqlBitand', 'column and literal', fn () => $this->expectNumber(2, DbSupport::sqlBitand('flags', 3), 1)],
            ['sqlBitor', 'literals', fn () => $this->expectNumber(7, DbSupport::sqlBitor(6, 3), 1)],
            ['sqlBitor', 'column and literal', fn () => $this->expectNumber(7, DbSupport::sqlBitor('flags', 4), 2)],
            ['sqlBitxor', 'literals', fn () => $this->expectNumber(5, DbSupport::sqlBitxor(6, 3), 1)],
            ['sqlBitxor', 'column and literal', fn () => $this->expectNumber(5, DbSupport::sqlBitxor('flags', 3), 1)],
            // MySQL returns ~x as an unsigned 64-bit value: mask it to compare across engines.
            ['sqlBitnot', 'masked complement of a column', fn () => $this->expectNumber(1, DbSupport::sqlBitand(DbSupport::sqlBitnot('flags'), 7), 1)],
            ['sqlBitnot', 'masked complement of a literal', fn () => $this->expectNumber(4, DbSupport::sqlBitand(DbSupport::sqlBitnot(3), 7), 1)],
            ['sqlModulo', 'column and literal', fn () => $this->expectNumber(2, DbSupport::sqlModulo('num', 5), 2)],
            ['sqlModulo', 'literals', fn () => $this->expectNumber(1, DbSupport::sqlModulo(7, 3), 1)],
            ['sqlCeil', 'positive fraction', fn () => $this->expectNumber(4, DbSupport::sqlCeil(DbSupport::sqlCastChar2real('realchar')), 1)],
            ['sqlCeil', 'negative fraction', fn () => $this->expectNumber(0, DbSupport::sqlCeil(DbSupport::sqlCastChar2real('realchar')), 2)],
        ];
    }

    /**
     * @return list<array{0: string, 1: string, 2: Closure(): void}>
     */
    private function castChecks(): array
    {
        $table = '{' . self::TABLE . '}';

        return [
            ['sqlCastToChar', 'integer column compared as text', fn () => $this->expectIds([1], DbSupport::sqlCastToChar('num') . ' = :p', ['p' => '7'])],
            ['sqlCastToChar', 'integer column in LIKE', fn () => $this->expectIds([2], DbSupport::sqlLike(DbSupport::sqlCastToChar('num'), ':p'), ['p' => '1%'])],
            ['sqlCastChar2int', 'sum of CHAR column', fn () => $this->expectScalarNumber(145, 'SELECT SUM(' . DbSupport::sqlCastChar2int('numchar') . ") FROM {$table}")],
            ['sqlCastChar2int', 'numeric ordering of CHAR column', fn () => $this->expectOrder([2, 3, 4, 1, 5], '1 = 1', DbSupport::sqlCastChar2int('numchar'))],
            ['sqlCastChar2int', 'sum of TEXT column', fn () => $this->expectScalarNumber(122, 'SELECT SUM(' . DbSupport::sqlCastChar2int('txtnum', true) . ") FROM {$table}")],
            ['sqlCastChar2real', 'sum of CHAR column', fn () => $this->expectScalarNumber(6.5, 'SELECT SUM(' . DbSupport::sqlCastChar2real('realchar') . ") FROM {$table}")],
            ['sqlCastChar2real', 'comparison with a fraction', fn () => $this->expectIds([1, 5], DbSupport::sqlCastChar2real('realchar') . ' > 1.5')],
            ['sqlCastChar2real', 'sum of TEXT column', fn () => $this->expectScalarNumber(122, 'SELECT SUM(' . DbSupport::sqlCastChar2real('txtnum', true) . ") FROM {$table}")],
        ];
    }

    /**
     * @return list<array{0: string, 1: string, 2: Closure(): void}>
     */
    private function emptinessChecks(): array
    {
        return [
            ['sqlIsempty', 'nullable CHAR column', fn () => $this->expectIds([3], DbSupport::sqlIsempty(self::TABLE, 'label', true, false))],
            ['sqlIsempty', 'nullable TEXT column', fn () => $this->expectIds([3], DbSupport::sqlIsempty(self::TABLE, 'body', true, true))],
            ['sqlIsnotempty', 'nullable CHAR column', fn () => $this->expectIds([1, 2, 5], DbSupport::sqlIsnotempty(self::TABLE, 'label', true, false))],
            ['sqlIsnotempty', 'nullable TEXT column', fn () => $this->expectIds([1, 2, 5], DbSupport::sqlIsnotempty(self::TABLE, 'body', true, true))],
        ];
    }

    /**
     * @return list<array{0: string, 1: string, 2: Closure(): void}>
     */
    private function setChecks(): array
    {
        $table = '{' . self::TABLE . '}';

        return [
            ['sqlIntersect', 'two selects, one field', fn () => $this->expectFieldset([1, 2], DbSupport::sqlIntersect([
                "SELECT id FROM {$table} WHERE num > 4",
                "SELECT id FROM {$table} WHERE flags > 2",
            ], 'id'))],
            ['sqlIntersect', 'three selects, two fields', fn () => $this->expectFieldset([1], DbSupport::sqlIntersect([
                "SELECT id, num FROM {$table} WHERE num > 4",
                "SELECT id, num FROM {$table} WHERE flags > 2",
                "SELECT id, num FROM {$table} WHERE num < 10",
            ], 'id, num'))],
        ];
    }

    /**
     * @return array<string, array{ok: int, failures: list<string>}>
     */
    private function runChecks(): array
    {
        $results = [];
        foreach ($this->checks() as [$method, $label, $assert]) {
            $results[$method] ??= ['ok' => 0, 'failures' => []];

            try {
                $assert();
                ++$results[$method]['ok'];
            } catch (Throwable $e) {
                $results[$method]['failures'][] = $label . ': ' . $e::class . ': ' . $e->getMessage();
            }
        }
        ksort($results);

        return $results;
    }

    /**
     * @param array<string, array{ok: int, failures: list<string>}> $results
     * @param list<string>                                          $coverage
     */
    private function report(array $results, array $coverage): int
    {
        $failed = 0;
        foreach ($results as $method => $result) {
            $total = $result['ok'] + count($result['failures']);
            if ($result['failures'] === []) {
                $this->line(sprintf('PASS %s (%d/%d)', $method, $result['ok'], $total));

                continue;
            }
            ++$failed;
            $this->line(sprintf('FAIL %s (%d/%d)', $method, $result['ok'], $total));
            foreach ($result['failures'] as $failure) {
                $this->line('     - ' . $failure);
            }
        }

        $proven = count($results) - $failed;
        $this->line(sprintf(
            'Summary: %d/%d DbSupport sql* methods proven, %d failed, %d coverage errors.',
            $proven,
            count(self::publicSqlMethods()),
            $failed,
            count($coverage)
        ));

        return ($failed === 0 && $coverage === []) ? 0 : 1;
    }

    private function writeHeader(): void
    {
        global $CFG, $DB;

        $server = $DB->get_server_info();
        $this->line(sprintf(
            'DbSupport engine probe: Moodle %s, driver %s, family %s, server %s, PHP %s',
            $CFG->release ?? '?',
            $CFG->dbtype ?? '?',
            $DB->get_dbfamily(),
            $server['version'] ?? '?',
            PHP_VERSION
        ));
    }

    private function createFixture(): void
    {
        global $DB;

        $dbman = $DB->get_manager();
        $table = new xmldb_table(self::TABLE);
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('firstname', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, '');
        $table->add_field('lastname', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, '');
        $table->add_field('label', XMLDB_TYPE_CHAR, '100', null, null, null, null);
        $table->add_field('body', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('num', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('numchar', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, '');
        $table->add_field('realchar', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, '');
        $table->add_field('txtnum', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('flags', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }
        $dbman->create_table($table);

        foreach (self::ROWS as $id => $row) {
            $DB->import_record(self::TABLE, (object) (['id' => $id] + $row));
        }
    }

    private function dropFixture(): void
    {
        global $DB;

        $dbman = $DB->get_manager();
        $table = new xmldb_table(self::TABLE);
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }
    }

    /**
     * @param array<string, mixed> $byFamily
     */
    private function perFamily(array $byFamily): mixed
    {
        global $DB;

        $family = $DB->get_dbfamily();
        if (!array_key_exists($family, $byFamily)) {
            throw new RuntimeException("no expectation for database family '{$family}'");
        }

        return $byFamily[$family];
    }

    /**
     * @param list<int>            $expected
     * @param array<string, mixed> $params
     */
    private function expectIds(array $expected, string $where, array $params = []): void
    {
        global $DB;

        $sql = 'SELECT id FROM {' . self::TABLE . "} WHERE {$where} ORDER BY id";
        $actual = array_map('intval', array_values($DB->get_fieldset_sql($sql, $params)));
        $this->expectSame($expected, $actual, $sql);
    }

    /**
     * @param list<int> $expected
     */
    private function expectOrder(array $expected, string $where, string $orderBy): void
    {
        global $DB;

        $sql = 'SELECT id FROM {' . self::TABLE . "} WHERE {$where} ORDER BY {$orderBy}, id";
        $actual = array_map('intval', array_values($DB->get_fieldset_sql($sql)));
        $this->expectSame($expected, $actual, $sql);
    }

    private function expectFirstId(int $expected, string $orderBy): void
    {
        global $DB;

        $sql = 'SELECT id FROM {' . self::TABLE . "} ORDER BY {$orderBy}, id";
        $actual = array_map('intval', array_values($DB->get_fieldset_sql($sql)));
        $this->expectSame($expected, $actual[0] ?? null, $sql);
    }

    /**
     * @param list<int> $expected sorted ids
     */
    private function expectFieldset(array $expected, string $sql): void
    {
        global $DB;

        $actual = array_map('intval', array_values($DB->get_fieldset_sql($sql)));
        sort($actual);
        $this->expectSame($expected, $actual, $sql);
    }

    private function expectValue(string $expected, string $expression, int $id): void
    {
        $sql = "SELECT {$expression} AS v FROM {" . self::TABLE . '} WHERE id = :rowid';
        $this->expectSame($expected, $this->fetchValue($sql, ['rowid' => $id]), $sql);
    }

    private function expectNumber(float|int $expected, string $expression, int $id): void
    {
        $sql = "SELECT {$expression} AS v FROM {" . self::TABLE . '} WHERE id = :rowid';
        $this->expectNumeric($expected, $this->fetchValue($sql, ['rowid' => $id]), $sql);
    }

    private function expectScalar(string $expected, string $sql): void
    {
        $this->expectSame($expected, $this->fetchValue($sql), $sql);
    }

    private function expectScalarNumber(float|int $expected, string $sql): void
    {
        $this->expectNumeric($expected, $this->fetchValue($sql), $sql);
    }

    /**
     * @param array<string, mixed> $params
     */
    private function fetchValue(string $sql, array $params = []): mixed
    {
        global $DB;

        $value = $DB->get_field_sql($sql, $params, MUST_EXIST);

        return is_numeric($value) || $value === null ? $value : (string) $value;
    }

    private function expectNumeric(float|int $expected, mixed $actual, string $sql): void
    {
        if (!is_numeric($actual) || abs((float) $actual - (float) $expected) > 1e-9) {
            throw new RuntimeException(sprintf('expected %s, got %s; SQL: %s', var_export($expected, true), var_export($actual, true), $sql));
        }
    }

    private function expectSame(mixed $expected, mixed $actual, string $sql = ''): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException(sprintf(
                'expected %s, got %s%s',
                json_encode($expected, JSON_UNESCAPED_UNICODE),
                json_encode($actual, JSON_UNESCAPED_UNICODE),
                $sql === '' ? '' : '; SQL: ' . $sql
            ));
        }
    }

    private function line(string $text): void
    {
        fwrite($this->out, $text . PHP_EOL);
    }
}
