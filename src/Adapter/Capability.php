<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Adapter;

use core\context;
use Middag\Framework\Exception\MiddagAuthorizationException as middag_authorization_exception;
use Middag\Moodle\Contract\CapabilityInterface as capability_interface;
use Middag\Moodle\Enum\ContextLevel as context_level;
use Middag\Moodle\Support\CapabilitySupport as capability_support;
use Middag\Moodle\Support\ContextSupport as context_support;

/**
 * Moodle capability adapter.
 *
 * Delegates permission checks to Moodle via the boundary support layer.
 * Converts the typed `context_level` enum to Moodle's native context objects.
 *
 * @internal
 */
class Capability implements capability_interface
{
    public function can(string $capability, context_level $contextlevel = context_level::SYSTEM, int $instanceid = 0, ?int $userid = null): bool
    {
        $context = $this->resolveContext($contextlevel, $instanceid);

        return capability_support::has($capability, $context, $userid) ?? false;
    }

    /**
     * @throws middag_authorization_exception
     */
    public function authorize(string $capability, context_level $contextlevel = context_level::SYSTEM, int $instanceid = 0, ?int $userid = null): void
    {
        if (!$this->can($capability, $contextlevel, $instanceid, $userid)) {
            throw new middag_authorization_exception(
                sprintf('Missing capability: %s', $capability),
            );
        }
    }

    /**
     * Resolve a context_level enum + instance ID into a Moodle context object.
     */
    private function resolveContext(context_level $contextlevel, int $instanceid): context
    {
        return match ($contextlevel) {
            context_level::SYSTEM => context_support::system(),
            context_level::COURSECAT => context_support::coursecat($instanceid),
            context_level::COURSE => context_support::course($instanceid),
            context_level::MODULE => context_support::module($instanceid),
            context_level::BLOCK => context_support::block($instanceid),
            context_level::USER => context_support::user($instanceid),
        };
    }
}
