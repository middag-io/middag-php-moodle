<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Security;

use core\exception\coding_exception;
use core\exception\moodle_exception;
use core\url as moodle_url;
use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Middag\Moodle\Config\ComponentContext;
use Middag\Moodle\Domain\User\User;
use Middag\Moodle\Security\Contract\AuthServiceInterface;
use Middag\Moodle\Settings\framework_config;
use Middag\Moodle\Shared\Util\Debug;
use Middag\Moodle\Support\AuthSupport;
use Middag\Moodle\Support\ConfigSupport;
use Middag\Moodle\Support\SessionSupport;
use Middag\Moodle\Support\SettingsSupport;
use Middag\Moodle\Support\UrlSupport;
use Middag\Moodle\Support\UserSupport;
use stdClass;

/**
 * Authentication Service.
 *
 * Handles JWT-based authentication (HS256/RS256) for SSO and support access.
 *
 * @internal
 *
 * @see AuthServiceInterface
 */
class AuthService implements AuthServiceInterface
{
    public const AUTH_JWT = 'JWT';

    public const AUTH_JWT_ALGORITHM_SHA = 'HS256';

    public const AUTH_JWT_ALGORITHM_RSA = 'RS256';

    public const ACTION_MIDDAG_LOGIN = 'middag_support_login';

    public const PUBLIC_KEY = <<<EOD
        -----BEGIN PUBLIC KEY-----
        MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA1JmT7N1INatW/HOlC9Xq
        5AjbfzkHvMD6ghC8f8kxzq7uZfPmH90NajzvN3h5+jCoI2Sh6W3DBqja6n857Dkq
        4n7OGtsgRBY+J44qd2z8iZZi+kCjP+bvYUrys5XPeIM+py1RCXCrsv/XgjnQ2O9y
        OTE+gsU0R4XOrWRXrOQOLrOVBn8A5QnA9y+gg2dPE5X0kiKJwYBIY3rkYIhPjBAD
        QOh3udrkVzyrcmJyx6+MPTgE8wY0TY2eavBw/itfKUcDZaST5tUS5Q1Fsozqn4xW
        Lvsdd9jYzEvt6brYbdE2H7suXYNVUjUmfrxyyobt5OUVb7zYRBQPJ5O789J4XqGB
        JwIDAQAB
        -----END PUBLIC KEY-----
        EOD;

    /**
     * Initializes the authentication process based on the token provided in the request.
     *
     * @throws coding_exception
     */
    public static function init(): void
    {
        try {
            // PARAM_RAW is necessary because tokens contain dots and special chars
            if (!$token = optional_param('token', false, PARAM_RAW)) {
                throw new moodle_exception('tokennotfound', ComponentContext::name());
            }

            // 1. Try RSA (Support/Admin Token)
            if ($data = self::decryptJwtRsa($token)) {
                self::middagRsa($data);

                return;
            }

            // 2. Try Standard JWT (User SSO)
            if ((SettingsSupport::get(framework_config::authtype) ?? '') === self::AUTH_JWT) {
                self::authJwt($token);
            } else {
                self::redirect();
            }
        } catch (Exception $exception) {
            Debug::traceException($exception);
            self::redirect();
        }
    }

    /**
     * Redirects the user to the home page or the requested URL safely.
     *
     * @throws coding_exception
     */
    public static function redirect(): void
    {
        $wwwroot = ConfigSupport::getGlobal('wwwroot');
        $urltogo = $wwwroot;

        $wantsurl = SessionSupport::getWantsUrl();
        if (isset($wantsurl)) {
            $urltogo = $wantsurl;
            SessionSupport::unsetWantsUrl();
        } elseif ($redirect = optional_param('redirect', false, PARAM_URL)) {
            // Security: Ensure redirect is local to avoid Open Redirect vulnerabilities
            if (str_starts_with($redirect, $wwwroot) || str_starts_with($redirect, '/')) {
                $urltogo = $redirect;
            }
        }

        UrlSupport::redirect($urltogo);
    }

    /**
     * Checks if the user is already logged in.
     *
     * @throws moodle_exception if the user is already logged in
     */
    public static function authCheck(): void
    {
        if (AuthSupport::isLoggedIn() && !AuthSupport::isGuest()) {
            throw new moodle_exception('alreadyloggedin');
        }
    }

    /**
     * Generates a login URL with a JWT token based on the provided user data and expiration time.
     *
     * @param stdClass $user    the user object containing necessary details like email and ID
     * @param int      $expires the expiration time for the token in seconds (default is 60 seconds)
     *
     * @return moodle_url returns the generated login URL containing the JWT token as a query parameter
     */
    public static function generateLoginUrl(stdClass $user, int $expires = 120): moodle_url
    {
        $authsecretkey = SettingsSupport::get(framework_config::authsecretkey);

        if (empty($authsecretkey)) {
            return UrlSupport::get('login/index.php');
        }

        $payload = [
            'iat' => time(),
            'exp' => time() + $expires,
            'email' => $user->email,
            'uid' => $user->id,
            'action' => 'login',
        ];

        $token = JWT::encode(
            $payload,
            $authsecretkey,
            self::AUTH_JWT_ALGORITHM_SHA
        );

        return UrlSupport::get('/local/middag/auth.php', ['token' => $token]);
    }

