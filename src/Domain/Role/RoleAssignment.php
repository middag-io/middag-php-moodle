<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Domain\Role;

use Middag\Moodle\Domain\AbstractMoodleEntity;

/**
 * Role assignment entity (Moodle native).
 *
 * Represents a row of `mdl_role_assignments`.
 *
 * @method int    get_roleid()
 * @method self   with_roleid(int $roleid)
 * @method int    get_contextid()
 * @method self   with_contextid(int $contextid)
 * @method int    get_userid()
 * @method self   with_userid(int $userid)
 * @method int    get_modifierid()
 * @method self   with_modifierid(int $modifierid)
 * @method string get_component()
 * @method self   with_component(string $component)
 * @method int    get_itemid()
 * @method self   with_itemid(int $itemid)
 * @method int    get_sortorder()
 * @method self   with_sortorder(int $sortorder)
 *
 * @api
 */
class RoleAssignment extends AbstractMoodleEntity
{
    protected int $roleid = 0;

    protected int $contextid = 0;

    protected int $userid = 0;

    protected int $modifierid = 0;

    protected string $component = '';

    protected int $itemid = 0;

    protected int $sortorder = 0;

    /**
     * {@inheritDoc}
     */
    public static function getTable(): string
    {
        return 'role_assignments';
    }

    /**
     * Whether this assignment was made manually (no enrol plugin component).
     */
    public function isManual(): bool
    {
        return $this->component === '';
    }

    /**
     * Whether this assignment originates from an enrol plugin or other component.
     */
    public function isFromPlugin(): bool
    {
        return $this->component !== '';
    }
}
