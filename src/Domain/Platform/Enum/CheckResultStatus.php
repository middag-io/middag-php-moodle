<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Domain\Platform\Enum;

/**
 * Typed enum wrapping Moodle's `\core\check\result` status constants.
 *
 * @api
 */
enum CheckResultStatus: string
{
    case Na = 'na';

    case Ok = 'ok';

    case Info = 'info';

    case Unknown = 'unknown';

    case Warning = 'warning';

    case Error = 'error';

    case Critical = 'critical';

    public function isHealthy(): bool
    {
        return in_array($this, [self::Na, self::Ok, self::Info], true);
    }

    public function isCriticalOrWorse(): bool
    {
        return in_array($this, [self::Error, self::Critical], true);
    }

    /**
     * Numeric severity (0 = least severe, 6 = most severe).
     */
    public function severity(): int
    {
        return match ($this) {
            self::Na => 0,
            self::Ok => 1,
            self::Info => 2,
            self::Unknown => 3,
            self::Warning => 4,
            self::Error => 5,
            self::Critical => 6,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Na => 'N/A',
            self::Ok => 'OK',
            self::Info => 'Info',
            self::Unknown => 'Unknown',
            self::Warning => 'Warning',
            self::Error => 'Error',
            self::Critical => 'Critical',
        };
    }

    public static function resolve(string $value): self
    {
        return self::tryFrom($value) ?? self::Unknown;
    }
}
