<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\WebService;

use core_external\external_api;

/**
 * Base class for MIDDAG web service implementations (PD-024 C).
 *
 * Plugin nominal external classes (registered in `db/services.php`) extend
 * this abstract. Migration of `{component}\external` concrete methods is
 * deferred to a later step; this class marks the structural extension point.
 *
 * Usage:
 *   class my_plugin_external extends AbstractExternal { … }
 */
abstract class AbstractExternal extends external_api {}
