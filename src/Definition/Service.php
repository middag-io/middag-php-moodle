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

use Middag\Moodle\Definition\Contract\DefinitionInterface;

/**
 * External function definition for db/services.php.
 *
 * @api
 */
final readonly class Service implements DefinitionInterface
{
    /**
     * @param string[] $services     web service shortnames this function is published in
     * @param ?string  $capabilities comma-separated Moodle capability names required to call this
     *                               function (advisory metadata in db/services.php; actual enforcement
     *                               is the external function's own validate_context()/require_capability())
     */
    public function __construct(
        public string $name,
        public string $classname,
        public string $type = 'read',
        public ?string $method = null,
        public ?string $description = null,
        public bool $ajax = true,
        public array $services = [],
        public ?string $min_moodle = null,
        public ?string $max_moodle = null,
        public ?string $capabilities = null,
    ) {}

    public function toMoodleArray(string $plugin_name): array
    {
        $entry = [
            'classname' => $this->classname,
            'methodname' => $this->method ?? $this->name,
            'description' => $this->description ?? '',
            'type' => $this->type,
            'ajax' => $this->ajax,
        ];

        if ($this->capabilities !== null && trim($this->capabilities) !== '') {
            $entry['capabilities'] = trim($this->capabilities);
        }

        if ($this->services !== []) {
            $entry['services'] = $this->services;
        }

        return $entry;
    }

    /**
     * Get the fully qualified function name (with plugin prefix).
     */
    public function get_qualified_name(string $plugin_name): string
    {
        return $plugin_name . '_' . $this->name;
    }

    public function isCompatible(string $moodle_version): bool
    {
        if ($this->min_moodle !== null && version_compare($moodle_version, $this->min_moodle, '<')) {
            return false;
        }

        if ($this->max_moodle !== null && version_compare($moodle_version, $this->max_moodle, '>')) {
            return false;
        }

        return true;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
