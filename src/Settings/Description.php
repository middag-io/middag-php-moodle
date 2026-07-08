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
use Middag\Moodle\Support\LangSupport;

/**
 * Static description block (no stored value).
 *
 * @api
 */
final class Description extends AbstractSetting
{
    public function __construct(
        string $name,
        ?string $label = null,
        ?string $description = null,
        public readonly string $content = '',
    ) {
        parent::__construct($name, null, $label, $description);
    }

    public function toMoodleSetting(string $extension, string $plugin): admin_setting
    {
        return new admin_setting_description(
            $plugin . '/' . $this->name,
            LangSupport::getString($this->resolveLabel($extension, $plugin), $plugin),
            $this->content,
        );
    }
}
