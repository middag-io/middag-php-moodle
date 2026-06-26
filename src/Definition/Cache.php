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

use Middag\Moodle\Enum\CacheMode as cache_mode;

/**
 * Cache store definition for db/caches.php.
 *
 * @api
 */
final readonly class Cache implements DefinitionInterface
{
    public function __construct(
        public string $name,
        public cache_mode $mode = cache_mode::APPLICATION,
        public bool $simple_keys = true,
        public bool $simple_data = false,
        public ?string $min_moodle = null,
        public ?string $max_moodle = null,
    ) {}

    public function toMoodleArray(string $plugin_name): array
    {
        $entry = [
            'mode' => $this->mode->toMoodleValue(),
            'simplekeys' => $this->simple_keys,
        ];

        if ($this->simple_data) {
            $entry['simpledata'] = true;
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
        return $this->name;
    }
}
