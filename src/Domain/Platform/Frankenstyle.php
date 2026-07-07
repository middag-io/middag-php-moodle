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
 * Validated Moodle component name in Frankenstyle format (e.g. 'mod_assign', 'local_example').
 *
 * @api
 */
final readonly class Frankenstyle implements Stringable
{
    public function __construct(
        /** Plugin type (e.g. 'mod', 'local', 'block', 'auth', 'tool'). */
        public string $type,
        /** Plugin name (lowercase alphanumeric). */
        public string $name,
    ) {}

    public function __toString(): string
    {
        return $this->type . '_' . $this->name;
    }

    /**
     * Parse from component string (e.g. 'mod_assign').
     *
     * @throws MiddagValidationException if format is invalid
     */
    public static function from_string(string $component): self
    {
        $component = trim($component);

        if ($component === '' || !str_contains($component, '_')) {
            throw new MiddagValidationException(
                sprintf("Invalid Frankenstyle component: '%s'. Expected 'type_name' format.", $component),
            );
        }

        $pos = strpos($component, '_');
        $type = substr($component, 0, $pos);
        $name = substr($component, $pos + 1);

        if (!preg_match('/^[a-z][a-z0-9]*$/', $name)) {
            throw new MiddagValidationException(
                sprintf("Invalid Frankenstyle name: '%s'. Must match /^[a-z][a-z0-9]*$/.", $name),
            );
        }

        if (!preg_match('/^[a-z]+$/', $type)) {
            throw new MiddagValidationException(
                sprintf("Invalid Frankenstyle type: '%s'. Must be lowercase letters only.", $type),
            );
        }

        return new self($type, $name);
    }

    public static function local(string $name): self
    {
        return self::from_string('local_' . $name);
    }

    public static function mod(string $name): self
    {
        return self::from_string('mod_' . $name);
    }

    public static function block(string $name): self
    {
        return self::from_string('block_' . $name);
    }

    public static function auth(string $name): self
    {
        return self::from_string('auth_' . $name);
    }

    public static function tool(string $name): self
    {
        return self::from_string('tool_' . $name);
    }

    public function equals(self $other): bool
    {
        return $this->type === $other->type && $this->name === $other->name;
    }
}
