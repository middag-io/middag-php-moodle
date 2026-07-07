<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Security;

use core\exception\moodle_exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Middag\Moodle\Security\AuthService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use stdClass;

/**
 * AuthService performs JWT-based SSO/support authentication on top of the
 * Support boundary wrappers (Settings/Config/Session/Auth/User/Url). The
 * bootstrap + support stubs stand in for the Moodle globals those wrappers
 * call, and their returns are driven via $GLOBALS['__middag_test_*'], so every
 * branch is exercised without a Moodle runtime. Protected static members are
 * invoked through reflection; the PHPUnit-specific branch of performSafeLogin
 * (guarded by the process-global PHPUNIT_TEST constant, which this suite leaves
 * undefined) is covered in an isolated process that defines the constant.
 *
 * @internal
 */
#[CoversClass(AuthService::class)]
final class AuthServiceCoverageTest extends TestCase
{
    private const WWWROOT = 'https://moodle.example.test';

    // HS256 requires a key of at least 256 bits (32 bytes); this literal is 40 bytes.
    private const SECRET = '0123456789abcdefghij0123456789abcdefghij';

    /** @var list<string> */
    private const TEST_KEYS = [
        '__middag_test_params',
        '__middag_test_config',
        '__middag_test_isloggedin',
        '__middag_test_isguest',
        '__middag_test_user_by_email',
        '__middag_test_complete_login',
        '__middag_test_admin',
        '__middag_test_redirect',
        '__middag_test_destroyed_sessions',
        '__middag_test_session_user',
    ];

    private mixed $prevCfg;

    private mixed $prevSession;

    protected function setUp(): void
    {
        $this->prevCfg = $GLOBALS['CFG'] ?? null;
        $this->prevSession = $GLOBALS['SESSION'] ?? null;

        foreach (self::TEST_KEYS as $key) {
            unset($GLOBALS[$key]);
        }

        // Baseline: not logged in (so authCheck passes), fresh CFG + SESSION.
        $GLOBALS['CFG'] = (object) ['wwwroot' => self::WWWROOT];
        $GLOBALS['SESSION'] = new stdClass();
        $GLOBALS['__middag_test_params'] = [];
        $GLOBALS['__middag_test_config'] = [];
        $GLOBALS['__middag_test_isloggedin'] = false;
        $GLOBALS['__middag_test_isguest'] = false;
    }

    protected function tearDown(): void
    {
        foreach (self::TEST_KEYS as $key) {
            unset($GLOBALS[$key]);
        }

        $GLOBALS['CFG'] = $this->prevCfg;
        $GLOBALS['SESSION'] = $this->prevSession;
    }

    // ---------------------------------------------------------------------
    // init()
    // ---------------------------------------------------------------------

    #[Test]
    public function testInitRedirectsWhenTokenIsMissing(): void
    {
        // No 'token' request param → optional_param() returns false → the
        // tokennotfound moodle_exception is thrown and swallowed by init()'s
        // catch, which falls through to redirect().
        AuthService::init();

        self::assertSame(self::WWWROOT, $this->redirectTarget());
    }

    #[Test]
    public function testInitRedirectsWhenRsaFailsAndAuthTypeIsNotJwt(): void
    {
        // A non-RSA token: decryptJwtRsa() fails (returns false) and authtype is
        // unset, so the JWT branch is skipped and init() redirects.
        $GLOBALS['__middag_test_params']['token'] = 'not.a.real.jwt';

        AuthService::init();

        self::assertSame(self::WWWROOT, $this->redirectTarget());
    }

    #[Test]
    public function testInitAuthenticatesViaJwtAndCompletesLogin(): void
    {
        $this->setConfig('authtype', 'JWT');
        $this->setConfig('authsecretkey', self::SECRET);
        $this->setConfig('authvarname', 'email');
        $this->setConfig('authprofilefield', 'email');
        $GLOBALS['__middag_test_user_by_email'] = (object) ['id' => 42, 'email' => 'user@ex.com', 'username' => 'u42'];
        $GLOBALS['__middag_test_complete_login'] = true;
        $GLOBALS['__middag_test_params']['token'] = $this->makeToken(['email' => 'user@ex.com', 'uid' => 42]);

        AuthService::init();

        // Reached the user lookup + session destroy, then completed login and redirected.
        self::assertSame([42, session_id()], $GLOBALS['__middag_test_destroyed_sessions'] ?? null);
        self::assertSame(self::WWWROOT, $this->redirectTarget());
    }

    // ---------------------------------------------------------------------
    // redirect()
    // ---------------------------------------------------------------------

    #[Test]
    public function testRedirectUsesWantsUrlAndClearsIt(): void
    {
        $GLOBALS['SESSION']->wantsurl = '/course/view.php?id=3';

        AuthService::redirect();

        self::assertSame('/course/view.php?id=3', $this->redirectTarget());
        self::assertFalse(isset($GLOBALS['SESSION']->wantsurl));
    }

