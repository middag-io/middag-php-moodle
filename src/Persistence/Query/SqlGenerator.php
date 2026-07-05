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
        $params = [];

        // Heuristic: Detect text columns for cross-db compatibility (Oracle/Postgres CLOBs)
        $is_text_column = str_contains($column, 'meta_value') || str_ends_with($column, 'description');

        switch ($op) {
            case Operator::EQ:
            case Operator::NEQ:
                $param_name = $param_prefix . '_v';
                $column_sql = $is_text_column ? DbSupport::sqlCompareText($column) : $column;

                $sql = sprintf('%s %s :%s', $column_sql, $op->value, $param_name);
                $params[$param_name] = $value;

                return [$sql, $params];

            case Operator::GT:
            case Operator::GTE:
            case Operator::LT:
            case Operator::LTE:
                $param_name = $param_prefix . '_v';
                $sql = sprintf('%s %s :%s', $column, $op->value, $param_name);
                $params[$param_name] = $value;

                return [$sql, $params];

            case Operator::LIKE:
                $param_name = $param_prefix . '_v';
                // sql_like handles ILIKE/LIKE differences automatically
                $sql = DbSupport::sqlLike($column, ':' . $param_name, false);
                $params[$param_name] = $value;

                return [$sql, $params];

            case Operator::IN:
            case Operator::NOT_IN:
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
                    $op === Operator::IN
                );

                return [sprintf('%s %s', $column, $in_sql), $in_params];

            case Operator::BETWEEN:
                $param_min = $param_prefix . '_min';
                $param_max = $param_prefix . '_max';
                $sql = sprintf('%s BETWEEN :%s AND :%s', $column, $param_min, $param_max);
                $params[$param_min] = $value;
                $params[$param_max] = $value2;

                return [$sql, $params];

            case Operator::IS:
            case Operator::IS_NOT:
                if ($value === null) {
                    return [sprintf('%s %s NULL', $column, $op->value), []];
                }
                if (is_bool($value)) {
                    $bool_val = $value ? 'TRUE' : 'FALSE';

                    return [sprintf('%s %s %s', $column, $op->value, $bool_val), []];
                }

                throw new coding_exception('IS / IS_NOT operator requires NULL or Boolean value.');

            case Operator::RAW:
                return [(string) $value, []];

            default:
                throw new coding_exception('Unsupported operator: ' . $op->name);
        }
    }
}
