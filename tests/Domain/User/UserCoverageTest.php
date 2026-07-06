<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Domain\User;

use Middag\Moodle\Domain\User\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * User is a pure Moodle-native entity: it declares typed properties and a
 * getX()/withX() accessor pair per property, plus getTable() and getFullname().
 * Every accessor line is exercised directly, defaults are asserted, and the
 * fluent setters are verified to return the same instance so the whole class
 * body is covered without a Moodle runtime.
 *
 * @internal
 */
#[CoversClass(User::class)]
final class UserCoverageTest extends TestCase
{
    #[Test]
    public function testGetTableReturnsUser(): void
    {
        self::assertSame('user', User::getTable());
    }

    #[Test]
    public function testGetFullnameConcatenatesFirstAndLastName(): void
    {
        $user = (new User())->withFirstname('John')->withLastname('Doe');

        self::assertSame('John Doe', $user->getFullname());
    }

    #[Test]
    public function testGetFullnameTrimsWhenNamesAreEmpty(): void
    {
        $user = new User();

        self::assertSame('', $user->getFullname());
    }

    #[Test]
    public function testDefaultsAreExposedThroughGetters(): void
    {
        $user = new User();

        self::assertSame('manual', $user->getAuth());
        self::assertSame(0, $user->getConfirmed());
        self::assertSame(0, $user->getPolicyagreed());
        self::assertSame(0, $user->getDeleted());
        self::assertSame(0, $user->getSuspended());
        self::assertSame(0, $user->getMnethostid());
        self::assertSame('', $user->getUsername());
        self::assertSame('', $user->getPassword());
        self::assertSame('', $user->getIdnumber());
        self::assertSame('', $user->getFirstname());
        self::assertSame('', $user->getLastname());
        self::assertSame('', $user->getEmail());
        self::assertSame(0, $user->getEmailstop());
        self::assertSame('', $user->getPhone1());
        self::assertSame('', $user->getPhone2());
        self::assertSame('', $user->getInstitution());
        self::assertSame('', $user->getDepartment());
        self::assertSame('', $user->getAddress());
        self::assertSame('', $user->getCity());
        self::assertSame('', $user->getCountry());
        self::assertSame('en', $user->getLang());
        self::assertSame('gregorian', $user->getCalendartype());
        self::assertSame('', $user->getTheme());
        self::assertSame('99', $user->getTimezone());
        self::assertSame(0, $user->getFirstaccess());
        self::assertSame(0, $user->getLastaccess());
        self::assertSame(0, $user->getLastlogin());
        self::assertSame(0, $user->getCurrentlogin());
        self::assertSame('', $user->getLastip());
        self::assertSame('', $user->getSecret());
        self::assertSame(0, $user->getPicture());
        self::assertNull($user->getDescription());
        self::assertSame(1, $user->getDescriptionformat());
        self::assertSame(1, $user->getMailformat());
        self::assertSame(0, $user->getMaildigest());
        self::assertSame(2, $user->getMaildisplay());
        self::assertSame(1, $user->getAutosubscribe());
        self::assertSame(0, $user->getTrackforums());
        self::assertSame(0, $user->getTrustbitmask());
        self::assertNull($user->getImagealt());
        self::assertNull($user->getLastnamephonetic());
        self::assertNull($user->getFirstnamephonetic());
        self::assertNull($user->getMiddlename());
        self::assertNull($user->getAlternatename());
        self::assertNull($user->getMoodlenetprofile());
    }

