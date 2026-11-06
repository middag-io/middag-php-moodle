<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Http\Controller;

use Middag\Framework\Exception\MiddagAuthorizationException;
use Middag\Moodle\Http\Controller\AbstractApiController;
use Middag\Moodle\Http\Controller\AbstractController;
use Middag\Moodle\Security\Contract\AuthenticationInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionProperty;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;

/**
 * The CSRF gate: sesskey verification ON by default for non-idempotent
 * requests, with one test per documented exemption.
 *
 * The gate runs from preHandle(), not from handle(): the kernel always calls
 * preHandle() before the action, while handle() is lazy on page controllers and
 * is never reached by inertiaRedirect() — the return of every Inertia mutation.
 * Each test therefore drives preHandle() directly, which is exactly what the
 * kernel does (HttpKernel::dispatch, step 3).
 *
 * The valid token is the sesskey the test bootstrap's sesskey() stub returns,
 * and the fake AuthenticationInterface compares against it the same way the
 * real adapter does.
 *
 * @internal
 */
#[CoversClass(AbstractController::class)]
#[CoversClass(AbstractApiController::class)]
final class CsrfGateTest extends TestCase
{
    /** The sesskey the test bootstrap's stub returns; public so the anonymous doubles can read it. */
    public const VALID = 'sesskey000';

    // =========================================================================
    // Page controllers (Inertia / HTML)
    // =========================================================================

    #[Test]
    public function sessionPostWithoutTokenIsRejectedWithForbidden(): void
    {
        $controller = $this->pageController($this->request('POST'));

        // Message comes from LangSupport::getString('invalidsesskey', 'error');
        // the bootstrap get_string() stub echoes "[component/identifier]".
        $this->expectException(MiddagAuthorizationException::class);
        $this->expectExceptionMessage('[error/invalidsesskey]');
        $controller->preHandle();
    }

    #[Test]
    public function invalidTokenOnPageControllerIsForbiddenNotServerError(): void
    {
        $controller = $this->pageController(
            $this->request('POST', headers: ['HTTP_X_CSRF_TOKEN' => 'wrong']),
        );

        // Regression: the old path called require_sesskey() and let a raw
        // moodle_exception escape, which the kernel maps to 500.
        $this->expectException(MiddagAuthorizationException::class);
        $controller->preHandle();
    }

    #[Test]
    public function headerTokenSatisfiesTheGate(): void
    {
        $controller = $this->pageController(
            $this->request('POST', headers: ['HTTP_X_CSRF_TOKEN' => self::VALID]),
        );

        $controller->preHandle();

        self::assertSame([self::VALID], $controller->auth->confirmSesskeyTokens);
    }

    #[Test]
    public function xsrfCookieHeaderEchoedByAxiosSatisfiesTheGate(): void
    {
        $controller = $this->pageController(
            $this->request('POST', headers: ['HTTP_X_XSRF_TOKEN' => self::VALID]),
        );

        $controller->preHandle();

        self::assertSame([self::VALID], $controller->auth->confirmSesskeyTokens);
    }

    #[Test]
    public function sesskeyFieldInTheBodySatisfiesTheGate(): void
    {
        // The formslib transport: every moodleform posts a hidden sesskey.
        $controller = $this->pageController(
            $this->request('POST', body: ['sesskey' => self::VALID]),
        );

        $controller->preHandle();

        self::assertSame([self::VALID], $controller->auth->confirmSesskeyTokens);
    }

    #[Test]
    public function idempotentMethodsAreNeverChecked(): void
    {
        foreach (['GET', 'HEAD', 'OPTIONS'] as $method) {
            $controller = $this->pageController($this->request($method));
            $controller->preHandle();

            self::assertSame([], $controller->auth->confirmSesskeyTokens, $method);
        }
    }

    #[Test]
    public function routeOptOutSkipsTheGate(): void
    {
        // What #[Sesskey(require: false)] reaches through the kernel.
        $controller = $this->pageController($this->request('POST'));
        $controller->setRequireSesskey(false);

        $controller->preHandle();

        self::assertSame([], $controller->auth->confirmSesskeyTokens);
    }

    #[Test]
    public function callerWithoutASessionIsNotExposedToCsrf(): void
    {
        $controller = $this->pageController($this->request('POST'));
        $controller->auth->loggedIn = false;

        $controller->preHandle();

        self::assertSame([], $controller->auth->confirmSesskeyTokens);
    }

    // =========================================================================
    // API controllers
    // =========================================================================

