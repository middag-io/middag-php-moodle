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
 * Check type enum for Moodle's Check API.
 *
 * Maps to the check type strings used by \core\check\manager.
 *
 * @api
 */
enum CheckType: string
{
    case Status = 'status';

    case Security = 'security';

    case Performance = 'performance';

    /**
     * Convert to Moodle's string value.
     */
    public function toMoodleValue(): string
    {
        return $this->value;
    }
}
