<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Entity;

use stdClass;

/**
 * User profile field entity (Moodle native).
 *
 * Represents a row of `mdl_user_info_field` — the definition of a custom
 * profile field that an admin can create (distinct from the `$USER` record and
 * from `\core_customfield` used by courses/activities, which is covered by
 * `custom_field_support`).
 *
 * Datatype is kept as a string (not enum) because Moodle allows plugins to
 * register new profile field types; constraining to a fixed enum would break
 * forward compatibility. Known core types: `text`, `textarea`, `menu`,
 * `checkbox`, `datetime`, `social`.
 *
 * @method string      get_shortname()
 * @method self        with_shortname(string $shortname)
 * @method string      get_name()
 * @method self        with_name(string $name)
 * @method string      get_datatype()
 * @method self        with_datatype(string $datatype)
 * @method null|string get_description()
 * @method self        with_description(?string $description)
 * @method int         get_descriptionformat()
 * @method self        with_descriptionformat(int $descriptionformat)
 * @method int         get_categoryid()
 * @method self        with_categoryid(int $categoryid)
 * @method int         get_sortorder()
 * @method self        with_sortorder(int $sortorder)
 * @method int         get_required()
 * @method self        with_required(int $required)
 * @method int         get_locked()
 * @method self        with_locked(int $locked)
 * @method int         get_visible()
 * @method self        with_visible(int $visible)
 * @method int         get_forceunique()
 * @method self        with_forceunique(int $forceunique)
 * @method int         get_signup()
 * @method self        with_signup(int $signup)
 * @method null|string get_defaultdata()
 * @method self        with_defaultdata(?string $defaultdata)
 * @method int         get_defaultdataformat()
 * @method self        with_defaultdataformat(int $defaultdataformat)
 * @method null|string get_param1()
 * @method self        with_param1(?string $param1)
 * @method null|string get_param2()
 * @method self        with_param2(?string $param2)
 * @method null|string get_param3()
 * @method self        with_param3(?string $param3)
 * @method null|string get_param4()
 * @method self        with_param4(?string $param4)
 * @method null|string get_param5()
 * @method self        with_param5(?string $param5)
 *
 * @api
 */
class UserProfileField extends AbstractMoodleEntity
{
    protected string $shortname = '';

    protected string $name = '';

    /**
     * Datatype identifier (e.g. 'text', 'textarea', 'menu', 'checkbox',
     * 'datetime', 'social'). Plugins may register more.
     */
    protected string $datatype = '';

    protected ?string $description = null;

    protected int $descriptionformat = 1;

    protected int $categoryid = 0;

    protected int $sortorder = 0;

    protected int $required = 0;

    protected int $locked = 0;

    protected int $visible = 0;

    protected int $forceunique = 0;

    protected int $signup = 0;

    protected ?string $defaultdata = null;

    protected int $defaultdataformat = 0;

    protected ?string $param1 = null;

    protected ?string $param2 = null;

    protected ?string $param3 = null;

    protected ?string $param4 = null;

    protected ?string $param5 = null;

    public static function getTable(): string
    {
        return 'user_info_field';
    }

    /**
     * Whether the field must be filled by the user.
     */
    public function isRequired(): bool
    {
        return $this->required > 0;
    }

    /**
     * Whether the field is locked against user editing.
     */
    public function isLocked(): bool
    {
        return $this->locked > 0;
    }

    /**
     * Whether the field is shown in the signup form.
     */
    public function isSignup(): bool
    {
        return $this->signup > 0;
    }

    /**
     * Whether the field must hold a unique value across users.
     */
    public function requiresUnique(): bool
    {
        return $this->forceunique > 0;
    }

    /**
     * Visibility level — Moodle uses 0/1/2 for nobody/everyone/self-only.
     */
    public function visibilityLevel(): int
    {
        return $this->visible;
    }

    /**
     * Factory from a `mdl_user_info_field` record.
     *
     * @param array|stdClass $record
     */
    public static function fromRecord(array|stdClass $record): static
    {
        $data = (object) $record;

        $entity = new static();

        $scalar_map = [
            'id' => 'int',
            'categoryid' => 'int',
            'sortorder' => 'int',
            'required' => 'int',
            'locked' => 'int',
            'visible' => 'int',
            'forceunique' => 'int',
            'signup' => 'int',
            'descriptionformat' => 'int',
            'defaultdataformat' => 'int',
            'shortname' => 'string',
            'name' => 'string',
            'datatype' => 'string',
        ];

        foreach ($scalar_map as $prop => $type) {
            if (!property_exists($data, $prop)) {
                continue;
            }

            $entity->{$prop} = $type === 'int' ? (int) $data->{$prop} : (string) $data->{$prop};
        }

        $nullable_strings = ['description', 'defaultdata', 'param1', 'param2', 'param3', 'param4', 'param5'];

        foreach ($nullable_strings as $prop) {
            if (property_exists($data, $prop) && $data->{$prop} !== null) {
                $entity->{$prop} = (string) $data->{$prop};
            }
        }

        return $entity;
    }
}
