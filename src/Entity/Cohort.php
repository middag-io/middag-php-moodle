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

/**
 * Cohort Entity (Moodle Native).
 *
 * @method int         get_contextid()
 * @method self        with_contextid(int $contextid)
 * @method string      get_name()
 * @method self        with_name(string $name)
 * @method null|string get_idnumber()
 * @method self        with_idnumber(?string $idnumber)
 * @method null|string get_description()
 * @method self        with_description(?string $description)
 * @method int         get_descriptionformat()
 * @method self        with_descriptionformat(int $descriptionformat)
 * @method int         get_visible()
 * @method self        with_visible(int $visible)
 * @method string      get_component()
 * @method self        with_component(string $component)
 * @method null|string get_theme()
 * @method self        with_theme(?string $theme)
 *
 * @api
 */
class Cohort extends AbstractMoodleEntity
{
    protected int $contextid = 0;

    protected string $name = '';

    protected ?string $idnumber = null;

    protected ?string $description = null;

    protected int $descriptionformat = 0;

    protected int $visible = 1;

    protected string $component = '';

    protected ?string $theme = null;

    /**
     * {@inheritDoc}
     */
    public static function getTable(): string
    {
        return 'cohort';
    }
}
