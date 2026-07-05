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

/**
 * Typed file area definition.
 *
 * Extensions declare file areas via `get_file_area_definitions()`.
 * The pluginfile delegation reads from these definitions to route
 * file serving to the correct handler.
 *
 * @api
 */
final readonly class FileAreaDefinition implements DefinitionInterface
{
    /**
     * @param string       $name             area name without extension prefix (e.g. 'attachments')
     * @param ContextLevel $context_level    context level for this area
     * @param null|string  $handler          FQCN of a file_area_handler_interface implementation (null = default handler)
     * @param bool         $supports_preview whether this area supports file preview/thumbnails
     * @param string       $description      human-readable description
     * @param null|string  $min_moodle       minimum Moodle version (null = no minimum)
     * @param null|string  $max_moodle       maximum Moodle version (null = no maximum)
     */
    public function __construct(
        public string $name,
        public ContextLevel $context_level = ContextLevel::SYSTEM,
        public ?string $handler = null,
        public bool $supports_preview = false,
        public string $description = '',
        public ?string $min_moodle = null,
        public ?string $max_moodle = null,
    ) {}

    public function toMoodleArray(string $plugin_name): array
    {
        return [
            'contextlevel' => $this->context_level->toMoodleValue(),
            'supports_preview' => $this->supports_preview,
        ];
    }

    /**
     * Get the fully qualified area name.
     *
     * @param null|string $extension extension slug
     */
    public function get_qualified_name(?string $extension = null): string
    {
        if ($extension === null || $extension === 'core') {
            return $this->name;
        }

        return $extension . '_' . $this->name;
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