    #[Test]
    public function testWithersAreFluentAndPersistValues(): void
    {
        $user = new User();

        self::assertSame($user, $user->withAuth('ldap'));
        self::assertSame($user, $user->withConfirmed(1));
        self::assertSame($user, $user->withPolicyagreed(1));
        self::assertSame($user, $user->withDeleted(1));
        self::assertSame($user, $user->withSuspended(1));
        self::assertSame($user, $user->withMnethostid(7));
        self::assertSame($user, $user->withUsername('jdoe'));
        self::assertSame($user, $user->withPassword('secrethash'));
        self::assertSame($user, $user->withIdnumber('ID-1'));
        self::assertSame($user, $user->withFirstname('John'));
        self::assertSame($user, $user->withLastname('Doe'));
        self::assertSame($user, $user->withEmail('john@example.com'));
        self::assertSame($user, $user->withEmailstop(1));
        self::assertSame($user, $user->withPhone1('111'));
        self::assertSame($user, $user->withPhone2('222'));
        self::assertSame($user, $user->withInstitution('MIDDAG'));
        self::assertSame($user, $user->withDepartment('Eng'));
        self::assertSame($user, $user->withAddress('Street 1'));
        self::assertSame($user, $user->withCity('Town'));
        self::assertSame($user, $user->withCountry('BR'));
        self::assertSame($user, $user->withLang('pt_br'));
        self::assertSame($user, $user->withCalendartype('iso8601'));
        self::assertSame($user, $user->withTheme('boost'));
        self::assertSame($user, $user->withTimezone('America/Sao_Paulo'));
        self::assertSame($user, $user->withFirstaccess(100));
        self::assertSame($user, $user->withLastaccess(200));
        self::assertSame($user, $user->withLastlogin(300));
        self::assertSame($user, $user->withCurrentlogin(400));
        self::assertSame($user, $user->withLastip('127.0.0.1'));
        self::assertSame($user, $user->withSecret('token'));
        self::assertSame($user, $user->withPicture(5));
        self::assertSame($user, $user->withDescription('bio'));
        self::assertSame($user, $user->withDescriptionformat(2));
        self::assertSame($user, $user->withMailformat(0));
        self::assertSame($user, $user->withMaildigest(2));
        self::assertSame($user, $user->withMaildisplay(0));
        self::assertSame($user, $user->withAutosubscribe(0));
        self::assertSame($user, $user->withTrackforums(1));
        self::assertSame($user, $user->withTrustbitmask(3));
        self::assertSame($user, $user->withImagealt('alt'));
        self::assertSame($user, $user->withLastnamephonetic('doe'));
        self::assertSame($user, $user->withFirstnamephonetic('john'));
        self::assertSame($user, $user->withMiddlename('M'));
        self::assertSame($user, $user->withAlternatename('Johnny'));
        self::assertSame($user, $user->withMoodlenetprofile('@john'));

        self::assertSame('ldap', $user->getAuth());
        self::assertSame(1, $user->getConfirmed());
        self::assertSame(1, $user->getPolicyagreed());
        self::assertSame(1, $user->getDeleted());
        self::assertSame(1, $user->getSuspended());
        self::assertSame(7, $user->getMnethostid());
        self::assertSame('jdoe', $user->getUsername());
        self::assertSame('secrethash', $user->getPassword());
        self::assertSame('ID-1', $user->getIdnumber());
        self::assertSame('John', $user->getFirstname());
        self::assertSame('Doe', $user->getLastname());
        self::assertSame('john@example.com', $user->getEmail());
        self::assertSame(1, $user->getEmailstop());
        self::assertSame('111', $user->getPhone1());
        self::assertSame('222', $user->getPhone2());
        self::assertSame('MIDDAG', $user->getInstitution());
        self::assertSame('Eng', $user->getDepartment());
        self::assertSame('Street 1', $user->getAddress());
        self::assertSame('Town', $user->getCity());
        self::assertSame('BR', $user->getCountry());
        self::assertSame('pt_br', $user->getLang());
        self::assertSame('iso8601', $user->getCalendartype());
        self::assertSame('boost', $user->getTheme());
        self::assertSame('America/Sao_Paulo', $user->getTimezone());
        self::assertSame(100, $user->getFirstaccess());
        self::assertSame(200, $user->getLastaccess());
        self::assertSame(300, $user->getLastlogin());
        self::assertSame(400, $user->getCurrentlogin());
        self::assertSame('127.0.0.1', $user->getLastip());
        self::assertSame('token', $user->getSecret());
        self::assertSame(5, $user->getPicture());
        self::assertSame('bio', $user->getDescription());
        self::assertSame(2, $user->getDescriptionformat());
        self::assertSame(0, $user->getMailformat());
        self::assertSame(2, $user->getMaildigest());
        self::assertSame(0, $user->getMaildisplay());
        self::assertSame(0, $user->getAutosubscribe());
        self::assertSame(1, $user->getTrackforums());
        self::assertSame(3, $user->getTrustbitmask());
        self::assertSame('alt', $user->getImagealt());
        self::assertSame('doe', $user->getLastnamephonetic());
        self::assertSame('john', $user->getFirstnamephonetic());
        self::assertSame('M', $user->getMiddlename());
        self::assertSame('Johnny', $user->getAlternatename());
        self::assertSame('@john', $user->getMoodlenetprofile());
    }

    #[Test]
    public function testNullableWithersAcceptNull(): void
    {
        $user = (new User())
            ->withDescription('bio')
            ->withImagealt('alt')
            ->withLastnamephonetic('doe')
            ->withFirstnamephonetic('john')
            ->withMiddlename('M')
            ->withAlternatename('Johnny')
            ->withMoodlenetprofile('@john');

        $user->withDescription(null)
            ->withImagealt(null)
            ->withLastnamephonetic(null)
            ->withFirstnamephonetic(null)
            ->withMiddlename(null)
            ->withAlternatename(null)
            ->withMoodlenetprofile(null);

        self::assertNull($user->getDescription());
        self::assertNull($user->getImagealt());
        self::assertNull($user->getLastnamephonetic());
        self::assertNull($user->getFirstnamephonetic());
        self::assertNull($user->getMiddlename());
        self::assertNull($user->getAlternatename());
        self::assertNull($user->getMoodlenetprofile());
    }
}
