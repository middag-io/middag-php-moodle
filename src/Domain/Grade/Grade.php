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
use Middag\Moodle\Shared\Enum\TextFormat as text_format;

/**
 * Grade entity (Moodle native).
 *
 * Represents a row of `mdl_grade_grades`.
 *
 * @method int         get_itemid()
 * @method self        with_itemid(int $itemid)
 * @method int         get_userid()
 * @method self        with_userid(int $userid)
 * @method null|float  get_rawgrade()
 * @method self        with_rawgrade(?float $rawgrade)
 * @method float       get_rawgrademax()
 * @method self        with_rawgrademax(float $rawgrademax)
 * @method float       get_rawgrademin()
 * @method self        with_rawgrademin(float $rawgrademin)
 * @method null|int    get_rawscaleid()
 * @method self        with_rawscaleid(?int $rawscaleid)
 * @method int         get_usermodified()
 * @method self        with_usermodified(int $usermodified)
 * @method null|float  get_finalgrade()
 * @method self        with_finalgrade(?float $finalgrade)
 * @method int         get_hidden()
 * @method self        with_hidden(int $hidden)
 * @method int         get_locked()
 * @method self        with_locked(int $locked)
 * @method int         get_locktime()
 * @method self        with_locktime(int $locktime)
 * @method int         get_exported()
 * @method self        with_exported(int $exported)
 * @method int         get_overridden()
 * @method self        with_overridden(int $overridden)
 * @method int         get_excluded()
 * @method self        with_excluded(int $excluded)
 * @method null|string get_feedback()
 * @method self        with_feedback(?string $feedback)
 * @method int         get_feedbackformat()
 * @method self        with_feedbackformat(int $feedbackformat)
 * @method null|string get_information()
 * @method self        with_information(?string $information)
 * @method int         get_informationformat()
 * @method self        with_informationformat(int $informationformat)
 * @method null|string get_aggregationstatus()
 * @method self        with_aggregationstatus(?string $aggregationstatus)
 * @method null|float  get_aggregationweight()
 * @method self        with_aggregationweight(?float $aggregationweight)
 *
 * @api
 */
class Grade extends AbstractMoodleEntity
{
    protected int $itemid = 0;

    protected int $userid = 0;

    protected ?float $rawgrade = null;

    protected float $rawgrademax = 100.0;

    protected float $rawgrademin = 0.0;

    protected ?int $rawscaleid = null;

    protected int $usermodified = 0;

    protected ?float $finalgrade = null;

    protected int $hidden = 0;

    protected int $locked = 0;

    protected int $locktime = 0;

    protected int $exported = 0;

    protected int $overridden = 0;

    protected int $excluded = 0;

    protected ?string $feedback = null;

    /**
     * Raw feedback format value (mapped to text_format enum via accessor).
     */
    protected int $feedbackformat = 0;

    protected ?string $information = null;

    /**
     * Raw information format value (mapped to text_format enum via accessor).
     */
    protected int $informationformat = 0;

    protected ?string $aggregationstatus = null;

    protected ?float $aggregationweight = null;

    /**
     * {@inheritDoc}
     */
    public static function getTable(): string
    {
        return 'grade_grades';
    }

    /**
     * Get the typed feedback format.
     */
    public function feedbackFormat(): text_format
    {
        return text_format::resolve($this->feedbackformat);
    }

    /**
     * Get the typed information format.
     */
    public function infoFormat(): text_format
    {
        return text_format::resolve($this->informationformat);
    }

    /**
     * Whether the grade is hidden.
     */
    public function isHidden(): bool
    {
        return $this->hidden > 0;
    }

    /**
     * Whether the grade is locked.
     */
    public function isLocked(): bool
    {
        return $this->locked > 0;
    }

    /**
     * Whether the grade has been overridden.
     */
    public function isOverridden(): bool
    {
        return $this->overridden > 0;
    }

    /**
     * Whether the grade is excluded from aggregation.
     */
    public function isExcluded(): bool
    {
        return $this->excluded > 0;
    }

    /**
     * Whether the grade has feedback text.
     */
    public function hasFeedback(): bool
    {
        return $this->feedback !== null && $this->feedback !== '';
    }
}
