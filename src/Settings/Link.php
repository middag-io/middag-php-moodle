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
use admin_setting_description;
use core\output\html_writer;
use Middag\Moodle\Settings\Setting as setting;
use Middag\Moodle\Support\LangSupport as lang_support;

/**
 * Quick access link setting.
 *
 * @api
 */
final class Link extends setting
{
    public function __construct(
        string $name,
        public readonly string $url,
        public readonly string $linkText = '',
        ?string $label = null,
        ?string $description = null,
    ) {
        parent::__construct($name, null, $label, $description);
    }

    public function toMoodleSetting(string $extension, string $plugin): admin_setting
    {
        $label = lang_support::getString($this->resolveLabel($extension, $plugin), $plugin);
        $description = lang_support::getString($this->resolveDescription($extension, $plugin), $plugin);
        $link_text = $this->linkText !== '' ? $this->linkText : $label;

        // Use admin_setting_description to render a clickable link.
        $html = html_writer::link($this->url, $link_text, ['target' => '_blank']);

        return new admin_setting_description(
            $plugin . '/' . $this->name,
            $label,
            $description . ' ' . $html,
        );
    }
}
