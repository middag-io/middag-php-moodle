<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Security\ValueObject;

use LogicException;
use Middag\Framework\Exception\MiddagDomainException;
use Middag\Moodle\Config\ComponentContext;
use Stringable;

/**
 * Typed representation of a Moodle capability string.
 *
 * Encapsulates the 5-tuple format: {type}/{plugin}:{name}
 * Examples: 'local/middag:manage', 'moodle/course:view', 'mod/forum:addinstance'
 *
 * Provides validation at construction time, eliminating typos and making
 * capability references refactorable. Replaces 135+ raw strings scattered
 * across the codebase with a typed, self-validating object.
 *
 * @api
 */
final readonly class Capability implements Stringable
{
    /**
     * @param string $identifier The full capability identifier (e.g. 'local/middag:manage')
     *
     * @throws MiddagDomainException if the identifier format is invalid
     */
    public function __construct(
        public string $identifier,
    ) {
        if (!self::isValidFormat($identifier)) {
            throw new MiddagDomainException(
                sprintf("Invalid capability format: '%s'. Expected '{type}/{plugin}:{name}'.", $identifier)
            );
        }
    }

    /**
     * String representation for use with Moodle capability APIs.
     */
    public function __toString(): string
    {
        return $this->identifier;
    }

    /**
     * The component part (e.g. 'local/middag' from 'local/middag:manage').
     */
    public function component(): string
    {
        return explode(':', $this->identifier, 2)[0];
    }

    /**
     * The name part (e.g. 'manage' from 'local/middag:manage').
     */
    public function name(): string
    {
        return explode(':', $this->identifier, 2)[1];
    }

    /**
     * Whether this capability belongs to the running host plugin — the
     * component configured via {@see ComponentContext} (e.g. 'local/middag:*'
     * when the host is 'local_middag').
     *
     * @throws LogicException when the adapter component is not configured
     */
    public function isHostComponent(): bool
    {
        return str_starts_with($this->identifier, ComponentContext::capabilityComponent() . ':');
    }

    /**
     * Validate the capability format: must contain exactly one ':' with
     * a component part containing '/' before it.
     */
    public static function isValidFormat(string $identifier): bool
    {
        if (!str_contains($identifier, ':')) {
            return false;
        }

        [$component, $name] = explode(':', $identifier, 2);

        return str_contains($component, '/') && $name !== '';
    }

    /**
     * Factory — create a capability scoped to the running host plugin, deriving
     * the component from {@see ComponentContext} (e.g. 'local/middag:manage'
     * when the host is 'local_middag').
     *
     * @param string $name e.g. 'manage', 'view', 'configure'
     *
     * @throws LogicException when the adapter component is not configured
     */
    public static function forHostComponent(string $name): self
    {
        return new self(ComponentContext::capabilityComponent() . ':' . $name);
    }
}
