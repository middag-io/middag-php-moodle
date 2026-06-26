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
 * Typed enum wrapping Moodle's `\core\check\result` status constants.
 *
 * @api
 */
enum CheckResultStatus: string
{
    case NA = 'na';

    case OK = 'ok';

    case INFO = 'info';

    case UNKNOWN = 'unknown';

    case WARNING = 'warning';

    case ERROR = 'error';

    case CRITICAL = 'critical';

    public function isHealthy(): bool
    {
        return in_array($this, [self::NA, self::OK, self::INFO], true);
    }

    public function isCriticalOrWorse(): bool
    {
        return in_array($this, [self::ERROR, self::CRITICAL], true);
    }

    /**
     * Numeric severity (0 = least severe, 6 = most severe).
     */
    public function severity(): int
    {
        return match ($this) {
            self::NA => 0,
            self::OK => 1,
            self::INFO => 2,
            self::UNKNOWN => 3,
            self::WARNING => 4,
            self::ERROR => 5,
            self::CRITICAL => 6,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::NA => 'N/A',
            self::OK => 'OK',
            self::INFO => 'Info',
            self::UNKNOWN => 'Unknown',
            self::WARNING => 'Warning',
            self::ERROR => 'Error',
            self::CRITICAL => 'Critical',
        };
    }

    public static function resolve(string $value): self
    {
        return self::tryFrom($value) ?? self::UNKNOWN;
    }
}
