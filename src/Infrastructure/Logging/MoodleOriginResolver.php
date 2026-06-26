<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Infrastructure\Logging;

use Middag\Framework\Logging\Contract\OriginResolverInterface;

/**
 * Moodle-flavored origin resolver. Returns `cli` under CLI, otherwise the
 * remote IP via Moodle's `getremoteaddr()` helper, or `system` when no
 * remote address is available.
 *
 * @internal
 */
final readonly class MoodleOriginResolver implements OriginResolverInterface
{
    public function resolve(): string
    {
        if (defined('CLI_SCRIPT') && CLI_SCRIPT) {
            return 'cli';
        }

        $ip = function_exists('getremoteaddr') ? getremoteaddr() : '';

        return $ip !== '' ? 'ip:' . $ip : 'system';
    }
}
