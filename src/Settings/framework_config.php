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

use Middag\Moodle\Support\SettingsSupport;

/**
 * Canonical config keys for the adapter's host-level auth/API settings.
 *
 * Follows the `{slug}_config` convention consumed by
 * {@see SettingsSupport}: the short class name
 * `framework_config` resolves to the `core` settings slug, so each case
 * reads/writes the canonical Moodle config key `mdg_core_{value}`.
 *
 * These are basic host settings keys owned by the adapter; they are not
 * governed product vocabulary.
 *
 * @internal
 */
enum framework_config: string
{
    case authtype = 'authtype';

    case authsecretkey = 'authsecretkey';

    case authvarname = 'authvarname';

    case authprofilefield = 'authprofilefield';

    case usersupport = 'usersupport';

    case api_enabled = 'api_enabled';

    case debugmode = 'debugmode';
}
