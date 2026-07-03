<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Domain\Course;

use Middag\Moodle\Domain\AbstractMoodleEntity;

/**
 * Category Entity (Moodle Native).
 *
 * @method string      get_name()
 * @method self        with_name(string $name)
 * @method null|string get_idnumber()
 * @method self        with_idnumber(?string $idnumber)
 * @method null|string get_description()
 * @method self        with_description(?string $description)
 * @method int         get_descriptionformat()
 * @method self        with_descriptionformat(int $descriptionformat)
 * @method int         get_parent()
 * @method self        with_parent(int $parent)
 * @method int         get_sortorder()
 * @method self        with_sortorder(int $sortorder)
 * @method int         get_coursecount()
 * @method self        with_coursecount(int $coursecount)
 * @method int         get_visible()
 * @method self        with_visible(int $visible)
 * @method int         get_visibleold()
 * @method self        with_visibleold(int $visibleold)
 * @method int         get_depth()
 * @method self        with_depth(int $depth)
 * @method string      get_path()
 * @method self        with_path(string $path)
 * @method null|string get_theme()
 * @method self        with_theme(?string $theme)
 *
 * @api
 */
class Category extends AbstractMoodleEntity
{
    protected string $name = '';

    protected ?string $idnumber = null;

    protected ?string $description = null;

    protected int $descriptionformat = 0;

    protected int $parent = 0;

    protected int $sortorder = 0;

    protected int $coursecount = 0;

    protected int $visible = 1;

    protected int $visibleold = 1;

    protected int $depth = 0;

    protected string $path = '';

    protected ?string $theme = null;

    /**
     * Returns the Moodle DB table used by this entity.
     *
     * @return string
     */
    public static function getTable(): string
    {
        return 'course_categories';
    }
}
