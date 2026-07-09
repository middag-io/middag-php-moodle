<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Domain\Platform;

/**
 * Cache store mode.
 *
 * Wraps Moodle's cache_store::MODE_* constants.
 *
 * @api
 */
enum CacheMode: int
{
    case Application = 1;

    case Session = 2;

    case Request = 4;

    public function toMoodleValue(): int
    {
        return $this->value;
    }
}