    #[Test]
    public function apiSessionPostWithoutTokenIsRejected(): void
    {
        $controller = $this->apiController($this->request('POST'));

        $this->expectException(MiddagAuthorizationException::class);
        $controller->preHandle();
    }

    #[Test]
    public function publicRouteIsExemptWithoutToken(): void
    {
        // requiresAuthentication() === false — webhook receivers (HMAC/shared
        // secret) and the platform token endpoint.
        $controller = $this->apiController($this->request('POST'), public: true);

        $controller->preHandle();

        self::assertSame([], $controller->auth->confirmSesskeyTokens);
    }

    #[Test]
    public function wstokenAuthenticatedRequestIsExemptWithoutToken(): void
    {
        $controller = $this->apiController($this->request('POST'));
        $controller->markAuthenticatedByToken();

        $controller->preHandle();

        self::assertSame([], $controller->auth->confirmSesskeyTokens);
    }

    #[Test]
    public function bearerHeaderAloneDoesNotExemptASessionRequest(): void
    {
        // Regression: the old gate exempted anything carrying an Authorization
        // header (isWsRequest()), so a session request with any junk Bearer
        // skipped CSRF entirely — which is what every HÉLICO admin SPA call
        // sends.
        $controller = $this->apiController(
            $this->request('POST', headers: ['HTTP_AUTHORIZATION' => 'Bearer junk']),
        );

        $this->expectException(MiddagAuthorizationException::class);
        $controller->preHandle();
    }

    // =========================================================================
    // Fixtures
    // =========================================================================

    /**
     * @param array<string, string> $headers
     * @param array<string, string> $body
     */
    private function request(string $method, array $headers = [], array $body = []): Request
    {
        return new Request(
            request: $body,
            server: array_merge(['REQUEST_METHOD' => $method], $headers),
        );
    }

    private function pageController(Request $request): object
    {
        $controller = new class extends AbstractController {
            public object $auth;

            public function setAuth(object $auth): void
            {
                $this->auth = $auth;
            }

            /** Page setup needs a Moodle runtime; the gate does not. */
            public function handle(): void
            {
                $this->handled = true;
            }
        };

        return $this->wire($controller, $request);
    }

    private function apiController(Request $request, bool $public = false): object
    {
        $controller = new class extends AbstractApiController {
            public object $auth;

            public bool $isPublic = false;

            public function setAuth(object $auth): void
            {
                $this->auth = $auth;
            }

            public function markAuthenticatedByToken(): void
            {
                // Stands in for authenticateViaWstoken(), which needs a DB.
                $reflection = new ReflectionProperty(AbstractApiController::class, 'authenticatedByToken');
                $reflection->setValue($this, true);
            }

            public function handle(): void
            {
                $this->handled = true;
            }

            protected function requiresAuthentication(): bool
            {
                return !$this->isPublic;
            }

            protected function authenticateApiRequest(): void
            {
                // Session is already established in these fixtures.
            }
        };

        $controller->isPublic = $public;

        return $this->wire($controller, $request);
    }

    private function wire(object $controller, Request $request): object
    {
        $auth = $this->makeAuth();
        $controller->setAuth($auth);
        $controller->setContainer($this->makeContainer([AuthenticationInterface::class => $auth]));
        $controller->setRequest($request);

        return $controller;
    }

    private function makeAuth(): object
    {
        return new class implements AuthenticationInterface {
            public bool $loggedIn = true;

            /** @var list<string> */
            public array $confirmSesskeyTokens = [];

            public function requireLogin(?int $courseid = null, bool $autologinguest = true): void {}

            public function isLoggedIn(): bool
            {
                return $this->loggedIn;
            }

            public function isGuest(): bool
            {
                return false;
            }

            public function requireSesskey(): void {}

            public function confirmSesskey(string $token): bool
            {
                $this->confirmSesskeyTokens[] = $token;

                return $token === CsrfGateTest::VALID;
            }
        };
    }

    /**
     * @param array<string, mixed> $services
     */
    private function makeContainer(array $services): ContainerInterface
    {
        return new class($services) implements ContainerInterface {
            /** @param array<string, mixed> $services */
            public function __construct(private array $services) {}

            public function get(string $id): mixed
            {
                if (!array_key_exists($id, $this->services)) {
                    throw new class('not found') extends RuntimeException implements NotFoundExceptionInterface {};
                }

                return $this->services[$id];
            }

            public function has(string $id): bool
            {
                return array_key_exists($id, $this->services);
            }
        };
    }
}
