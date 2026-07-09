<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Domain\Message\Enum;

/**
 * Message delivery permission levels.
 *
 * Wraps Moodle's MESSAGE_* constants.
 *
 * @api
 */
enum MessagePermission: string
{
    case Forced = 'forced';

    case Permitted = 'permitted';

    case Disallowed = 'disallowed';

    public function toMoodleValue(): int
    {
        // Moodle's message permission bitmask constants (lib/messagelib.php):
        // MESSAGE_DISALLOWED = 0x4, MESSAGE_PERMITTED = 0x8, MESSAGE_FORCED = 0xc.
        // (The old 1/2/0 mapping collided with MESSAGE_DEFAULT_LOGGEDIN/LOGGEDOFF.)
        return match ($this) {
            self::Forced => 0xC, // MESSAGE_FORCED
            self::Permitted => 0x8, // MESSAGE_PERMITTED
            self::Disallowed => 0x4, // MESSAGE_DISALLOWED
        };
    }
}
