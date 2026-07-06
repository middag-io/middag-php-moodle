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

use core\exception\moodle_exception;
use Middag\Moodle\Domain\User\UserService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * UserService delegates lifecycle operations to UserSupport, which require_once's
 * $CFG->dirroot.'/user/lib.php' before calling Moodle's user_* functions (stubbed
 * in tests/stubs/support/version-user.php). A temp dirroot with an empty stand-in
 * lib file is prepared in setUp so the require_once resolves.
 *
 * @internal
 */
#[CoversClass(UserService::class)]
final class UserServiceCoverageTest extends TestCase
{
    private string $tempDir;

    private mixed $prevCfg;

    private mixed $prevUserRecord;

    private mixed $prevConfig;

    private mixed $prevNewUserId;

    protected function setUp(): void
    {
        $this->prevCfg = $GLOBALS['CFG'] ?? null;
        $this->prevUserRecord = $GLOBALS['__middag_test_user_record'] ?? null;
        $this->prevConfig = $GLOBALS['__middag_test_config'] ?? null;
        $this->prevNewUserId = $GLOBALS['__middag_test_new_user_id'] ?? null;

        $this->tempDir = sys_get_temp_dir() . '/middag_user_service_test';
        if (!is_dir($this->tempDir . '/user')) {
            mkdir($this->tempDir . '/user', 0o777, true);
        }
        file_put_contents($this->tempDir . '/user/lib.php', "<?php\n");

        $GLOBALS['CFG'] = (object) ['dirroot' => $this->tempDir];
        $GLOBALS['__middag_test_config'] = ['mnet_localhost_id' => 1];

        unset(
            $GLOBALS['__middag_test_user_record'],
            $GLOBALS['__middag_test_new_user_id'],
            $GLOBALS['__middag_test_created_user'],
            $GLOBALS['__middag_test_updated_user'],
            $GLOBALS['__middag_test_delete_result'],
        );
    }

    protected function tearDown(): void
    {
        $GLOBALS['CFG'] = $this->prevCfg;
        $GLOBALS['__middag_test_config'] = $this->prevConfig;

        if ($this->prevUserRecord === null) {
            unset($GLOBALS['__middag_test_user_record']);
        } else {
            $GLOBALS['__middag_test_user_record'] = $this->prevUserRecord;
        }

        if ($this->prevNewUserId === null) {
            unset($GLOBALS['__middag_test_new_user_id']);
        } else {
            $GLOBALS['__middag_test_new_user_id'] = $this->prevNewUserId;
        }

        unset(
            $GLOBALS['__middag_test_created_user'],
            $GLOBALS['__middag_test_updated_user'],
            $GLOBALS['__middag_test_delete_result'],
        );
    }

    #[Test]
    public function createUserDelegatesToSupportAndReturnsNewId(): void
    {
        $GLOBALS['__middag_test_new_user_id'] = 77;

        $userobj = new stdClass();
        $userobj->username = 'newbie';
        $userobj->email = 'newbie@example.com';

        $service = new UserService();
        $newId = $service->createUser($userobj, true, false);

        self::assertSame(77, $newId);
        // The username survived delegation to Moodle's user_create_user stub.
        self::assertSame('newbie', $GLOBALS['__middag_test_created_user'][0]->username);
    }

    #[Test]
    public function updateUserThrowsWhenIdMissing(): void
    {
        $service = new UserService();

        $this->expectException(moodle_exception::class);
        $service->updateUser(new stdClass());
    }

    #[Test]
    public function updateUserDelegatesAndReturnsTrueWhenIdPresent(): void
    {
        $userobj = new stdClass();
        $userobj->id = 5;
        $userobj->email = 'changed@example.com';

        $service = new UserService();

        self::assertTrue($service->updateUser($userobj, false, false));
        self::assertSame(5, $GLOBALS['__middag_test_updated_user'][0]->id);
    }

    #[Test]
    public function deleteUserReturnsFalseWhenUserNotFound(): void
    {
        // core\user::get_user() stub returns false -> UserSupport::getUser() null.
        $GLOBALS['__middag_test_user_record'] = false;

        $service = new UserService();

        self::assertFalse($service->deleteUser(999));
    }

    #[Test]
    public function deleteUserDelegatesWhenUserFound(): void
    {
        $GLOBALS['__middag_test_user_record'] = (object) ['id' => 5, 'username' => 'victim'];
        $GLOBALS['__middag_test_delete_result'] = true;

        $service = new UserService();

        self::assertTrue($service->deleteUser(5));
    }

    #[Test]
    public function deleteUserPropagatesSupportFailure(): void
    {
        $GLOBALS['__middag_test_user_record'] = (object) ['id' => 5, 'username' => 'victim'];
        $GLOBALS['__middag_test_delete_result'] = false;

        $service = new UserService();

        self::assertFalse($service->deleteUser(5));
    }
}
