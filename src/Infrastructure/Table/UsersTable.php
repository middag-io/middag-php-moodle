<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Infrastructure\Table;

use core\context;
use core_table\sql_table;

/**
 * Abstract base for MIDDAG user list tables.
 *
 * Extends Moodle's `sql_table` with a standard column set and context-aware
 * initialisation for use in MIDDAG admin and extension views.
 *
 * Concrete tables (e.g. plugin `{component}\table\*`) call
 * `parent::__construct()` and then `set_sql()` + `define_columns()` as needed.
 *
 * @api
 */
abstract class UsersTable extends sql_table
{
    public function __construct(string $uniqueid, protected readonly context $context)
    {
        parent::__construct($uniqueid);
    }
}
