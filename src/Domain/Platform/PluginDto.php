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

use Middag\Framework\Shared\Dto\AbstractDto as abstract_dto;
use stdClass;
use Stringable;

/**
 * Data Transfer Object representing a Moodle plugin.
 *
 * Abstracts the core plugininfo structure into a serializable, immutable
 * representation for domain/services without leaking Moodle internals.
 *
 * @internal
 */
final class PluginDto extends abstract_dto implements Stringable
{
    /**
     * @param string                        $type            Plugin type (e.g., "mod", "auth", "local")
     * @param string                        $name            Plugin name (e.g., "forum", "oauth2")
     * @param string                        $component       Full frankenstyle name (e.g., "mod_forum")
     * @param null|string                   $rootdir         Full filesystem path to the plugin directory
     * @param null|string                   $displayname     Localized display name
     * @param null|string                   $source          Plugin source (STANDARD or EXTENSION)
     * @param null|int|string               $versiondisk     Version declared in version.php
     * @param null|int|string               $versiondb       Version installed in database
     * @param null|float|int|string         $versionrequires Moodle version required
     * @param null|array<string,int|string> $dependencies    List of required plugins
     * @param null|bool                     $enabled         Whether plugin is enabled
     * @param null|string                   $release         Human-readable release information
     * @param null|array<int>               $supported       Supported Moodle branches
     * @param null|int                      $incompatible    First incompatible Moodle branch
     * @param null|string                   $status          Plugin status (uptodate, new, missing, etc.)
     */
    public function __construct(
        public string $type,
        public string $name,
        public string $component,
        public ?string $rootdir,
        public ?string $displayname,
        public ?string $source,
        public int|string|null $versiondisk,
        public int|string|null $versiondb,
        public float|int|string|null $versionrequires,
        public ?array $dependencies,
        public ?bool $enabled,
        public ?string $release,
        public ?array $supported,
        public ?int $incompatible,
        public ?string $status,
    ) {}

    /**
     * String representation (component name).
     */
    public function __toString(): string
    {
        return $this->component;
    }

    /**
     * Convert to associative array with key metadata.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'name' => $this->name,
            'component' => $this->component,
            'displayname' => $this->displayname,
            'enabled' => $this->enabled,
            'status' => $this->status,
        ];
    }

    /**
     * Convert to stdClass for compatibility layers.
     */
    public function toObject(): stdClass
    {
        $obj = new stdClass();
        foreach ($this->toArray() as $key => $value) {
            $obj->{$key} = $value;
        }

        return $obj;
    }

    /**
     * Returns true if the plugin exists on disk.
     */
    public function existsOnDisk(): bool
    {
        return $this->rootdir !== null;
    }

    /**
     * Returns true if the plugin is installed (has DB version or folder).
     */
    public function isInstalled(): bool
    {
        return $this->versiondb !== null || $this->rootdir !== null;
    }

    /**
     * Returns true if the plugin requires a core version.
     */
    public function hasCoreRequirement(): bool
    {
        return $this->versionrequires !== null;
    }
}
