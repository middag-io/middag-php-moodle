<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Security\Contract;

/**
 * Composed authorizer contract — authentication + capability.
 *
 * Prefer the most specific interface when possible:
 * - `AuthenticationInterface` when only session/login is needed
 * - `CapabilityInterface` when only permission checking is needed
 * - `authorizer_interface` when both concerns are needed in the same consumer
 *
 * @api
 */
interface AuthorizerInterface extends AuthenticationInterface, CapabilityInterface {}
