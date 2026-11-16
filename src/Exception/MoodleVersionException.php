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

use Middag\Framework\Exception\MiddagInfrastructureException;

/**
 * Raised when Moodle version parsing or constraints are invalid.
 *
 * A host-boundary failure in the framework exception family: the kernel
 * maps it to HTTP 500, like any other infrastructure failure.
 *
 * @api
 */
final class MoodleVersionException extends MiddagInfrastructureException {}
