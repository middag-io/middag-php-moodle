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
use admin_setting_configtime;
use Middag\Moodle\Settings\Setting as setting;
use Middag\Moodle\Support\LangSupport as lang_support;

/**
 * Time of day setting (hour:minute).
 *
 * @api
 */
final class Time extends setting
{
    public function __construct(
        string $name,
        mixed $default = null,
        ?string $label = null,
        ?string $description = null,
        public readonly string $minutesName = 'minutes',
    ) {
        parent::__construct($name, $default, $label, $description);
    }

    public function toMoodleSetting(string $extension, string $plugin): admin_setting
    {
        return new admin_setting_configtime(
            $plugin . '/' . $this->resolveConfigName($extension),
            $plugin . '/' . $this->resolveConfigName($extension) . '_' . $this->minutesName,
            lang_support::getString($this->resolveLabel($extension, $plugin), $plugin),
            lang_support::getString($this->resolveDescription($extension, $plugin), $plugin),
            $this->default,
        );
    }
}
