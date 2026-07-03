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
 * Course Entity (Moodle Native).
 *
 * @method int         get_category()
 * @method self        with_category(int $category)
 * @method int         get_sortorder()
 * @method self        with_sortorder(int $sortorder)
 * @method string      get_fullname()
 * @method self        with_fullname(string $fullname)
 * @method string      get_shortname()
 * @method self        with_shortname(string $shortname)
 * @method string      get_idnumber()
 * @method self        with_idnumber(string $idnumber)
 * @method null|string get_summary()
 * @method self        with_summary(?string $summary)
 * @method int         get_summaryformat()
 * @method self        with_summaryformat(int $summaryformat)
 * @method string      get_format()
 * @method self        with_format(string $format)
 * @method int         get_showgrades()
 * @method self        with_showgrades(int $showgrades)
 * @method int         get_newsitems()
 * @method self        with_newsitems(int $newsitems)
 * @method int         get_startdate()
 * @method self        with_startdate(int $startdate)
 * @method int         get_enddate()
 * @method self        with_enddate(int $enddate)
 * @method int         get_relativedatesmode()
 * @method self        with_relativedatesmode(int $relativedatesmode)
 * @method int         get_marker()
 * @method self        with_marker(int $marker)
 * @method int         get_maxbytes()
 * @method self        with_maxbytes(int $maxbytes)
 * @method int         get_legacyfiles()
 * @method self        with_legacyfiles(int $legacyfiles)
 * @method int         get_showreports()
 * @method self        with_showreports(int $showreports)
 * @method int         get_visible()
 * @method self        with_visible(int $visible)
 * @method int         get_visibleold()
 * @method self        with_visibleold(int $visibleold)
 * @method null|int    get_downloadcontent()
 * @method self        with_downloadcontent(?int $downloadcontent)
 * @method int         get_groupmode()
 * @method self        with_groupmode(int $groupmode)
 * @method int         get_groupmodeforce()
 * @method self        with_groupmodeforce(int $groupmodeforce)
 * @method int         get_defaultgroupingid()
 * @method self        with_defaultgroupingid(int $defaultgroupingid)
 * @method string      get_lang()
 * @method self        with_lang(string $lang)
 * @method string      get_calendartype()
 * @method self        with_calendartype(string $calendartype)
 * @method string      get_theme()
 * @method self        with_theme(string $theme)
 * @method int         get_requested()
 * @method self        with_requested(int $requested)
 * @method int         get_enablecompletion()
 * @method self        with_enablecompletion(int $enablecompletion)
 * @method int         get_completionnotify()
 * @method self        with_completionnotify(int $completionnotify)
 * @method int         get_cacherev()
 * @method self        with_cacherev(int $cacherev)
 * @method null|int    get_originalcourseid()
 * @method self        with_originalcourseid(?int $originalcourseid)
 * @method int         get_showactivitydates()
 * @method self        with_showactivitydates(int $showactivitydates)
 * @method null|int    get_showcompletionconditions()
 * @method self        with_showcompletionconditions(?int $showcompletionconditions)
 * @method null|string get_pdfexportfont()
 * @method self        with_pdfexportfont(?string $pdfexportfont)
 *
 * @api
 */
class Course extends AbstractMoodleEntity
{
    protected int $category = 0;

    protected int $sortorder = 0;

    protected string $fullname = '';

    protected string $shortname = '';

    protected string $idnumber = '';

    protected ?string $summary = null;

    protected int $summaryformat = 0;

    protected string $format = 'topics';

    protected int $showgrades = 1;

    protected int $newsitems = 1;

    protected int $startdate = 0;

    protected int $enddate = 0;

    protected int $relativedatesmode = 0;

    protected int $marker = 0;

    protected int $maxbytes = 0;

    protected int $legacyfiles = 0;

    protected int $showreports = 0;

    protected int $visible = 1;

    protected int $visibleold = 1;

    protected ?int $downloadcontent = null;

    protected int $groupmode = 0;

    protected int $groupmodeforce = 0;

    protected int $defaultgroupingid = 0;

    protected string $lang = '';

    protected string $calendartype = '';

    protected string $theme = '';

    protected int $requested = 0;

    protected int $enablecompletion = 0;

    protected int $completionnotify = 0;

    protected int $cacherev = 0;

    protected ?int $originalcourseid = null;

    protected int $showactivitydates = 0;

    protected ?int $showcompletionconditions = null;

    protected ?string $pdfexportfont = null;

    /**
     * {@inheritDoc}
     */
    public static function getTable(): string
    {
        return 'course';
    }
}
