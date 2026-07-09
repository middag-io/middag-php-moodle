<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Domain\Context;

/**
 * Moodle context levels.
 *
 * Wraps Moodle's CONTEXT_* constants.
 *
 * @api
 */
enum ContextLevel: int
{
    case System = 10;

    case User = 30;

    case Coursecat = 40;

    case Course = 50;

    case Module = 70;

    case Block = 80;

    public function toMoodleValue(): int
    {
        return $this->value;
    }

    /**
     * Resolve a context-level name to its enum case (case-insensitive).
     *
     * Accepts the short name (`course`) or the Moodle constant spelling
     * (`CONTEXT_COURSE`); unknown or empty names return null so callers keep
     * their own default (typically SYSTEM). This is what lets `#[Auth]`'s
     * string `context` reach the authorization check instead of being dropped.
     */
    public static function fromString(?string $name): ?self
    {
        if ($name === null) {
            return null;
        }

        $key = strtolower(trim($name));
        $key = str_starts_with($key, 'context_') ? substr($key, 8) : $key;

        return match ($key) {
            'system' => self::System,
            'user' => self::User,
            'coursecat', 'category' => self::Coursecat,
            'course' => self::Course,
            'module', 'coursemodule', 'cm' => self::Module,
            'block' => self::Block,
            default => null,
        };
    }
}
