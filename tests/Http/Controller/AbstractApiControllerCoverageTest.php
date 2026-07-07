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

use Middag\Framework\Exception\MiddagAuthenticationException;
use Middag\Framework\Exception\MiddagAuthorizationException;
use Middag\Moodle\Http\Controller\AbstractApiController;
use Middag\Moodle\Security\Contract\AuthenticationInterface;
use moodle_database;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use RuntimeException;
use stdClass;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Coverage for the API base controller.
 *
 * The abstract controller is exercised through an anonymous concrete subclass
 * that exposes the protected methods and overrides the parent lifecycle hook
 * handle() (owned and covered by AbstractController) with a recording stand-in,
 * so the API layer's own logic — dual auth, JSON envelope, error helpers — is
 * observed in isolation without a Moodle page-setup runtime.
 *
 * Auth is driven through a fake AuthenticationInterface resolved from a tiny
 * PSR-11 container; wstoken validation is driven through a recording $DB double.
 *
 * @internal
 */
#[CoversClass(AbstractApiController::class)]
final class AbstractApiControllerCoverageTest extends TestCase
{
    private mixed $prevDb;

    protected function setUp(): void
    {
        $this->prevDb = $GLOBALS['DB'] ?? null;
    }

    protected function tearDown(): void
    {
        $GLOBALS['DB'] = $this->prevDb;
        unset(
            $GLOBALS['__middag_test_config'],
            $GLOBALS['__middag_test_session_user'],
        );
    }

    // =========================================================================
    // disableAuthentication / requiresAuthentication
    // =========================================================================

    #[Test]
    public function testRequiresAuthenticationByDefault(): void
    {
        $controller = $this->makeController($this->request(), $this->makeContainer([]));

        self::assertTrue($controller->exposeRequiresAuthentication());
    }

    #[Test]
    public function testDisableAuthenticationMakesRoutePublic(): void
    {
        $controller = $this->makeController($this->request(), $this->makeContainer([]));
        $controller->disableAuthentication();

        self::assertFalse($controller->exposeRequiresAuthentication());
    }

    // =========================================================================
    // preHandle
    // =========================================================================

    #[Test]
    public function testPreHandleRunsAuthPipelineWhenAuthenticationRequired(): void
    {
        $auth = $this->makeAuth();
        $auth->loggedIn = true; // short-circuits authenticateApiRequest

        $controller = $this->makeController(
            $this->request(),
            $this->makeContainer([AuthenticationInterface::class => $auth]),
        );

        $controller->preHandle();

        self::assertSame(1, $controller->handleCalls);
    }

    #[Test]
    public function testPreHandleSkipsAuthWhenRoutePublic(): void
    {
        $controller = $this->makeController($this->request(), $this->makeContainer([]));
        $controller->disableAuthentication();

        $controller->preHandle();

        // handle() still runs, but authenticateApiRequest was never reached
        // (no AuthenticationInterface in the container, so it would have thrown).
        self::assertSame(1, $controller->handleCalls);
    }

    // =========================================================================
    // isWsRequest
    // =========================================================================

    #[Test]
    public function testIsWsRequestFalseWithoutTokenOrHeader(): void
    {
        $controller = $this->makeController($this->request(), $this->makeContainer([]));

        self::assertFalse($controller->isWsRequest());
    }

    #[Test]
    public function testIsWsRequestTrueWithBearerHeader(): void
    {
        $controller = $this->makeController(
            $this->request(['HTTP_AUTHORIZATION' => 'Bearer abc123']),
            $this->makeContainer([]),
        );

        self::assertTrue($controller->isWsRequest());
    }

    #[Test]
    public function testIsWsRequestTrueWithWstokenQuery(): void
    {
        $controller = $this->makeController(
            $this->request([], ['wstoken' => 'qtok']),
            $this->makeContainer([]),
        );

        self::assertTrue($controller->isWsRequest());
    }

    // =========================================================================
    // isJson
    // =========================================================================

    #[Test]
    public function testIsJsonAlwaysTrue(): void
    {
        $controller = $this->makeController($this->request(), $this->makeContainer([]));

        self::assertTrue($controller->exposeIsJson());
    }

