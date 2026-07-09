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
    case Text = 'text';

    /** Boolean checkbox. DSL: checkbox.php */
    case Checkbox = 'checkbox';

    /** Dropdown select. DSL: select.php */
    case Select = 'select';

    /** Dropdown select with search/autocomplete. */
    case Autocomplete = 'autocomplete';

    /** Password input (unmasked). DSL: password.php */
    case Password = 'password';

    /** Password input (encrypted at rest). */
    case EncryptedPassword = 'EncryptedPassword';

    /** Multi-line text area. DSL: textarea.php */
    case Textarea = 'textarea';

    /** Rich text editor (TinyMCE/Atto). */
    case HtmlEditor = 'htmleditor';

    /** Colour picker. */
    case ColourPicker = 'colourpicker';

    /** Time duration (seconds, minutes, hours, days). */
    case Duration = 'duration';

    /** Time of day (hour:minute). */
    case Time = 'time';

    /** Multiple checkboxes (key-value pairs). */
    case MultiCheckbox = 'multicheckbox';

    /** Multiple select (multi-value). */
    case MultiSelect = 'multiselect';

    /** File stored via Moodle file API. */
    case StoredFile = 'storedfile';

    /** File path on server filesystem. */
    case FilePath = 'filepath';

    /** Directory path on server filesystem. */
    case Directory = 'directory';

    /** Executable path on server. */
    case Executable = 'executable';

    /** IP address list. */
    case IpList = 'iplist';

    /** Port list. */
    case PortList = 'portlist';

    // --- Non-storage types (display only) ---

    /** Section heading. DSL: heading.php */
    case Heading = 'heading';

    /** Static description text. DSL: description.php */
    case Description = 'description';

    /** Clickable link. DSL: link.php */
    case Link = 'link';

    /**
     * Whether this type stores a config value.
     */
    public function stores_value(): bool
    {
        return !in_array($this, [self::Heading, self::Description, self::Link], true);
    }
}
