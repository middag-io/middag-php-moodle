<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Domain\Grade;

use Middag\Moodle\Domain\AbstractMoodleEntity;
use Middag\Moodle\Domain\Grade\GradeDisplayType as grade_display_type;
use Middag\Moodle\Domain\Grade\GradeType as grade_type;

/**
 * Grade item entity (Moodle native).
 *
 * Represents a row of `mdl_grade_items`.
 *
 * @method int         get_courseid()
 * @method self        with_courseid(int $courseid)
 * @method int         get_categoryid()
 * @method self        with_categoryid(int $categoryid)
 * @method null|string get_itemname()
 * @method self        with_itemname(?string $itemname)
 * @method string      get_itemtype()
 * @method self        with_itemtype(string $itemtype)
 * @method null|string get_itemmodule()
 * @method self        with_itemmodule(?string $itemmodule)
 * @method null|int    get_iteminstance()
 * @method self        with_iteminstance(?int $iteminstance)
 * @method null|int    get_itemnumber()
 * @method self        with_itemnumber(?int $itemnumber)
 * @method null|string get_iteminfo()
 * @method self        with_iteminfo(?string $iteminfo)
 * @method null|string get_idnumber()
 * @method self        with_idnumber(?string $idnumber)
 * @method null|string get_calculation()
 * @method self        with_calculation(?string $calculation)
 * @method int         get_gradetype()
 * @method self        with_gradetype(int $gradetype)
 * @method float       get_grademax()
 * @method self        with_grademax(float $grademax)
 * @method float       get_grademin()
 * @method self        with_grademin(float $grademin)
 * @method null|int    get_scaleid()
 * @method self        with_scaleid(?int $scaleid)
 * @method null|int    get_outcomeid()
 * @method self        with_outcomeid(?int $outcomeid)
 * @method float       get_gradepass()
 * @method self        with_gradepass(float $gradepass)
 * @method float       get_multfactor()
 * @method self        with_multfactor(float $multfactor)
 * @method float       get_plusfactor()
 * @method self        with_plusfactor(float $plusfactor)
 * @method float       get_aggregationcoef()
 * @method self        with_aggregationcoef(float $aggregationcoef)
 * @method float       get_aggregationcoef2()
 * @method self        with_aggregationcoef2(float $aggregationcoef2)
 * @method int         get_sortorder()
 * @method self        with_sortorder(int $sortorder)
 * @method int         get_display()
 * @method self        with_display(int $display)
 * @method null|int    get_decimals()
 * @method self        with_decimals(?int $decimals)
 * @method int         get_hidden()
 * @method self        with_hidden(int $hidden)
 * @method int         get_locked()
 * @method self        with_locked(int $locked)
 * @method int         get_locktime()
 * @method self        with_locktime(int $locktime)
 * @method int         get_needsupdate()
 * @method self        with_needsupdate(int $needsupdate)
 * @method int         get_weightoverride()
 * @method self        with_weightoverride(int $weightoverride)
 *
 * @api
 */
class GradeItem extends AbstractMoodleEntity
{
    protected int $courseid = 0;

    protected int $categoryid = 0;

    protected ?string $itemname = null;

    protected string $itemtype = '';

    protected ?string $itemmodule = null;

    protected ?int $iteminstance = null;

    protected ?int $itemnumber = null;

    protected ?string $iteminfo = null;

    protected ?string $idnumber = null;

    protected ?string $calculation = null;

    /**
     * Raw grade type value (mapped to grade_type enum via accessor).
     */
    protected int $gradetype = 0;

    protected float $grademax = 100.0;

    protected float $grademin = 0.0;

    protected ?int $scaleid = null;

    protected ?int $outcomeid = null;

    protected float $gradepass = 0.0;

    protected float $multfactor = 1.0;

    protected float $plusfactor = 0.0;

    protected float $aggregationcoef = 0.0;

    protected float $aggregationcoef2 = 0.0;

    protected int $sortorder = 0;

    /**
     * Raw display type value (mapped to grade_display_type enum via accessor).
     */
    protected int $display = 0;

    protected ?int $decimals = null;

    protected int $hidden = 0;

    protected int $locked = 0;

    protected int $locktime = 0;

    protected int $needsupdate = 0;

    protected int $weightoverride = 0;

    /**
     * {@inheritDoc}
     */
    public static function getTable(): string
    {
        return 'grade_items';
    }

    /**
     * Get the typed grade type.
     */
    public function gradeType(): grade_type
    {
        return grade_type::resolve($this->gradetype);
    }

    /**
     * Get the typed display type.
     */
    public function displayType(): grade_display_type
    {
        return grade_display_type::resolve($this->display);
    }

    /**
     * Whether the grade item is hidden.
     */
    public function isHidden(): bool
    {
        return $this->hidden > 0;
    }

    /**
     * Whether the grade item is locked.
     */
    public function isLocked(): bool
    {
        return $this->locked > 0;
    }
}
