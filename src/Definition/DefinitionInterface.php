<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Definition;

/**
 * Contract for all Moodle static file definitions.
 *
 * @api
 */
interface DefinitionInterface
{
    /**
     * Render this definition to a Moodle-compatible array.
     *
     * @param string $plugin_name Plugin frankenstyle name (e.g. 'local_example').
     *
     * @return array<string, mixed>
     */
    public function toMoodleArray(string $plugin_name): array;

    /**
     * Check if this definition is compatible with the given Moodle version.
     */
    public function isCompatible(string $moodle_version): bool;

    /**
     * Get the definition key used in the generated file.
     */
    public function getName(): string;
}
