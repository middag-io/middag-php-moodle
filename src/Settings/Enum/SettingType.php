<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Settings\Enum;

/**
 * Enumeration of supported setting types (ADR-312).
 *
 * Maps 1:1 to Moodle's native admin_setting_config* classes.
 * Types with a DSL class in this directory can be used in the typed settings DSL (ADR-311).
 * Types without a DSL class are registered for schema metadata and future implementation.
 *
 * @api
 */
enum SettingType: string
{
    // --- Input types (with storage) ---

    /** Single-line text input. DSL: text.php */
    case text = 'text';

    /** Boolean checkbox. DSL: checkbox.php */
    case checkbox = 'checkbox';

    /** Dropdown select. DSL: select.php */
    case select = 'select';

    /** Dropdown select with search/autocomplete. */
    case autocomplete = 'autocomplete';

    /** Password input (unmasked). DSL: password.php */
    case password = 'password';

    /** Password input (encrypted at rest). */
    case EncryptedPassword = 'EncryptedPassword';

    /** Multi-line text area. DSL: textarea.php */
    case textarea = 'textarea';

    /** Rich text editor (TinyMCE/Atto). */
    case htmleditor = 'htmleditor';

    /** Colour picker. */
    case colourpicker = 'colourpicker';

    /** Time duration (seconds, minutes, hours, days). */
    case duration = 'duration';

    /** Time of day (hour:minute). */
    case time = 'time';

    /** Multiple checkboxes (key-value pairs). */
    case multicheckbox = 'multicheckbox';

    /** Multiple select (multi-value). */
    case multiselect = 'multiselect';

    /** File stored via Moodle file API. */
    case storedfile = 'storedfile';

    /** File path on server filesystem. */
    case filepath = 'filepath';

    /** Directory path on server filesystem. */
    case directory = 'directory';

    /** Executable path on server. */
    case executable = 'executable';

    /** IP address list. */
    case iplist = 'iplist';

    /** Port list. */
    case portlist = 'portlist';

    // --- Non-storage types (display only) ---

    /** Section heading. DSL: heading.php */
    case heading = 'heading';

    /** Static description text. DSL: description.php */
    case description = 'description';

    /** Clickable link. DSL: link.php */
    case link = 'link';

    /**
     * Whether this type stores a config value.
     */
    public function stores_value(): bool
    {
        return !in_array($this, [self::heading, self::description, self::link], true);
    }
}
