<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Security\Attribute;

use Attribute;

/**
 * Requires sesskey validation (Moodle CSRF) on non-idempotent requests.
 *
 * Composes with `Middag\Framework\Http\Attribute\Auth`: the `Auth` attribute
 * declares login + capabilities (host-agnostic); `Sesskey` adds the
 * Moodle-flavored CSRF requirement. Applied on a method or class, read by
 * `MoodleHttpKernel::applyPlatformAuth()`.
 *
 * Examples:
 *
 *   #[Auth(capabilities: ['local/myplugin:manage'])]
 *   #[Sesskey]
 *   public function update(): JsonResponse { ... }
 *
 *   #[Sesskey]
 *   class my_mutation_controller extends api_controller { ... }
 *
 * @api
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
final readonly class Sesskey
{
    public function __construct(
        public bool $require = true,
    ) {}
}
