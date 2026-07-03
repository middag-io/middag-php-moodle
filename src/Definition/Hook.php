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
 * Hook callback definition for db/hooks.php.
 *
 * Hooks were introduced in Moodle 4.3, so min_moodle defaults to '4.3'.
 *
 * @api
 */
final readonly class Hook implements DefinitionInterface
{
    public function __construct(
        public string $hook_class,
        public array|string $callback,
        public int $priority = 0,
        public ?string $min_moodle = '4.3',
        public ?string $max_moodle = null,
    ) {}

    public function toMoodleArray(string $plugin_name): array
    {
        $entry = [
            'hook' => $this->hook_class,
            'callback' => $this->callback,
        ];

        if ($this->priority !== 0) {
            $entry['priority'] = $this->priority;
        }

        return $entry;
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
        return $this->hook_class;
    }
}
