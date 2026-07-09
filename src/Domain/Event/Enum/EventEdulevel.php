<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Domain\Event\Enum;

/**
 * Moodle event educational level.
 *
 * Wraps \core\event\base::LEVEL_* constants for typed event definitions.
 *
 * @api
 */
enum EventEdulevel: int
{
    case Teaching = 0;

    case Participating = 1;

    case Other = 2;

    public function toMoodleValue(): int
    {
        return $this->value;
    }
}
