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

use Middag\Framework\Exception\MiddagValidationException;
use Stringable;

/**
 * Typed Moodle sesskey value.
 *
 * Encapsulates the session key used for CSRF protection. Provides
 * constant-time comparison via hash_equals to prevent timing attacks.
 *
 * @api
 */
final readonly class Sesskey implements Stringable
{
    public function __construct(
        public string $value,
    ) {}

    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * Create from the current session's sesskey.
     */
    public static function fromCurrent(): self
    {
        return new self(sesskey());
    }

    /**
     * Create from a raw string value with validation.
     *
     * @throws MiddagValidationException if value is empty or too long
     */
    public static function fromString(string $value): self
    {
        $value = trim($value);

        if ($value === '' || strlen($value) > 40) {
            throw new MiddagValidationException(
                sprintf('Invalid sesskey: must be 1-40 characters, got %d.', strlen($value)),
            );
        }

        if (!preg_match('/^[a-zA-Z0-9]+$/', $value)) {
            throw new MiddagValidationException('Invalid sesskey: must be alphanumeric.');
        }

        return new self($value);
    }

    /**
     * Constant-time comparison to prevent timing attacks.
     */
    public function matches(string $value): bool
    {
        return hash_equals($this->value, $value);
    }
}
