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
use admin_setting_configstoredfile;
use Middag\Moodle\Settings\Setting as setting;
use Middag\Moodle\Support\LangSupport as lang_support;

/**
 * File stored via Moodle file API.
 *
 * @api
 */
final class Storedfile extends setting
{
    public function __construct(
        string $name,
        mixed $default = null,
        ?string $label = null,
        ?string $description = null,
        public readonly int $itemid = 0,
        public readonly ?array $options = null,
    ) {
        parent::__construct($name, $default, $label, $description);
    }

    public function toMoodleSetting(string $extension, string $plugin): admin_setting
    {
        return new admin_setting_configstoredfile(
            $plugin . '/' . $this->resolveConfigName($extension),
            lang_support::getString($this->resolveLabel($extension, $plugin), $plugin),
            lang_support::getString($this->resolveDescription($extension, $plugin), $plugin),
            $this->name,
            $this->itemid,
            $this->options,
        );
    }
}
