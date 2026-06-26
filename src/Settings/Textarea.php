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
use admin_setting_configtextarea;
use Middag\Moodle\Settings\Setting as setting;
use Middag\Moodle\Support\LangSupport as lang_support;

/**
 * Multi-line text area setting.
 *
 * @api
 */
final class Textarea extends setting
{
    public function __construct(
        string $name,
        mixed $default = null,
        ?string $label = null,
        ?string $description = null,
        public readonly int $rows = 8,
        public readonly int $cols = 60,
    ) {
        parent::__construct($name, $default, $label, $description);
    }

    public function toMoodleSetting(string $extension, string $plugin): admin_setting
    {
        return new admin_setting_configtextarea(
            $plugin . '/' . $this->resolveConfigName($extension),
            lang_support::getString($this->resolveLabel($extension, $plugin), $plugin),
            lang_support::getString($this->resolveDescription($extension, $plugin), $plugin),
            $this->default,
            PARAM_RAW,
            $this->cols . '',
            $this->rows . '',
        );
    }
}
