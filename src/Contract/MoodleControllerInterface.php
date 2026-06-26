<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Contract;

use Middag\Framework\Http\Contract\ControllerInterface;
use Middag\Moodle\Enum\ContextLevel;

/**
 * Moodle-specific controller contract.
 *
 * Extends the generic ControllerInterface with Moodle CSRF (sesskey) hook
 * and documents the expected capability context type as ContextLevel.
 *
 * The base contract types `setRequireCapabilities()` with `string $context`
 * to remain platform-agnostic. This Moodle bridge widens it to `mixed` so the
 * concrete controller can accept a {@see ContextLevel} (LSP-safe widening on a
 * parameter); non-ContextLevel values fall back to SYSTEM.
 *
 * @api
 */
interface MoodleControllerInterface extends ControllerInterface
{
    /**
     * Define the requirement of sesskey validation for non-idempotent
     * (POST/PUT/PATCH/DELETE) requests. Idempotent verbs are exempt.
     *
     * Implementations should be no-op when `$require` is false.
     */
    public function setRequireSesskey(bool $require = true): void;

    /**
     * Widens the base `string $context` to also accept a Moodle {@see ContextLevel}
     * (and the `null` default); non-ContextLevel values fall back to SYSTEM.
     *
     * @param array<int, string>       $capabilities required capability names
     * @param null|ContextLevel|string $context      Moodle context level; null/non-ContextLevel falls back to SYSTEM
     */
    public function setRequireCapabilities(array $capabilities, mixed $context = null, int $instanceid = 0): void;
}
