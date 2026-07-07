<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Domain\Platform;

use Middag\Framework\Exception\MiddagValidationException;
use Stringable;

/**
 * Parseable Moodle version (e.g. 4.5.0, 4.5, 4.5.0+).
 *
 * Supports comparison, range checks, and canonical string representation.
 *
 * @api
 */
final readonly class MoodleVersion implements Stringable
{
    public function __construct(
        public int $major,
        public int $minor,
        public int $patch = 0,
        public ?string $suffix = null,
    ) {}

    public function __toString(): string
    {
        $version = sprintf('%d.%d.%d', $this->major, $this->minor, $this->patch);

        if ($this->suffix !== null) {
            $version .= $this->suffix;
        }

        return $version;
    }

    /**
     * Parse from version string (e.g. '4.5.0', '4.5', '4.5.0+').
     *
     * @throws MiddagValidationException if format is invalid
     */
    public static function from_string(string $version): self
    {
        $version = trim($version);
        $suffix = null;

        // Extract suffix (e.g. '+', '-dev', '-beta1').
        if (preg_match('/^([\d.]+)([^\d.].*)$/', $version, $m)) {
            $version = $m[1];
            $suffix = $m[2];
        }

        $parts = explode('.', $version);

        if (count($parts) < 2 || count($parts) > 3) {
            throw new MiddagValidationException(
                sprintf("Invalid Moodle version format: '%s'. Expected 'major.minor' or 'major.minor.patch'.", $version),
            );
        }

        $major = (int) $parts[0];
        $minor = (int) $parts[1];
        $patch = isset($parts[2]) ? (int) $parts[2] : 0;

        return new self($major, $minor, $patch, $suffix);
    }

    /**
     * Compare two versions. Returns negative, zero, or positive.
     */
    public function compare(self $other): int
    {
        return ($this->major <=> $other->major)
            ?: ($this->minor <=> $other->minor)
            ?: ($this->patch <=> $other->patch);
    }

    public function is_at_least(self $other): bool
    {
        return $this->compare($other) >= 0;
    }

    public function is_between(self $min, self $max): bool
    {
        return $this->compare($min) >= 0 && $this->compare($max) <= 0;
    }

    public function equals(self $other): bool
    {
        return $this->compare($other) === 0;
    }
}