    #[Test]
    public function testRedirectHonorsLocalRelativeRedirectParam(): void
    {
        $GLOBALS['__middag_test_params']['redirect'] = '/local/middag/page.php';

        AuthService::redirect();

        self::assertSame('/local/middag/page.php', $this->redirectTarget());
    }

    #[Test]
    public function testRedirectHonorsRedirectParamWithinWwwroot(): void
    {
        $GLOBALS['__middag_test_params']['redirect'] = self::WWWROOT . '/my/';

        AuthService::redirect();

        self::assertSame(self::WWWROOT . '/my/', $this->redirectTarget());
    }

    #[Test]
    public function testRedirectRejectsExternalRedirectParam(): void
    {
        // Open-redirect guard: an off-site URL is ignored, falling back to wwwroot.
        $GLOBALS['__middag_test_params']['redirect'] = 'https://evil.test/phish';

        AuthService::redirect();

        self::assertSame(self::WWWROOT, $this->redirectTarget());
    }

    #[Test]
    public function testRedirectFallsBackToWwwrootWithoutHints(): void
    {
        AuthService::redirect();

        self::assertSame(self::WWWROOT, $this->redirectTarget());
    }

    // ---------------------------------------------------------------------
    // authCheck()
    // ---------------------------------------------------------------------

    #[Test]
    public function testAuthCheckThrowsWhenAlreadyLoggedIn(): void
    {
        $GLOBALS['__middag_test_isloggedin'] = true;
        $GLOBALS['__middag_test_isguest'] = false;

        $this->assertThrowsWithCode('alreadyloggedin', static fn () => AuthService::authCheck());
    }

    // ---------------------------------------------------------------------
    // generateLoginUrl()
    // ---------------------------------------------------------------------

    #[Test]
    public function testGenerateLoginUrlReturnsPlainLoginWhenSecretMissing(): void
    {
        $url = AuthService::generateLoginUrl((object) ['email' => 'a@b.com', 'id' => 1]);

        self::assertSame('login/index.php', (string) $url);
    }

    #[Test]
    public function testGenerateLoginUrlEmbedsSignedToken(): void
    {
        $this->setConfig('authsecretkey', self::SECRET);

        $url = AuthService::generateLoginUrl((object) ['email' => 'user@ex.com', 'id' => 42], 300);

        self::assertSame('/local/example/auth.php', (string) $url);

        $token = $url->params['token'] ?? null;
        self::assertIsString($token);

        $decoded = JWT::decode($token, new Key(self::SECRET, 'HS256'));
        self::assertSame('user@ex.com', $decoded->email);
        self::assertSame(42, $decoded->uid);
        self::assertSame('login', $decoded->action);
        self::assertSame(300, $decoded->exp - $decoded->iat);
    }

    // ---------------------------------------------------------------------
    // decrypt() / decryptJwtRsa()
    // ---------------------------------------------------------------------

    #[Test]
    public function testDecryptDecodesHs256Payload(): void
    {
        $token = $this->makeToken(['email' => 'who@ex.com']);

        $decoded = $this->callProtected('decrypt', $token, self::SECRET);

        self::assertInstanceOf(stdClass::class, $decoded);
        self::assertSame('who@ex.com', $decoded->email);
    }

    #[Test]
    public function testDecryptJwtRsaReturnsFalseOnInvalidToken(): void
    {
        // No token signed by the private key matching PUBLIC_KEY is available,
        // so RS256 decoding always throws and is swallowed into false.
        self::assertFalse($this->callProtected('decryptJwtRsa', 'garbage.token.value'));
    }

    // ---------------------------------------------------------------------
    // middagRsa()
    // ---------------------------------------------------------------------

    #[Test]
    public function testMiddagRsaThrowsWhenActionMissing(): void
    {
        $this->assertThrowsWithCode('invalidtoken', fn (): mixed => $this->callProtected('middagRsa', new stdClass()));
    }

    #[Test]
    public function testMiddagRsaThrowsOnUnknownAction(): void
    {
        $data = (object) ['action' => 'somethingelse'];

        $this->assertThrowsWithCode('invalidaction', fn (): mixed => $this->callProtected('middagRsa', $data));
    }

    #[Test]
    public function testMiddagRsaSupportLoginDispatchesAndRedirects(): void
    {
        $this->setConfig('usersupport', 1);
        $GLOBALS['__middag_test_admin'] = (object) ['id' => 2, 'email' => 'admin@ex.com'];
        $GLOBALS['__middag_test_complete_login'] = true;

        $data = (object) ['action' => AuthService::ACTION_MIDDAG_LOGIN];
        $this->callProtected('middagRsa', $data);

        self::assertSame(self::WWWROOT, $this->redirectTarget());
    }

    // ---------------------------------------------------------------------
    // actionMiddagRsaLogin()
    // ---------------------------------------------------------------------

    #[Test]
    public function testActionMiddagRsaLoginThrowsWhenSupportDisabled(): void
    {
        // usersupport unset → empty() → throws.
        $this->assertThrowsWithCode('middagloginnotenabled', fn (): mixed => $this->callProtected('actionMiddagRsaLogin'));
    }

