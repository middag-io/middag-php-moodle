<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Support;

use Middag\Moodle\Domain\User\User;
use Middag\Moodle\Support\UserSupport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * UserSupport wraps Moodle's user API. core\user::get_user() is a central
 * bootstrap stub, but get_user_by_email()/get_user_by_username() are not exposed
 * there, so those two wrappers are covered by skipped tests that auto-activate
 * once the central stub grows them. The create/update/delete/formatUserData
 * paths require $CFG->dirroot to resolve their require_once() of user library
 * files, so a temp directory holding empty stand-ins is prepared in setUp.
 *
 * @internal
 */
#[CoversClass(UserSupport::class)]
final class UserSupportCoverageTest extends TestCase
{
    private string $tempDir;

    private mixed $prevCfg;

    private mixed $prevUser;

    private mixed $prevConfig;

    protected function setUp(): void
    {
        $this->prevCfg = $GLOBALS['CFG'] ?? null;
        $this->prevUser = $GLOBALS['USER'] ?? null;
        $this->prevConfig = $GLOBALS['__middag_test_config'] ?? null;

        // createUser/updateUser/deleteUser require_once $CFG->dirroot.'/user/lib.php';
        // formatUserData -> UserFieldSupport require_once's '/user/profile/lib.php'.
        $this->tempDir = sys_get_temp_dir() . '/middag_user_support_test';
        foreach (['/user', '/user/profile'] as $sub) {
            if (!is_dir($this->tempDir . $sub)) {
                mkdir($this->tempDir . $sub, 0o777, true);
            }
        }
        file_put_contents($this->tempDir . '/user/lib.php', "<?php\n");
        file_put_contents($this->tempDir . '/user/profile/lib.php', "<?php\n");

        $GLOBALS['CFG'] = (object) ['dirroot' => $this->tempDir];
        $GLOBALS['__middag_test_config'] = [];

        unset(
            $GLOBALS['USER'],
            $GLOBALS['__middag_test_user_record'],
            $GLOBALS['__middag_test_fullname'],
            $GLOBALS['__middag_test_profile_fields'],
            $GLOBALS['__middag_test_created_user'],
            $GLOBALS['__middag_test_updated_user'],
            $GLOBALS['__middag_test_new_user_id'],
            $GLOBALS['__middag_test_delete_result'],
            $GLOBALS['__middag_test_user_by_email'],
            $GLOBALS['__middag_test_user_by_username'],
        );
    }

    protected function tearDown(): void
    {
        $GLOBALS['CFG'] = $this->prevCfg;
        $GLOBALS['USER'] = $this->prevUser;
        $GLOBALS['__middag_test_config'] = $this->prevConfig;

        unset(
            $GLOBALS['__middag_test_user_record'],
            $GLOBALS['__middag_test_fullname'],
            $GLOBALS['__middag_test_profile_fields'],
            $GLOBALS['__middag_test_created_user'],
            $GLOBALS['__middag_test_updated_user'],
            $GLOBALS['__middag_test_new_user_id'],
            $GLOBALS['__middag_test_delete_result'],
            $GLOBALS['__middag_test_user_by_email'],
            $GLOBALS['__middag_test_user_by_username'],
        );
    }

    #[Test]
    public function testGetUserReturnsAnEntityWhenFound(): void
    {
        $GLOBALS['__middag_test_user_record'] = (object) ['id' => 5, 'firstname' => 'Jane'];

        $user = UserSupport::getUser(5);

        self::assertInstanceOf(User::class, $user);
        self::assertSame(5, $user->getId());
    }

    #[Test]
    public function testGetUserReturnsNullWhenNotFound(): void
    {
        $GLOBALS['__middag_test_user_record'] = false;

        self::assertNull(UserSupport::getUser(5));
    }

    #[Test]
    public function testCreateUserAppliesEssentialDefaults(): void
    {
        $GLOBALS['__middag_test_config']['mnet_localhost_id'] = 1;
        $GLOBALS['__middag_test_new_user_id'] = 99;

        $userobj = new stdClass();
        $userobj->username = 'jdoe';

        $id = UserSupport::createUser($userobj);

        self::assertSame(99, $id);

        $created = $GLOBALS['__middag_test_created_user'][0];
        self::assertSame('manual', $created->auth);
        self::assertSame(1, $created->confirmed);
        self::assertSame(1, $created->mnethostid);
        self::assertFalse($GLOBALS['__middag_test_created_user'][1]);
        // $triggerevent defaults to true and reaches user_create_user's 3rd arg,
        // so user_created fires by default (regression guard for the old $nologin mismap).
        self::assertTrue($GLOBALS['__middag_test_created_user'][2]);
    }

    #[Test]
    public function testCreateUserKeepsProvidedValues(): void
    {
        $userobj = new stdClass();
        $userobj->auth = 'ldap';
        $userobj->confirmed = 0;
        $userobj->mnethostid = 7;

        UserSupport::createUser($userobj, true, true);

        $created = $GLOBALS['__middag_test_created_user'][0];
        self::assertSame('ldap', $created->auth);
        self::assertSame(0, $created->confirmed);
        self::assertSame(7, $created->mnethostid);
        self::assertTrue($GLOBALS['__middag_test_created_user'][1]);
    }

