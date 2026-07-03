<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Security\Enum;

/**
 * Capability risk bitmask values.
 *
 * Wraps Moodle's RISK_* constants. Composable via bitwise OR.
 *
 * @api
 */
enum CapabilityRisk: int
{
    case SPAM = 1;       // RISK_SPAM

    case PERSONAL = 2;   // RISK_PERSONAL

    case XSS = 4;        // RISK_XSS

    case CONFIG = 8;     // RISK_CONFIG

    case DATALOSS = 16;  // RISK_DATALOSS

    public function toMoodleValue(): int
    {
        return $this->value;
    }
}
