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

use Middag\Framework\Logging\Contract\ActorResolverInterface;

/**
 * Moodle-flavored actor resolver. Reads `$USER` (or `CLI_SCRIPT` sentinel)
 * to label log lines emitted through the framework LoggerFactory.
 *
 * @internal
 */
final readonly class MoodleActorResolver implements ActorResolverInterface
{
    public function resolve(): string
    {
        if (defined('CLI_SCRIPT') && CLI_SCRIPT) {
            return 'cli';
        }

        global $USER;
        if (isset($USER->id) && (int) $USER->id > 0) {
            return 'user:' . $USER->id;
        }

        return 'system';
    }
}
