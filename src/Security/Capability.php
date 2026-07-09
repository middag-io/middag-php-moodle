<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Security;

use core\context;
use Middag\Framework\Exception\MiddagAuthorizationException;
use Middag\Moodle\Domain\Context\ContextLevel;
use Middag\Moodle\Security\Contract\CapabilityInterface;
use Middag\Moodle\Support\CapabilitySupport;
use Middag\Moodle\Support\ContextSupport;

/**
 * Moodle capability adapter.
 *
 * Delegates permission checks to Moodle via the boundary support layer.
 * Converts the typed `ContextLevel` enum to Moodle's native context objects.
 *
 * @internal
 */
class Capability implements CapabilityInterface
{
    public function can(string $capability, ContextLevel $contextlevel = ContextLevel::System, int $instanceid = 0, ?int $userid = null): bool
    {
        $context = $this->resolveContext($contextlevel, $instanceid);

        return CapabilitySupport::has($capability, $context, $userid) ?? false;
    }

    /**
     * @throws MiddagAuthorizationException
     */
    public function authorize(string $capability, ContextLevel $contextlevel = ContextLevel::System, int $instanceid = 0, ?int $userid = null): void
    {
        if (!$this->can($capability, $contextlevel, $instanceid, $userid)) {
            throw new MiddagAuthorizationException(
                sprintf('Missing capability: %s', $capability),
            );
        }
    }

    /**
     * Resolve a ContextLevel enum + instance ID into a Moodle context object.
     */
    private function resolveContext(ContextLevel $contextlevel, int $instanceid): context
    {
        return match ($contextlevel) {
            ContextLevel::System => ContextSupport::system(),
            ContextLevel::Coursecat => ContextSupport::coursecat($instanceid),
            ContextLevel::Course => ContextSupport::course($instanceid),
            ContextLevel::Module => ContextSupport::module($instanceid),
            ContextLevel::Block => ContextSupport::block($instanceid),
            ContextLevel::User => ContextSupport::user($instanceid),
        };
    }
}
