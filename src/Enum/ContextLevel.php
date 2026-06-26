<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Enum;

/**
 * Moodle context levels.
 *
 * Wraps Moodle's CONTEXT_* constants.
 *
 * @api
 */
enum ContextLevel: int
{
    case SYSTEM = 10;

    case USER = 30;

    case COURSECAT = 40;

    case COURSE = 50;

    case MODULE = 70;

    case BLOCK = 80;

    public function toMoodleValue(): int
    {
        return $this->value;
    }
}
