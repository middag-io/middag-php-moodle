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
use Middag\Moodle\Domain\Context\ContextLevel;
use Middag\Moodle\Security\Enum\CapabilityRisk;
use Middag\Moodle\Security\Enum\CapabilityType;

/**
 * Capability definition for db/access.php.
 *
 * @api
 */
final readonly class CapabilityDefinition implements DefinitionInterface
{
    public function __construct(
        public string $name,
        public array $archetypes = [],
        public CapabilityType $type = CapabilityType::Read,
        public ContextLevel $context = ContextLevel::System,
        public CapabilityRisk $risk = CapabilityRisk::Spam,
        public ?string $clone_from = null,
        public ?string $min_moodle = null,
        public ?string $max_moodle = null,
    ) {}

    public function toMoodleArray(string $plugin_name): array
    {
        $entry = [
            'riskbitmask' => $this->risk->toMoodleValue(),
            'captype' => $this->type->toMoodleValue(),
            'contextlevel' => $this->context->toMoodleValue(),
            'archetypes' => [],
        ];

        foreach ($this->archetypes as $role) {
            $entry['archetypes'][$role] = CAP_ALLOW;
        }

        if ($this->clone_from !== null) {
            $entry['clonepermissionsfrom'] = $this->clone_from;
        }

        return $entry;
    }

    /**
     * Get the fully qualified capability name (with plugin prefix).
     *
     * @param string $plugin_name Frankenstyle plugin name (e.g. 'local_example').
     * @param string $extension   Extension slug. 'core' or null = no prefix.
     */
    public function get_qualified_name(string $plugin_name, ?string $extension = null): string
    {
        $prefix = str_replace('_', '/', $plugin_name);

        if ($extension === null || $extension === 'core') {
            return $prefix . ':' . $this->name;
        }

        return $prefix . ':' . $extension . '_' . $this->name;
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
