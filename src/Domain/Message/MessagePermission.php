<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Domain\Message;

/**
 * Message delivery permission levels.
 *
 * Wraps Moodle's MESSAGE_* constants.
 *
 * @api
 */
enum MessagePermission: string
{
    case FORCED = 'forced';

    case PERMITTED = 'permitted';

    case DISALLOWED = 'disallowed';

    public function toMoodleValue(): int
    {
        return match ($this) {
            self::FORCED => 1, // MESSAGE_FORCED
            self::PERMITTED => 2, // MESSAGE_PERMITTED
            self::DISALLOWED => 0, // MESSAGE_DISALLOWED
        };
    }
}