    #[Test]
    public function testUpdateUserDelegatesToMoodle(): void
    {
        $userobj = new stdClass();
        $userobj->id = 5;

        UserSupport::updateUser($userobj, false, false);

        self::assertSame($userobj, $GLOBALS['__middag_test_updated_user'][0]);
        self::assertFalse($GLOBALS['__middag_test_updated_user'][1]);
    }

    #[Test]
    public function testDeleteUserReturnsTheMoodleResult(): void
    {
        $user = (object) ['id' => 7, 'username' => 'jane'];

        $GLOBALS['__middag_test_delete_result'] = false;
        self::assertFalse(UserSupport::deleteUser($user));

        $GLOBALS['__middag_test_delete_result'] = true;
        self::assertTrue(UserSupport::deleteUser($user));
    }

    #[Test]
    public function testDeleteUserHonoursTheBoolContractForAnIdOnlyRecord(): void
    {
        // Moodle's delete_user() throws a coding_exception when the record
        // lacks the username property; the adapter's documented contract is
        // bool, so the guard must surface as false — not as an uncaught crash.
        $GLOBALS['__middag_test_delete_result'] = true;

        self::assertFalse(UserSupport::deleteUser((object) ['id' => 7]));
    }

    #[Test]
    public function testFullnameFromAStdClass(): void
    {
        $GLOBALS['__middag_test_fullname'] = 'Jane Doe';

        self::assertSame('Jane Doe', UserSupport::fullname((object) ['firstname' => 'Jane', 'lastname' => 'Doe']));
    }

    #[Test]
    public function testFullnameFromAUserEntityConvertsToRecordFirst(): void
    {
        $GLOBALS['__middag_test_fullname'] = 'Jane Doe';
        $user = User::fromRecord((object) ['id' => 5, 'firstname' => 'Jane', 'lastname' => 'Doe']);

        self::assertSame('Jane Doe', UserSupport::fullname($user));
    }

    #[Test]
    public function testGetCurrentUserIdReturnsTheUserId(): void
    {
        $GLOBALS['USER'] = (object) ['id' => 5];

        self::assertSame(5, UserSupport::getCurrentUserId());
    }

    #[Test]
    public function testGetCurrentUserIdReturnsZeroWhenAbsent(): void
    {
        $GLOBALS['USER'] = new stdClass();

        self::assertSame(0, UserSupport::getCurrentUserId());
    }

    #[Test]
    public function testGetCurrentReturnsTheUserEntity(): void
    {
        $GLOBALS['USER'] = (object) ['id' => 5, 'firstname' => 'Jane'];

        $user = UserSupport::getCurrent();

        self::assertInstanceOf(User::class, $user);
        self::assertSame(5, $user->getId());
    }

    #[Test]
    public function testGetCurrentReturnsNullWhenNotLoggedIn(): void
    {
        $GLOBALS['USER'] = new stdClass();

        self::assertNull(UserSupport::getCurrent());
    }

    #[Test]
    public function testFormatUserDataMergesCoreFieldsWithProfileFields(): void
    {
        $GLOBALS['__middag_test_fullname'] = 'Jane Doe';
        $GLOBALS['__middag_test_profile_fields'] = [
            new class {
                public $data = 'Engineering';

                public function get_shortname(): string
                {
                    return 'dept';
                }
            },
        ];

        $user = (object) [
            'id' => 5,
            'username' => 'jdoe',
            'firstname' => 'Jane',
            'lastname' => 'Doe',
            'email' => 'jane@example.test',
            'phone1' => '111',
            'phone2' => '222',
            'city' => 'Rio',
            'country' => 'BR',
        ];

        $data = UserSupport::formatUserData($user);

        self::assertSame(5, $data['id']);
        self::assertSame('Jane Doe', $data['fullname']);
        self::assertSame('jdoe', $data['username']);
        self::assertSame('jane@example.test', $data['email']);
        self::assertSame('Rio', $data['city']);
        self::assertSame('Engineering', $data['profile_fields']['dept']);
    }

    #[Test]
    public function testGetUserByEmailReturnsAnEntity(): void
    {
        $this->requireCoreUserMethod('get_user_by_email');

        $GLOBALS['__middag_test_user_by_email'] = (object) ['id' => 7];

        $user = UserSupport::getUserByEmail('jane@example.test');

        self::assertInstanceOf(User::class, $user);
        self::assertSame(7, $user->getId());
    }

    #[Test]
    public function testGetUserByUsernameReturnsAnEntity(): void
    {
        $this->requireCoreUserMethod('get_user_by_username');

        $GLOBALS['__middag_test_user_by_username'] = (object) ['id' => 8];

        $user = UserSupport::getUserByUsername('jdoe');

        self::assertInstanceOf(User::class, $user);
        self::assertSame(8, $user->getId());
    }

    private function requireCoreUserMethod(string $method): void
    {
        if (!method_exists('core\user', $method)) {
            self::markTestSkipped(
                sprintf('central bootstrap stub core\user::%s() not yet provided', $method),
            );
        }
    }
}