    /**
     * Decode a JWT token using HS256.
     *
     * @param string $value JWT string
     * @param string $key   Secret key
     */
    protected static function decrypt(string $value, string $key): stdClass
    {
        return JWT::decode($value, new Key($key, self::AUTH_JWT_ALGORITHM_SHA));
    }

    /**
     * Decodes a JWT token using RS256 algorithm (Public Key).
     *
     * @param string $value the JWT string
     *
     * @return false|stdClass the decoded payload or false on failure
     */
    protected static function decryptJwtRsa(string $value): false|stdClass
    {
        try {
            return JWT::decode($value, new Key(self::PUBLIC_KEY, self::AUTH_JWT_ALGORITHM_RSA));
        } catch (Exception) {
            // Silent fail for RSA checks on normal login
            return false;
        }
    }

    /**
     * Handles RSA specific actions like support login.
     *
     * @param mixed $data the decoded token data
     *
     * @throws moodle_exception if the action is invalid
     */
    protected static function middagRsa(mixed $data): void
    {
        if (!isset($data->action)) {
            throw new moodle_exception('invalidtoken', ComponentContext::name());
        }

        match ($data->action) {
            self::ACTION_MIDDAG_LOGIN => self::actionMiddagRsaLogin(),
            default => throw new moodle_exception('invalidaction', ComponentContext::name()),
        };
    }

    /**
     * Execute the support login action.
     *
     * @throws moodle_exception if user support is not enabled
     */
    protected static function actionMiddagRsaLogin(): void
    {
        if (empty(SettingsSupport::get(framework_config::usersupport))) {
            throw new moodle_exception('middagloginnotenabled', ComponentContext::name());
        }

        $user = AuthSupport::getAdmin();
        if (!$user instanceof stdClass) {
            throw new moodle_exception('cannotfindadmin', ComponentContext::name());
        }

        self::performSafeLogin($user);
    }

    /**
     * Authenticate a standard user via JWT.
     *
     * @param mixed $token JWT token payload
     *
     * @throws moodle_exception for validation failures (key, token, email, user)
     */
    protected static function loginUser(mixed $token): void
    {
        self::authCheck();

        $authsecretkey = SettingsSupport::get(framework_config::authsecretkey);

        if (empty($authsecretkey)) {
            throw new moodle_exception('secretkeynotfound', ComponentContext::name());
        }

        // Validate Token
        try {
            $data = self::decrypt((string) $token, $authsecretkey);
        } catch (Exception) {
            throw new moodle_exception('invalidtoken', ComponentContext::name());
        }

        // Default to the 'email' payload field when the setting is unset.
        // SettingsSupport::get() returns false (not null) for an absent config
        // key, so `?:` (not `??`) is required for the default to apply.
        $var = SettingsSupport::get(framework_config::authvarname) ?: 'email';
        $value = $data->{$var} ?? null;

        if (!$value) {
            throw new moodle_exception('invalidtokenpayload', ComponentContext::name());
        }

        if ((SettingsSupport::get(framework_config::authprofilefield) ?: 'email') === 'email' && !validate_email($value)) {
            throw new moodle_exception('invalidemail', ComponentContext::name());
        }

        // Find user
        if (!($user = UserSupport::getUserByEmail($value, '*', null, IGNORE_MULTIPLE)) instanceof User) {
            throw new moodle_exception('usernotfound', ComponentContext::name());
        }

        // Destroy previous sessions if not testing
        if (!defined('PHPUNIT_TEST') || !PHPUNIT_TEST) {
            SessionSupport::destroyUserSessions($user->id, SessionSupport::getId());
        }

        self::performSafeLogin($user->toRecord());
    }

    /**
     * Performs the login process safely.
     *
     * If running in a PHPUnit environment, it sets the global user without regenerating
     * the session ID (which causes errors in CLI) and avoids the redirect (which kills the test).
     * In production, it performs the standard complete_user_login and redirect.
     *
     * @param stdClass $user the Moodle user object
     *
     * @throws moodle_exception
     */
    protected static function performSafeLogin(stdClass $user): void
    {
        // Handle PHPUnit environment.
        if (defined('PHPUNIT_TEST') && PHPUNIT_TEST) {
            SessionSupport::setUser($user);

            // Throw exception to stop execution flow during tests (simulate redirect stop)
            throw new moodle_exception('redirecterrordetected');
        }

        // Handle Production environment.
        $login = AuthSupport::completeUserLogin($user);
        if ($login) {
            self::redirect();
        }
    }

    /**
     * Wrapper to handle JWT authentication logic.
     *
     * @throws moodle_exception
     */
    protected static function authJwt(mixed $token): void
    {
        self::loginUser($token);
    }
}
