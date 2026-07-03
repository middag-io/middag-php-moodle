<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Output;

use core\output\plugin_renderer_base;

/**
 * Base renderer for MIDDAG Moodle extensions.
 *
 * Plugin extension renderers (e.g. `{component}\output\extensions`) extend
 * this class rather than `plugin_renderer_base` directly. Provides the
 * canonical Mustache rendering entrypoint (ADR-803) and any shared rendering
 * helpers needed across extensions.
 *
 * @api
 */
abstract class MoodleRenderer extends plugin_renderer_base {}