    // =========================================================================
    // requireLogin (API override)
    // =========================================================================

    #[Test]
    public function testRequireLoginNoopWhenNoFlagsSet(): void
    {
        $auth = $this->makeAuth();
        $controller = $this->makeController(
            $this->request(),
            $this->makeContainer([AuthenticationInterface::class => $auth]),
        );

        $controller->exposeRequireLogin();

        self::assertSame(0, $auth->requireLoginCalls);
        self::assertSame(0, $auth->requireSesskeyCalls);
    }

    #[Test]
    public function testRequireLoginPassesWhenLoggedInAndNotGuest(): void
    {
        $auth = $this->makeAuth();
        $auth->loggedIn = true;

        $controller = $this->makeController(
            $this->request(),
            $this->makeContainer([AuthenticationInterface::class => $auth]),
        );
        $controller->setRequireLogin();

        $controller->exposeRequireLogin();

        // No exception thrown: reached the requiredLogin = true line.
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function testRequireLoginThrowsWhenNotLoggedIn(): void
    {
        $auth = $this->makeAuth();
        $auth->loggedIn = false;

        $controller = $this->makeController(
            $this->request(),
            $this->makeContainer([AuthenticationInterface::class => $auth]),
        );
        $controller->setRequireLogin();

        $this->expectException(MiddagAuthenticationException::class);
        $controller->exposeRequireLogin();
    }

    #[Test]
    public function testRequireLoginThrowsWhenGuest(): void
    {
        $auth = $this->makeAuth();
        $auth->loggedIn = true;
        $auth->guest = true;

        $controller = $this->makeController(
            $this->request(),
            $this->makeContainer([AuthenticationInterface::class => $auth]),
        );
        $controller->setRequireLogin();

        $this->expectException(MiddagAuthenticationException::class);
        $controller->exposeRequireLogin();
    }

    #[Test]
    public function testRequireSesskeyEnforcedOnPostRequests(): void
    {
        $auth = $this->makeAuth();
        $auth->loggedIn = true;

        $controller = $this->makeController(
            $this->request(['REQUEST_METHOD' => 'POST']),
            $this->makeContainer([AuthenticationInterface::class => $auth]),
        );
        $controller->setRequireLogin();
        $controller->setRequireSesskey();

        $controller->exposeRequireLogin();

        self::assertSame(1, $auth->requireSesskeyCalls);
    }

    #[Test]
    public function testRequireSesskeySkippedOnGetRequests(): void
    {
        $auth = $this->makeAuth();
        $auth->loggedIn = true;

        $controller = $this->makeController(
            $this->request(['REQUEST_METHOD' => 'GET']),
            $this->makeContainer([AuthenticationInterface::class => $auth]),
        );
        $controller->setRequireLogin();
        $controller->setRequireSesskey();

        $controller->exposeRequireLogin();

        self::assertSame(0, $auth->requireSesskeyCalls);
    }

    #[Test]
    public function testRequireSesskeySkippedForWebserviceRequest(): void
    {
        $auth = $this->makeAuth();
        $auth->loggedIn = true;

        $controller = $this->makeController(
            $this->request(['REQUEST_METHOD' => 'POST'], ['wstoken' => 'tok']),
            $this->makeContainer([AuthenticationInterface::class => $auth]),
        );
        $controller->setRequireLogin();
        $controller->setRequireSesskey();

        $controller->exposeRequireLogin();

        self::assertSame(0, $auth->requireSesskeyCalls);
    }

    #[Test]
    public function testRequireSesskeyThrowsAuthorizationOnInvalidKey(): void
    {
        $auth = $this->makeAuth();
        $auth->loggedIn = true;
        $auth->sesskeyThrows = true;

        $controller = $this->makeController(
            $this->request(['REQUEST_METHOD' => 'POST']),
            $this->makeContainer([AuthenticationInterface::class => $auth]),
        );
        $controller->setRequireLogin();
        $controller->setRequireSesskey();

        // The invalid-sesskey path resolves its message via
        // LangSupport::getString('invalidsesskey', 'error') — the bootstrap
        // get_string() stub echoes the deterministic "[component/identifier]"
        // marker, so the observable message is "[error/invalidsesskey]".
        $this->expectException(MiddagAuthorizationException::class);
        $this->expectExceptionMessage('[error/invalidsesskey]');
        $controller->exposeRequireLogin();
    }

    // =========================================================================
    // authenticateApiRequest
    // =========================================================================

    #[Test]
    public function testAuthenticateApiRequestReturnsEarlyWhenAlreadyLoggedIn(): void
    {
        $auth = $this->makeAuth();
        $auth->loggedIn = true;

        $controller = $this->makeController(
            $this->request(),
            $this->makeContainer([AuthenticationInterface::class => $auth]),
        );

        $controller->exposeAuthenticateApiRequest();

        self::assertFalse($controller->isRequireLoginFlag());
    }

    #[Test]
    public function testAuthenticateApiRequestUsesWstokenWhenPresent(): void
    {
        $this->installDb([
            'external_tokens' => (object) ['token' => 'tok', 'validuntil' => 0, 'userid' => 7],
            'user' => (object) ['id' => 7, 'deleted' => 0, 'suspended' => 0],
        ]);
        $controller = $this->makeController(
            $this->request([], ['wstoken' => 'tok']),
            $this->makeContainer([AuthenticationInterface::class => $this->makeAuth()]),
        );

        $controller->exposeAuthenticateApiRequest();

        self::assertSame(7, $GLOBALS['__middag_test_session_user']->id);
        // Wstoken path returned early: session fallback flags never set.
        self::assertFalse($controller->isRequireLoginFlag());
    }

    #[Test]
    public function testAuthenticateApiRequestFallsBackToSessionOnGet(): void
    {
        $controller = $this->makeController(
            $this->request(['REQUEST_METHOD' => 'GET']),
            $this->makeContainer([AuthenticationInterface::class => $this->makeAuth()]),
        );

        $controller->exposeAuthenticateApiRequest();

        self::assertTrue($controller->isRequireLoginFlag());
        self::assertFalse($controller->isRequireSesskeyFlag());
    }

    #[Test]
    public function testAuthenticateApiRequestFallsBackToSessionWithSesskeyOnPost(): void
    {
        $controller = $this->makeController(
            $this->request(['REQUEST_METHOD' => 'POST']),
            $this->makeContainer([AuthenticationInterface::class => $this->makeAuth()]),
        );

        $controller->exposeAuthenticateApiRequest();

        self::assertTrue($controller->isRequireLoginFlag());
        self::assertTrue($controller->isRequireSesskeyFlag());
    }

    // =========================================================================
    // resolveApiToken / extractBearerToken
    // =========================================================================

    #[Test]
    public function testResolveApiTokenPrefersWstokenQuery(): void
    {
        $controller = $this->makeController(
            $this->request([], ['wstoken' => 'from-query']),
            $this->makeContainer([]),
        );

        self::assertSame('from-query', $controller->exposeResolveApiToken());
    }

    #[Test]
    public function testResolveApiTokenFallsBackToBearerHeader(): void
    {
        $controller = $this->makeController(
            $this->request(['HTTP_AUTHORIZATION' => 'Bearer header-tok']),
            $this->makeContainer([]),
        );

        self::assertSame('header-tok', $controller->exposeResolveApiToken());
    }

    #[Test]
    public function testResolveApiTokenReturnsNullWhenNothingProvided(): void
    {
        $controller = $this->makeController($this->request(), $this->makeContainer([]));

        self::assertNull($controller->exposeResolveApiToken());
    }

    #[Test]
    public function testExtractBearerTokenReturnsNullWithoutHeader(): void
    {
        $controller = $this->makeController($this->request(), $this->makeContainer([]));

        self::assertNull($controller->exposeExtractBearerToken());
    }

    #[Test]
    public function testExtractBearerTokenParsesBearerScheme(): void
    {
        $controller = $this->makeController(
            $this->request(['HTTP_AUTHORIZATION' => 'Bearer   spaced-token  ']),
            $this->makeContainer([]),
        );

        self::assertSame('spaced-token', $controller->exposeExtractBearerToken());
    }

    #[Test]
    public function testExtractBearerTokenReturnsRawHeaderWithoutScheme(): void
    {
        $controller = $this->makeController(
            $this->request(['HTTP_AUTHORIZATION' => 'raw-token-no-scheme']),
            $this->makeContainer([]),
        );

        self::assertSame('raw-token-no-scheme', $controller->exposeExtractBearerToken());
    }

    // =========================================================================
    // authenticateViaWstoken
    // =========================================================================

    #[Test]
    public function testAuthenticateViaWstokenSetsUserOnValidToken(): void
    {
        $user = (object) ['id' => 42, 'deleted' => 0, 'suspended' => 0];
        $this->installDb([
            'external_tokens' => (object) ['token' => 'tok', 'validuntil' => 0, 'userid' => 42],
            'user' => $user,
        ]);
        $controller = $this->makeController($this->request(), $this->makeContainer([]));

        $controller->exposeAuthenticateViaWstoken('tok');

        self::assertSame($user, $GLOBALS['__middag_test_session_user']);
    }

    #[Test]
    public function testAuthenticateViaWstokenThrowsWhenTokenMissing(): void
    {
        $this->installDb(['external_tokens' => false]);
        $controller = $this->makeController($this->request(), $this->makeContainer([]));

        $this->expectException(MiddagAuthenticationException::class);
        $controller->exposeAuthenticateViaWstoken('nope');
    }

    #[Test]
    public function testAuthenticateViaWstokenThrowsWhenTokenExpired(): void
    {
        $this->installDb([
            'external_tokens' => (object) ['token' => 'tok', 'validuntil' => 1, 'userid' => 5],
        ]);
        $controller = $this->makeController($this->request(), $this->makeContainer([]));

        $this->expectException(MiddagAuthenticationException::class);
        $controller->exposeAuthenticateViaWstoken('tok');
    }

    #[Test]
    public function testAuthenticateViaWstokenThrowsWhenUserMissing(): void
    {
        $this->installDb([
            'external_tokens' => (object) ['token' => 'tok', 'validuntil' => 0, 'userid' => 5],
            'user' => false,
        ]);
        $controller = $this->makeController($this->request(), $this->makeContainer([]));

        $this->expectException(MiddagAuthenticationException::class);
        $controller->exposeAuthenticateViaWstoken('tok');
    }

    #[Test]
    public function testAuthenticateViaWstokenThrowsWhenUserDeleted(): void
    {
        $this->installDb([
            'external_tokens' => (object) ['token' => 'tok', 'validuntil' => 0, 'userid' => 5],
            'user' => (object) ['id' => 5, 'deleted' => 1, 'suspended' => 0],
        ]);
        $controller = $this->makeController($this->request(), $this->makeContainer([]));

        $this->expectException(MiddagAuthenticationException::class);
        $controller->exposeAuthenticateViaWstoken('tok');
    }

    #[Test]
    public function testAuthenticateViaWstokenThrowsWhenUserSuspended(): void
    {
        $this->installDb([
            'external_tokens' => (object) ['token' => 'tok', 'validuntil' => 0, 'userid' => 5],
            'user' => (object) ['id' => 5, 'deleted' => 0, 'suspended' => 1],
        ]);
        $controller = $this->makeController($this->request(), $this->makeContainer([]));

        $this->expectException(MiddagAuthenticationException::class);
        $controller->exposeAuthenticateViaWstoken('tok');
    }

    // =========================================================================
    // jsonResponse (envelope)
    // =========================================================================

    #[Test]
    public function testJsonResponseWrapsScalarUnderDataKey(): void
    {
        $controller = $this->makeController($this->request(), $this->makeContainer([]));

        $response = $controller->exposeJsonResponse('hello');
        $body = $this->decode($response);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('hello', $body['data']);
        self::assertTrue($body['success']);
    }

    #[Test]
    public function testJsonResponseAddsSuccessTrueForOkArray(): void
    {
        $controller = $this->makeController($this->request(), $this->makeContainer([]));

        $body = $this->decode($controller->exposeJsonResponse(['items' => [1, 2]]));

        self::assertTrue($body['success']);
        self::assertSame([1, 2], $body['items']);
    }

    #[Test]
    public function testJsonResponseSuccessFalseForErrorStatus(): void
    {
        $controller = $this->makeController($this->request(), $this->makeContainer([]));

        $response = $controller->exposeJsonResponse(['x' => 1], Response::HTTP_BAD_REQUEST);
        $body = $this->decode($response);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertFalse($body['success']);
    }

    #[Test]
    public function testJsonResponsePreservesExplicitSuccessKey(): void
    {
        $controller = $this->makeController($this->request(), $this->makeContainer([]));

        $body = $this->decode($controller->exposeJsonResponse(['success' => false, 'note' => 'x']));

        self::assertFalse($body['success']);
    }

    #[Test]
    public function testJsonResponseTriggersHandleOnce(): void
    {
        $controller = $this->makeController($this->request(), $this->makeContainer([]));

        $controller->exposeJsonResponse(['a' => 1]);
        $controller->exposeJsonResponse(['b' => 2]);

        // ensurePreHandled short-circuits after the first call (handled flag set).
        self::assertSame(1, $controller->handleCalls);
    }

    #[Test]
    public function testJsonResponseSkipsHandleWhenAlreadyHandled(): void
    {
        $controller = $this->makeController($this->request(), $this->makeContainer([]));
        $controller->markHandled();

        $controller->exposeJsonResponse(['a' => 1]);

        self::assertSame(0, $controller->handleCalls);
    }

    // =========================================================================
    // errorPage / errorResponse
    // =========================================================================

    #[Test]
    public function testErrorPageDelegatesToJsonErrorResponse(): void
    {
        $controller = $this->makeController($this->request(), $this->makeContainer([]));

        $response = $controller->exposeErrorPage('boom', Response::HTTP_BAD_REQUEST);
        $body = $this->decode($response);

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertFalse($body['success']);
        self::assertSame('boom', $body['message']);
        self::assertSame(Response::HTTP_BAD_REQUEST, $body['error_code']);
    }

    #[Test]
    public function testErrorResponseOmitsDebugWhenNull(): void
    {
        $controller = $this->makeController($this->request(), $this->makeContainer([]));

        $body = $this->decode($controller->exposeErrorResponse('bad', Response::HTTP_BAD_REQUEST, null));

        self::assertArrayNotHasKey('debug', $body);
    }

    #[Test]
    public function testErrorResponseIncludesDebugWhenProvidedAndDeveloperMode(): void
    {
        $controller = $this->makeController($this->request(), $this->makeContainer([]));

        // The debugging() bootstrap stub returns true (developer mode on).
        $body = $this->decode($controller->exposeErrorResponse('bad', Response::HTTP_BAD_REQUEST, ['trace' => 'x']));

        self::assertSame(['trace' => 'x'], $body['debug']);
    }

    // =========================================================================
    // created / noContent / notFound / forbidden
    // =========================================================================

    #[Test]
    public function testCreatedReturns201Envelope(): void
    {
        $controller = $this->makeController($this->request(), $this->makeContainer([]));

        $response = $controller->exposeCreated(['id' => 9]);
        $body = $this->decode($response);

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        self::assertTrue($body['success']);
        self::assertSame(9, $body['id']);
    }

    #[Test]
    public function testNoContentReturns204AndTriggersHandle(): void
    {
        $controller = $this->makeController($this->request(), $this->makeContainer([]));

        $response = $controller->exposeNoContent();

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        self::assertSame('', (string) $response->getContent());
        self::assertSame(1, $controller->handleCalls);
    }

    #[Test]
    public function testNotFoundReturns404(): void
    {
        $controller = $this->makeController($this->request(), $this->makeContainer([]));

        $response = $controller->exposeNotFound('missing');
        $body = $this->decode($response);

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        self::assertSame('missing', $body['message']);
        self::assertFalse($body['success']);
    }

    #[Test]
    public function testForbiddenReturns403(): void
    {
        $controller = $this->makeController($this->request(), $this->makeContainer([]));

        $response = $controller->exposeForbidden('nope');
        $body = $this->decode($response);

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        self::assertSame('nope', $body['message']);
        self::assertFalse($body['success']);
    }

    // =========================================================================
    // Fixtures
    // =========================================================================

    /**
     * Anonymous authentication fake with recording + toggles.
     */
    private function makeAuth(): AuthenticationInterface
    {
        return new class implements AuthenticationInterface {
            public bool $loggedIn = false;

            public bool $guest = false;

            public bool $sesskeyThrows = false;

            public int $requireLoginCalls = 0;

            public int $requireSesskeyCalls = 0;

            public function requireLogin(?int $courseid = null, bool $autologinguest = true): void
            {
                ++$this->requireLoginCalls;
            }

            public function isLoggedIn(): bool
            {
                return $this->loggedIn;
            }

            public function isGuest(): bool
            {
                return $this->guest;
            }

            public function requireSesskey(): void
            {
                ++$this->requireSesskeyCalls;
                if ($this->sesskeyThrows) {
                    throw new RuntimeException('bad sesskey');
                }
            }
        };
    }

    /**
     * Minimal PSR-11 container backed by an id => service map.
     *
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

    /**
     * Recording $DB double: get_record() returns a per-table record map.
     *
     * @param array<string, false|stdClass> $records
     */
    private function installDb(array $records): void
    {
        $GLOBALS['DB'] = new class($records) extends moodle_database {
            /** @param array<string, mixed> $records */
            public function __construct(private array $records) {}

            public function get_record($table, ?array $conditions = null, $fields = '*', $strictness = 0)
            {
                return $this->records[$table] ?? false;
            }
        };
    }

    /**
     * Build the concrete controller under test wired with request + container.
     */
    private function makeController(Request $request, ContainerInterface $container): object
    {
        $controller = new class extends AbstractApiController {
            public int $handleCalls = 0;

            /** Override the parent lifecycle hook with a recording stand-in. */
            public function handle(): void
            {
                ++$this->handleCalls;
                $this->handled = true;
            }

            public function exposeRequiresAuthentication(): bool
            {
                return $this->requiresAuthentication();
            }

            public function exposeIsJson(): bool
            {
                return $this->isJson();
            }

            public function exposeRequireLogin(): void
            {
                $this->requireLogin();
            }

            public function exposeAuthenticateApiRequest(): void
            {
                $this->authenticateApiRequest();
            }

            public function exposeResolveApiToken(): ?string
            {
                return $this->resolveApiToken();
            }

            public function exposeExtractBearerToken(): ?string
            {
                return $this->extractBearerToken();
            }

            public function exposeAuthenticateViaWstoken(string $token): void
            {
                $this->authenticateViaWstoken($token);
            }

            public function exposeJsonResponse(mixed $data, int $status = Response::HTTP_OK): JsonResponse
            {
                return $this->jsonResponse($data, $status);
            }

            public function exposeErrorPage(string $message, int $status = Response::HTTP_BAD_REQUEST): Response
            {
                return $this->errorPage($message, $status);
            }

            public function exposeErrorResponse(string $message, int $status = Response::HTTP_BAD_REQUEST, mixed $debug = null): JsonResponse
            {
                return $this->errorResponse($message, $status, $debug);
            }

            public function exposeCreated(mixed $data = []): JsonResponse
            {
                return $this->created($data);
            }

            public function exposeNoContent(): Response
            {
                return $this->noContent();
            }

            public function exposeNotFound(string $message = 'Resource not found'): JsonResponse
            {
                return $this->notFound($message);
            }

            public function exposeForbidden(string $message = 'Access denied'): JsonResponse
            {
                return $this->forbidden($message);
            }

            public function isRequireLoginFlag(): bool
            {
                return $this->requireLogin;
            }

            public function isRequireSesskeyFlag(): bool
            {
                return $this->requireSesskey;
            }

            public function markHandled(): void
            {
                $this->handled = true;
            }
        };

        $controller->setRequest($request);
        $controller->setContainer($container);

        return $controller;
    }

    /**
     * @param array<string, string> $server
     * @param array<string, string> $query
     */
    private function request(array $server = [], array $query = []): Request
    {
        return new Request(query: $query, server: $server);
    }

    /**
     * Decode a JSON response body into an array.
     *
     * @return array<string, mixed>
     */
    private function decode(Response $response): array
    {
        return (array) json_decode((string) $response->getContent(), true);
    }
}
