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

use Middag\Framework\Database\Contract\SqlDialectInterface;
use moodle_database;

use const SQL_PARAMS_NAMED;

/**
 * Moodle SQL dialect helpers for raw queries built by generic repositories.
 *
 * Implements the framework {@see SqlDialectInterface} seam against Moodle DML idioms:
 *  - `table()` wraps a logical name in braces (`middag_items` → `{middag_items}`)
 *    so Moodle's `$DB` prefixes it at execution time;
 *  - `compareText()` wraps in `sql_compare_text()` for TEXT/CLOB comparison;
 *  - `inClause()` delegates to `moodle_database::get_in_or_equal()` with named params;
 *  - `limitOffset()` / `lockClause()` / `upsertClause()` emit MySQL/MariaDB-family SQL
 *    (the baseline Moodle DB families: mysqli/mariadb; pgsql also accepts these forms).
 *
 * This is the only Moodle-specific SQL idiom layer; repositories that must emit
 * raw SQL depend on this contract, never on `$DB` directly.
 *
 * @internal
 */
final readonly class MoodleSqlDialect implements SqlDialectInterface
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

    public function limitOffset(?int $limit, ?int $offset): string
    {
        $hasLimit = $limit !== null && $limit >= 0;
        $hasOffset = $offset !== null && $offset > 0;

        if (!$hasLimit && !$hasOffset) {
            return '';
        }

        if (!$hasLimit) {
            // MySQL/MariaDB require a row-count when OFFSET is present; use the
            // documented max-rows sentinel so "offset without limit" stays valid.
            return ' LIMIT 18446744073709551615 OFFSET ' . $offset;
        }

        if (!$hasOffset) {
            return ' LIMIT ' . $limit;
        }

        return ' LIMIT ' . $limit . ' OFFSET ' . $offset;
    }

    public function lockClause(string $mode): string
    {
        return match ($mode) {
            'share' => ' FOR SHARE',
            default => ' FOR UPDATE',
        };
    }

    public function upsertClause(array $uniqueBy, array $update): string
    {
        if ($update === []) {
            // "do nothing" on conflict — MySQL has no NOTHING, so no-op the PK column.
            $noop = $uniqueBy[0] ?? null;

            return $noop === null ? '' : ' ON DUPLICATE KEY UPDATE ' . $noop . ' = ' . $noop;
        }

        $assignments = [];
        foreach ($update as $column) {
            $assignments[] = $column . ' = VALUES(' . $column . ')';
        }

        // $uniqueBy is implicit on MySQL (any unique/PK collision triggers the update);
        // the column list is honoured by the engine, not the SQL text.
        return ' ON DUPLICATE KEY UPDATE ' . implode(', ', $assignments);
    }
}
