<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Domain\Enrolment;

use Middag\Moodle\Domain\AbstractMoodleEntity;

/**
 * User Enrolment Entity (Moodle Native).
 *
 * @method int  get_status()
 * @method self with_status(int $status)
 * @method int  get_enrolid()
 * @method self with_enrolid(int $enrolid)
 * @method int  get_userid()
 * @method self with_userid(int $userid)
 * @method int  get_timestart()
 * @method self with_timestart(int $timestart)
 * @method int  get_timeend()
 * @method self with_timeend(int $timeend)
 * @method int  get_modifierid()
 * @method self with_modifierid(int $modifierid)
 *
 * @api
 */
class UserEnrolment extends AbstractMoodleEntity
{
    protected int $status = 0;

    protected int $enrolid = 0;

    protected int $userid = 0;

    protected int $timestart = 0;

    protected int $timeend = 2147483647;

    protected int $modifierid = 0;

    /**
     * {@inheritDoc}
     */
    public static function getTable(): string
    {
        return 'user_enrolments';
    }
}
