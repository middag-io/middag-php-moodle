<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Exception;

use InvalidArgumentException;

/**
 * Raised when Moodle version parsing or constraints are invalid.
 *
 * @api
 */
final class MoodleVersionException extends InvalidArgumentException {}
