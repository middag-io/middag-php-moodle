<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Domain\Group;

use Middag\Moodle\Domain\AbstractMoodleEntity;

/**
 * Group Entity (Moodle Native).
 *
 * @method int         get_courseid()
 * @method self        with_courseid(int $courseid)
 * @method string      get_idnumber()
 * @method self        with_idnumber(string $idnumber)
 * @method string      get_name()
 * @method self        with_name(string $name)
 * @method null|string get_description()
 * @method self        with_description(?string $description)
 * @method int         get_descriptionformat()
 * @method self        with_descriptionformat(int $descriptionformat)
 * @method null|string get_enrolmentkey()
 * @method self        with_enrolmentkey(?string $enrolmentkey)
 * @method int         get_picture()
 * @method self        with_picture(int $picture)
 * @method int         get_visibility()
 * @method self        with_visibility(int $visibility)
 * @method int         get_participation()
 * @method self        with_participation(int $participation)
 *
 * @api
 */
class Group extends AbstractMoodleEntity
{
    protected int $courseid = 0;

    protected string $idnumber = '';

    protected string $name = '';

    protected ?string $description = null;

    protected int $descriptionformat = 0;

    protected ?string $enrolmentkey = null;

    protected int $picture = 0;

    protected int $visibility = 0;

    protected int $participation = 1;

    /**
     * {@inheritDoc}
     */
    public static function getTable(): string
    {
        return 'groups';
    }
}
