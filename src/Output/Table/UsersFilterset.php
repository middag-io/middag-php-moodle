<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Output\Table;

use core_table\local\filter\filterset;

/**
 * Abstract filterset for MIDDAG user list tables.
 *
 * Concrete filtersets (e.g. plugin `{component}\table\users_filterset`)
 * extend this class and register their filter types. Compatible with Moodle's
 * dynamic table infrastructure.
 *
 * @api
 */
abstract class UsersFilterset extends filterset {}
