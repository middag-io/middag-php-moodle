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
 * Typed enum wrapping Moodle's core message output processor types.
 *
 * Plugins may register additional output types not represented here;
 * use resolve() which returns null for unknown types.
 *
 * @api
 */
enum MessageOutputType: string
{
    case Popup = 'popup';

    case Email = 'email';

    case Airnotifier = 'airnotifier';

    case Mobile = 'mobile';

    public function isRealtime(): bool
    {
        return in_array($this, [self::Popup, self::Mobile, self::Airnotifier], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Popup => 'Web notification',
            self::Email => 'Email',
            self::Airnotifier => 'Push notification',
            self::Mobile => 'Mobile app',
        };
    }

    /**
     * Resolve from Moodle's processor name. Returns the enum case if known,
     * or null for plugin-registered output types not in this enum.
     */
    public static function resolve(string $value): ?self
    {
        return self::tryFrom($value);
    }
}
