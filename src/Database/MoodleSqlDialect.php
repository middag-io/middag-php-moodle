<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Database;

use Middag\Framework\Database\MysqlSqlDialect;
use moodle_database;

use const SQL_PARAMS_NAMED;

/**
 * Moodle SQL dialect helpers for raw queries built by generic repositories.
 *
 * Extends the framework {@see MysqlSqlDialect} — Moodle's baseline DB families
 * (mysqli/mariadb) share its LIMIT/OFFSET sentinel, `ON DUPLICATE KEY` upsert
 * and `FOR UPDATE` lock idioms, inherited verbatim. Only the Moodle-specific
 * name/text/IN idioms are overridden here:
 *  - `table()` wraps a logical name in braces (`middag_items` → `{middag_items}`)
 *    so Moodle's `$DB` prefixes it at execution time;
 *  - `compareText()` wraps in `sql_compare_text()` for TEXT/CLOB comparison;
 *  - `inClause()` delegates to `moodle_database::get_in_or_equal()` with named params.
 *
 * This is the only Moodle-specific SQL idiom layer; repositories that must emit
 * raw SQL depend on this contract, never on `$DB` directly.
 *
 * @internal
 */
final readonly class MoodleSqlDialect extends MysqlSqlDialect
{
    public function __construct(private moodle_database $db) {}

    public function table(string $logicalName): string
    {
        return '{' . $logicalName . '}';
    }

    public function inClause(array $values, string $prefix = 'p'): array
    {
        if ($values === []) {
            // Degenerate IN () — emit a never-true predicate with no params.
            return ['IN (NULL)', []];
        }

        [$sql, $params] = $this->db->get_in_or_equal($values, SQL_PARAMS_NAMED, $prefix);

        return [$sql, $params];
    }

    public function compareText(string $column): string
    {
        return $this->db->sql_compare_text($column);
    }
}
