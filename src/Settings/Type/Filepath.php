<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Settings\Type;

use admin_setting;
use admin_setting_configfile;
use Middag\Moodle\Settings\AbstractSetting;
use Middag\Moodle\Support\LangSupport;

/**
 * File path on server filesystem.
 *
 * @api
 */
final class Filepath extends AbstractSetting
{
    public function toMoodleSetting(string $extension, string $plugin): admin_setting
    {
        return new admin_setting_configfile(
            $plugin . '/' . $this->resolveConfigName($extension),
            LangSupport::getString($this->resolveLabel($extension, $plugin), $plugin),
            LangSupport::getString($this->resolveDescription($extension, $plugin), $plugin),
            $this->default,
        );
    }
}
