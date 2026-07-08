<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Settings;

use admin_setting;
use Middag\Moodle\Support\LangSupport;

/**
 * Base class for all admin settings.
 *
 * @api
 */
abstract class AbstractSetting
{
    private ?SettingsNamingPolicy $namingPolicy = null;

    public function __construct(
        public readonly string $name,
        public readonly mixed $default = null,
        public readonly ?string $label = null,
        public readonly ?string $description = null,
    ) {}

    /**
     * Adopt the naming policy of the resolver materialising this setting.
     *
     * Injected by {@see SettingsResolver::resolveExtensionPages()} right
     * before {@see self::toMoodleSetting()}; when never injected, the MIDDAG
     * default policy applies.
     */
    public function useNamingPolicy(SettingsNamingPolicy $policy): void
    {
        $this->namingPolicy = $policy;
    }

    /**
     * Resolve the lang string key for the label.
     */
    public function resolveLabel(string $extension, string $plugin): string
    {
        if ($this->label !== null) {
            return $this->label;
        }

        if ($extension === 'core') {
            return 'setting_' . $this->name;
        }

        return 'setting_' . $extension . '_' . $this->name;
    }

    /**
     * Resolve the lang string key for the description.
     *
     * When no description is explicitly set, tries {label}_desc. Returns ''
     * if the auto-resolved key does not exist in the lang file.
     */
    public function resolveDescription(string $extension, string $plugin): string
    {
        if ($this->description !== null) {
            return $this->description;
        }

        $candidate = $this->resolveLabel($extension, $plugin) . '_desc';

        if (LangSupport::stringExists($candidate, $plugin)) {
            return $candidate;
        }

        return '';
    }

    /**
     * Resolve the canonical config key for this setting under the adopted
     * naming policy (MIDDAG default when none was injected).
     *
     * @internal
     */
    public function resolveConfigName(string $extension): string
    {
        return ($this->namingPolicy ?? new SettingsNamingPolicy())->configKey($this->name, $extension);
    }

    /**
     * Convert to Moodle admin_setting instance.
     *
     * @internal
     */
    abstract public function toMoodleSetting(string $extension, string $plugin): admin_setting;
}
