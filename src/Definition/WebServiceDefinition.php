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
 * Web service group definition for the $services array in db/services.php.
 *
 * Each web service groups a set of external functions into a named service
 * that can be enabled/disabled and optionally restricted to specific users.
 *
 * @api
 */
final readonly class WebServiceDefinition implements DefinitionInterface
{
    /**
     * @param string   $name             human-readable service name
     * @param string   $shortname        machine name used as the $services array key
     * @param string[] $functions        external function names included in this service
     * @param bool     $enabled          whether the service is enabled by default
     * @param int      $restricted_users whether the service is restricted to specific users (0 = no, 1 = yes)
     * @param ?string  $min_moodle       minimum Moodle version (inclusive), or null for no constraint
     * @param ?string  $max_moodle       maximum Moodle version (inclusive), or null for no constraint
     */
    public function __construct(
        public string $name,
        public string $shortname,
        public array $functions,
        public bool $enabled = true,
        public int $restricted_users = 0,
        public ?string $min_moodle = null,
        public ?string $max_moodle = null,
    ) {}

    public function toMoodleArray(string $plugin_name): array
    {
        return [
            'shortname' => $this->shortname,
            'enabled' => (int) $this->enabled,
            'restrictedusers' => $this->restricted_users,
            'functions' => $this->functions,
        ];
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
        return $this->shortname;
    }
}
