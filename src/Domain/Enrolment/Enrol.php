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
 * Enrol Entity (Moodle Native).
 *
 * @method string      get_enrol()
 * @method self        with_enrol(string $enrol)
 * @method int         get_status()
 * @method self        with_status(int $status)
 * @method int         get_courseid()
 * @method self        with_courseid(int $courseid)
 * @method int         get_sortorder()
 * @method self        with_sortorder(int $sortorder)
 * @method null|string get_name()
 * @method self        with_name(?string $name)
 * @method int         get_enrolperiod()
 * @method self        with_enrolperiod(int $enrolperiod)
 * @method int         get_enrolstartdate()
 * @method self        with_enrolstartdate(int $enrolstartdate)
 * @method int         get_enrolenddate()
 * @method self        with_enrolenddate(int $enrolenddate)
 * @method int         get_expirynotify()
 * @method self        with_expirynotify(int $expirynotify)
 * @method int         get_expirythreshold()
 * @method self        with_expirythreshold(int $expirythreshold)
 * @method int         get_notifyall()
 * @method self        with_notifyall(int $notifyall)
 * @method null|string get_password()
 * @method self        with_password(?string $password)
 * @method null|string get_cost()
 * @method self        with_cost(?string $cost)
 * @method null|string get_currency()
 * @method self        with_currency(?string $currency)
 * @method int         get_roleid()
 * @method self        with_roleid(int $roleid)
 * @method null|int    get_customint1()
 * @method self        with_customint1(?int $customint1)
 * @method null|int    get_customint2()
 * @method self        with_customint2(?int $customint2)
 * @method null|int    get_customint3()
 * @method self        with_customint3(?int $customint3)
 * @method null|int    get_customint4()
 * @method self        with_customint4(?int $customint4)
 * @method null|int    get_customint5()
 * @method self        with_customint5(?int $customint5)
 * @method null|int    get_customint6()
 * @method self        with_customint6(?int $customint6)
 * @method null|int    get_customint7()
 * @method self        with_customint7(?int $customint7)
 * @method null|int    get_customint8()
 * @method self        with_customint8(?int $customint8)
 * @method null|string get_customchar1()
 * @method self        with_customchar1(?string $customchar1)
 * @method null|string get_customchar2()
 * @method self        with_customchar2(?string $customchar2)
 * @method null|string get_customchar3()
 * @method self        with_customchar3(?string $customchar3)
 * @method null|float  get_customdec1()
 * @method self        with_customdec1(?float $customdec1)
 * @method null|float  get_customdec2()
 * @method self        with_customdec2(?float $customdec2)
 * @method null|string get_customtext1()
 * @method self        with_customtext1(?string $customtext1)
 * @method null|string get_customtext2()
 * @method self        with_customtext2(?string $customtext2)
 * @method null|string get_customtext3()
 * @method self        with_customtext3(?string $customtext3)
 * @method null|string get_customtext4()
 * @method self        with_customtext4(?string $customtext4)
 *
 * @api
 */
class Enrol extends AbstractMoodleEntity
{
    protected string $enrol = '';

    protected int $status = 0;

    protected int $courseid = 0;

    protected int $sortorder = 0;

    protected ?string $name = null;

    protected int $enrolperiod = 0;

    protected int $enrolstartdate = 0;

    protected int $enrolenddate = 0;

    protected int $expirynotify = 0;

    protected int $expirythreshold = 0;

    protected int $notifyall = 0;

    protected ?string $password = null;

    protected ?string $cost = null;

    protected ?string $currency = null;

    protected int $roleid = 0;

    protected ?int $customint1 = null;

    protected ?int $customint2 = null;

    protected ?int $customint3 = null;

    protected ?int $customint4 = null;

    protected ?int $customint5 = null;

    protected ?int $customint6 = null;

    protected ?int $customint7 = null;

    protected ?int $customint8 = null;

    protected ?string $customchar1 = null;

    protected ?string $customchar2 = null;

    protected ?string $customchar3 = null;

    protected ?float $customdec1 = null;

    protected ?float $customdec2 = null;

    protected ?string $customtext1 = null;

    protected ?string $customtext2 = null;

    protected ?string $customtext3 = null;

    protected ?string $customtext4 = null;

    /**
     * {@inheritDoc}
     */
    public static function getTable(): string
    {
        return 'enrol';
    }
}
