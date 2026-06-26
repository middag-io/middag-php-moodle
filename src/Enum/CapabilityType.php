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
 * Capability type (read or write).
 *
 * @api
 */
enum CapabilityType: string
{
    case READ = 'read';

    case WRITE = 'write';

    public function toMoodleValue(): string
    {
        return $this->value;
    }
}