    #[Test]
    public function testActionMiddagRsaLoginThrowsWhenAdminMissing(): void
    {
        $this->setConfig('usersupport', 1);
        $GLOBALS['__middag_test_admin'] = null;

        $this->assertThrowsWithCode('cannotfindadmin', fn (): mixed => $this->callProtected('actionMiddagRsaLogin'));
    }

    // ---------------------------------------------------------------------
    // loginUser()
    // ---------------------------------------------------------------------

    #[Test]
    public function testLoginUserThrowsWhenSecretMissing(): void
    {
        $this->assertThrowsWithCode('secretkeynotfound', fn (): mixed => $this->callProtected('loginUser', 'any-token'));
    }

    #[Test]
    public function testLoginUserThrowsOnUndecodableToken(): void
    {
        $this->setConfig('authsecretkey', self::SECRET);

        $this->assertThrowsWithCode('invalidtoken', fn (): mixed => $this->callProtected('loginUser', 'garbage'));
    }

    #[Test]
    public function testLoginUserThrowsOnEmptyPayloadValue(): void
    {
        $this->setConfig('authsecretkey', self::SECRET);
        $this->setConfig('authvarname', 'email');
        // Token decodes fine but carries no 'email' claim → payload value is null.
        $token = $this->makeToken(['action' => 'login']);

        $this->assertThrowsWithCode('invalidtokenpayload', fn (): mixed => $this->callProtected('loginUser', $token));
    }

    #[Test]
    public function testLoginUserThrowsOnInvalidEmail(): void
    {
        $this->setConfig('authsecretkey', self::SECRET);
        $this->setConfig('authvarname', 'email');
        $this->setConfig('authprofilefield', 'email');
        $token = $this->makeToken(['email' => 'not-an-email']);

        $this->assertThrowsWithCode('invalidemail', fn (): mixed => $this->callProtected('loginUser', $token));
    }

    #[Test]
    public function testLoginUserThrowsWhenUserNotFound(): void
    {
        $this->setConfig('authsecretkey', self::SECRET);
        $this->setConfig('authvarname', 'email');
        $this->setConfig('authprofilefield', 'email');
        // Valid email passes validation; user lookup returns nothing.
        $GLOBALS['__middag_test_user_by_email'] = false;
        $token = $this->makeToken(['email' => 'ghost@ex.com']);

        $this->assertThrowsWithCode('usernotfound', fn (): mixed => $this->callProtected('loginUser', $token));
    }

    // ---------------------------------------------------------------------
    // performSafeLogin() — production branch (PHPUNIT_TEST undefined)
    // ---------------------------------------------------------------------

    #[Test]
    public function testPerformSafeLoginDoesNotRedirectWhenLoginFails(): void
    {
        $GLOBALS['__middag_test_complete_login'] = false;

        $this->callProtected('performSafeLogin', (object) ['id' => 9, 'email' => 'x@ex.com']);

        self::assertArrayNotHasKey('__middag_test_redirect', $GLOBALS);
    }

    // ---------------------------------------------------------------------
    // performSafeLogin() — PHPUnit branch (PHPUNIT_TEST defined true)
    // ---------------------------------------------------------------------

    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testPerformSafeLoginUnderPhpunitSetsUserAndThrows(): void
    {
        // The PHPUnit-only branch is guarded by the process-global PHPUNIT_TEST
        // constant, which the main suite leaves undefined; define it here in an
        // isolated process so the branch is honestly reachable.
        define('PHPUNIT_TEST', true);

        $record = (object) ['id' => 77, 'email' => 'iso@ex.com'];

        try {
            $this->callProtected('performSafeLogin', $record);
            self::fail('Expected moodle_exception to stop the flow.');
        } catch (moodle_exception $moodleexception) {
            self::assertSame('redirecterrordetected', $moodleexception->getMessage());
        }

        self::assertSame($record, $GLOBALS['__middag_test_session_user'] ?? null);
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    private function setConfig(string $key, mixed $value): void
    {
        $GLOBALS['__middag_test_config']['mdg_core_' . $key] = $value;
    }

    /**
     * @param array<string, mixed> $claims
     */
    private function makeToken(array $claims): string
    {
        return JWT::encode($claims + ['iat' => time(), 'exp' => time() + 3600], self::SECRET, 'HS256');
    }

    private function redirectTarget(): mixed
    {
        $redirect = $GLOBALS['__middag_test_redirect'] ?? null;
        self::assertIsArray($redirect, 'redirect() was not invoked');

        return (string) $redirect[0];
    }

    private function callProtected(string $method, mixed ...$args): mixed
    {
        $reflection = new ReflectionMethod(AuthService::class, $method);

        return $reflection->invoke(null, ...$args);
    }

    private function assertThrowsWithCode(string $expectedCode, callable $fn): void
    {
        try {
            $fn();
        } catch (moodle_exception $moodleexception) {
            self::assertSame($expectedCode, $moodleexception->getMessage());

            return;
        }

        self::fail('Expected moodle_exception with code "' . $expectedCode . '".');
    }
}
