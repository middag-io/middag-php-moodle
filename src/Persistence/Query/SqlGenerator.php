<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Persistence\Query;

use core\exception\coding_exception;
use Middag\Framework\Shared\Enum\Operator;
use Middag\Moodle\Support\DbSupport;

/**
 * SQL generator for query conditions.
 *
 * Translates abstract Query Conditions (field + operator + value) into Moodle-compatible SQL fragments.
 * Table-agnostic and reusable across repositories.
 *
 * @internal
 */
class SqlGenerator
{
    /**
     * Compile a single SQL condition based on Operator Enum.
     *
     * @param string   $column       SQL column reference
     * @param Operator $op           Enum Operator
     * @param mixed    $value        Primary value
     * @param mixed    $value2       Secondary value (for BETWEEN)
     * @param string   $param_prefix Unique prefix for parameter names
     *
     * @return array{0: string, 1: array<string, mixed>} [SQL Segment, Params Array]
     *
     * @throws coding_exception
     */
    public function compileCondition(
        string $column,
        Operator $op,
        mixed $value,
        mixed $value2,
        string $param_prefix
    ): array {
        // Heuristic: Detect text columns for cross-db compatibility (Oracle/Postgres CLOBs)
        $is_text_column = str_contains($column, 'meta_value') || str_ends_with($column, 'description');

        // Exhaustive over Operator — PHPStan flags a missing case at build time,
        // so there is no runtime "unsupported operator" default arm to guard.
        return match ($op) {
            Operator::EQ, Operator::NEQ => $this->compileBinary($column, $op, $value, $param_prefix, $is_text_column),
            Operator::GT, Operator::GTE, Operator::LT, Operator::LTE => $this->compileBinary($column, $op, $value, $param_prefix, false),
            Operator::LIKE => $this->compileLike($column, $value, $param_prefix),
            Operator::IN, Operator::NOT_IN => $this->compileInList($column, $op, $value, $param_prefix),
            Operator::BETWEEN => $this->compileBetween($column, $value, $value2, $param_prefix),
            Operator::IS, Operator::IS_NOT => $this->compileNullOrBool($column, $op, $value),
            Operator::RAW => [(string) $value, []],
        };
    }

    /**
     * Equality / relational comparison (EQ, NEQ, GT, GTE, LT, LTE).
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function compileBinary(string $column, Operator $op, mixed $value, string $param_prefix, bool $is_text_column): array
    {
        $param_name = $param_prefix . '_v';
        $column_sql = $is_text_column ? DbSupport::sqlCompareText($column) : $column;

        return [sprintf('%s %s :%s', $column_sql, $op->value, $param_name), [$param_name => $value]];
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function compileLike(string $column, mixed $value, string $param_prefix): array
    {
        $param_name = $param_prefix . '_v';

        // sql_like handles ILIKE/LIKE differences automatically
        return [DbSupport::sqlLike($column, ':' . $param_name, false), [$param_name => $value]];
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function compileInList(string $column, Operator $op, mixed $value, string $param_prefix): array
    {
        if (empty($value)) {
            return [$op === Operator::IN ? '1=0' : '1=1', []];
        }

        if (!is_array($value)) {
            $value = [$value];
        }

        [$in_sql, $in_params] = DbSupport::getInOrEqual(
            $value,
            SQL_PARAMS_NAMED,
            $param_prefix,
            $op === Operator::IN,
        );

        return [sprintf('%s %s', $column, $in_sql), $in_params];
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function compileBetween(string $column, mixed $value, mixed $value2, string $param_prefix): array
    {
        $param_min = $param_prefix . '_min';
        $param_max = $param_prefix . '_max';

        return [
            sprintf('%s BETWEEN :%s AND :%s', $column, $param_min, $param_max),
            [$param_min => $value, $param_max => $value2],
        ];
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     *
     * @throws coding_exception when the value is neither null nor boolean
     */
    private function compileNullOrBool(string $column, Operator $op, mixed $value): array
    {
        if ($value === null) {
            return [sprintf('%s %s NULL', $column, $op->value), []];
        }

        if (is_bool($value)) {
            return [sprintf('%s %s %s', $column, $op->value, $value ? 'TRUE' : 'FALSE'), []];
        }

        throw new coding_exception('IS / IS_NOT operator requires NULL or Boolean value.');
    }
}
