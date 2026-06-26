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
 * Typed representation of a Moodle course module (activity instance).
 *
 * Maps to the `course_modules` table. Each row represents one activity
 * (forum, assignment, quiz, etc.) placed in a course section.
 *
 * @api
 *
 * @method int         get_course()                                                                   Course ID
 * @method self        with_course(int $v)
 * @method int         get_module()              Module type ID ({modules}.id)
 * @method self        with_module(int $v)
 * @method int         get_instance()            Activity instance ID ({forum}.id, {assign}.id, etc.)
 * @method self        with_instance(int $v)
 * @method int         get_section()                                                                  Section ID within course
 * @method self        with_section(int $v)
 * @method null|string get_idnumber()                                                                 Custom ID number
 * @method self        with_idnumber(?string $v)
 * @method int         get_added()                                                                    Timestamp when added
 * @method self        with_added(int $v)
 * @method int         get_visible()             Visibility flag (0/1)
 * @method self        with_visible(int $v)
 * @method int         get_visibleoncoursepage()                                                      Whether shown on course page
 * @method self        with_visibleoncoursepage(int $v)
 * @method int         get_completion()                                                               Completion tracking type
 * @method self        with_completion(int $v)
 * @method int         get_completionexpected()                                                       Expected completion timestamp
 * @method self        with_completionexpected(int $v)
 * @method null|string get_availability()                                                             JSON availability conditions
 * @method self        with_availability(?string $v)
 * @method int         get_deletioninprogress()                                                       Soft delete flag
 * @method self        with_deletioninprogress(int $v)
 * @method int         get_showdescription()                                                          Show description on course page
 * @method self        with_showdescription(int $v)
 */
class CourseModule extends AbstractMoodleEntity
{
    protected int $course = 0;

    protected int $module = 0;

    protected int $instance = 0;

    protected int $section = 0;

    protected ?string $idnumber = null;

    protected int $added = 0;

    protected int $visible = 1;

    protected int $visibleoncoursepage = 1;

    protected int $completion = 0;

    protected int $completionexpected = 0;

    protected ?string $availability = null;

    protected int $deletioninprogress = 0;

    protected int $showdescription = 0;

    public static function getTable(): string
    {
        return 'course_modules';
    }

    /**
     * Whether this module is visible to students.
     */
    public function isVisible(): bool
    {
        return $this->visible === 1 && $this->deletioninprogress === 0;
    }

    /**
     * Whether completion tracking is enabled for this module.
     */
    public function hasCompletion(): bool
    {
        return $this->completion > 0;
    }
}
