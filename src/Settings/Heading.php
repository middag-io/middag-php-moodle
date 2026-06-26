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
use admin_setting_heading;
use Middag\Moodle\Settings\Setting as setting;
use Middag\Moodle\Support\LangSupport as lang_support;

/**
 * Section heading setting (no stored value).
 *
 * @api
 */
final class Heading extends setting
{
    public function __construct(
        string $name,
        ?string $label = null,
        ?string $description = null,
    ) {
        parent::__construct($name, null, $label, $description);
    }

    public function toMoodleSetting(string $extension, string $plugin): admin_setting
    {
        return new admin_setting_heading(
            $plugin . '/' . $this->name,
            lang_support::getString($this->resolveLabel($extension, $plugin), $plugin),
            lang_support::getString($this->resolveDescription($extension, $plugin), $plugin),
        );
    }
}
