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
 * Role Entity (Moodle Native).
 *
 * @method string get_name()
 * @method self   with_name(string $name)
 * @method string get_shortname()
 * @method self   with_shortname(string $shortname)
 * @method string get_description()
 * @method self   with_description(string $description)
 * @method int    get_sortorder()
 * @method self   with_sortorder(int $sortorder)
 * @method string get_archetype()
 * @method self   with_archetype(string $archetype)
 *
 * @api
 */
class Role extends AbstractMoodleEntity
{
    protected string $name = '';

    protected string $shortname = '';

    protected string $description = '';

    protected int $sortorder = 0;

    protected string $archetype = '';

    /**
     * {@inheritDoc}
     */
    public static function getTable(): string
    {
        return 'role';
    }
}
