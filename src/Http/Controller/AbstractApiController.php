<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Http\Controller;

use core\session\manager;
use Middag\Framework\Exception\MiddagAuthenticationException;
use Middag\Moodle\Support\DbSupport;
use Middag\Moodle\Support\LangSupport;
use stdClass;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Base API Controller.
 *
 * Forces JSON responses for all endpoints.
 * Standardizes the response envelope: { success: bool, data: mixed, message: string }.
 * Handles API authentication (wstoken or session fallback) automatically in pre_handle().
 *
 * @internal
 */
abstract class AbstractApiController extends AbstractController
{
    /** Set by the kernel when the route carries #[auth(login: false)]. */
    private bool $publicRoute = false;

    /**
     * True once this request was authenticated by a web-service token.
     *
     * The CSRF gate keys off this, NOT off {@see self::isWsRequest()}: that one
     * only reports that an `Authorization`/`wstoken` value is present, so a
     * session-authenticated request carrying any junk Bearer header would
     * exempt itself from the check.
     */
    private bool $authenticatedByToken = false;

    /**
     * Signals to the controller that this route is public.
     * Called by the kernel when it detects #[auth(login: false)].
     * Must not be called manually from actions.
     */
    public function disableAuthentication(): void
    {
        $this->publicRoute = true;
    }

    /**
     * Pre-handle hook: authenticates the API request and runs the auth pipeline.
     *
     * Called automatically by the kernel before the action. Runs dual auth
     * (wstoken → session fallback) and then calls handle() to apply
     * require_login, capability checks, and page setup.
     *
     * Subclasses that need extra flags (setRequireCapabilities() etc.) should
     * override, configure the flags, and call parent::preHandle().
     */
    public function preHandle(): void
    {
        if ($this->requiresAuthentication()) {
            $this->authenticateApiRequest();
        }

        // After authentication (so the wstoken exemption is known), before the
        // action (so a rejected request never reaches the service layer).
        $this->verifyCsrf();

        $this->handle();
    }

    /**
     * @return bool
     */
    public function isWsRequest(): bool
    {
        return !in_array($this->extractBearerToken(), [null, '', '0'], true) || !is_null($this->request->query->get('wstoken'));
    }

    /**
     * Whether this endpoint requires authentication.
     *
     * Returns false when the route was marked with #[auth(login: false)].
     * Can be overridden in a subclass to disable auth for the whole controller.
     */
    protected function requiresAuthentication(): bool
    {
        return !$this->publicRoute;
    }

    /**
     * API exemptions on top of the base gate.
     *
     * - Public routes (`requiresAuthentication() === false`): webhook receivers
     *   and token endpoints. They carry no Moodle session, and their real gate
     *   is their own HMAC/shared-secret check. Note this is the hook that works:
     *   `#[Auth(login: false)]` alone only reaches controllers implementing
     *   PublicRouteAwareInterface.
     * - Requests authenticated by wstoken: no session cookie is involved, so
     *   there is nothing for a third-party page to ride.
     */
    protected function verifyCsrf(): void
    {
        if (!$this->requiresAuthentication() || $this->authenticatedByToken) {
            return;
        }

        parent::verifyCsrf();
    }

    /**
     * Check if the request expects a JSON response.
     * Always true for API controllers to force JSON logic.
     */
    protected function isJson(): bool
    {
        return true;
    }

    // =========================================================================
    // API Authentication
    // =========================================================================

    /**
     * Override: API controllers check session state and throw typed exceptions
     * instead of letting Moodle redirect or die.
     *
     * - Not logged in / guest → MiddagAuthenticationException (401)
     *
     * CSRF is not checked here: see preHandle() / verifyCsrf().
     */
    protected function requireLogin(): void
    {
        if ($this->requireLogin) {
            if (!$this->authentication()->isLoggedIn() || $this->authentication()->isGuest()) {
                throw new MiddagAuthenticationException(
                    LangSupport::get('api_unauthorized'),
                );
            }
            $this->requiredLogin = true;
        }

        // Sesskey moved to preHandle()/verifyCsrf(). The old branch here was
        // exempted by isWsRequest() — header presence, not authentication.
    }

    /**
     * Authenticate the API request.
     *
     * Dual auth: wstoken (Moodle webservice token) first,
     * then fallback to Moodle session.
     *
     * A product-level "API enabled" policy gate is NOT enforced here: that is
     * host-product vocabulary and belongs to the consumer's own API controller
     * or middleware, not this generic adapter base class.
     *
     * @throws MiddagAuthenticationException if auth fails
     */
    protected function authenticateApiRequest(): void
    {
        if ($this->authentication()->isLoggedIn()) {
            return;
        }

        $token = $this->resolveApiToken();
        if ($token !== null) {
            $this->authenticateViaWstoken($token);

            return;
        }

        // Fallback: Moodle session. No sesskey wiring here — it used to live
        // below an early `return` taken by every logged-in request, so it never
        // ran; the gate is now unconditional in preHandle() via verifyCsrf().
        $this->setRequireLogin();
    }

    /**
     * Resolve token from query param or Authorization header.
     */
    protected function resolveApiToken(): ?string
    {
        $query_token = $this->request->query->get('wstoken');
        if ($query_token !== null && $query_token !== '') {
            return (string) $query_token;
        }

        return $this->extractBearerToken();
    }

    /**
     * Extract token from Authorization header.
     */
    protected function extractBearerToken(): ?string
    {
        $header = $this->request->headers->get('Authorization', '');

        if ($header === '' || $header === null) {
            return null;
        }

        if (preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return trim($matches[1]);
        }

        return $header;
    }

    /**
     * Validate wstoken via Moodle webservice API and configure $USER.
     *
     * @throws MiddagAuthenticationException if token is invalid, expired, or user is inactive
     */
    protected function authenticateViaWstoken(string $token): void
    {
        $tokenrecord = DbSupport::getRecord('external_tokens', ['token' => $token]);

        if (!$tokenrecord instanceof stdClass) {
            throw new MiddagAuthenticationException(
                LangSupport::get('api_unauthorized'),
            );
        }

        if ($tokenrecord->validuntil > 0 && $tokenrecord->validuntil < time()) {
            throw new MiddagAuthenticationException(
                LangSupport::get('api_unauthorized'),
            );
        }

        $user = DbSupport::getRecord('user', ['id' => $tokenrecord->userid]);

        if (!$user instanceof stdClass || $user->deleted || $user->suspended) {
            throw new MiddagAuthenticationException(
                LangSupport::get('api_unauthorized'),
            );
        }

        manager::set_user($user);

        $this->authenticatedByToken = true;
    }

    /**
     * Return a JSON response with 'success' wrapper.
     *
     * @param mixed $data   Data to return
     * @param int   $status HTTP status code (default is 200)
     *
     * @return JsonResponse
     */
    protected function jsonResponse(mixed $data, int $status = Response::HTTP_OK): JsonResponse
    {
        $this->ensurePreHandled();
        // Normalize data structure
        $payload = is_array($data) ? $data : ['data' => $data];

        $payload['success'] ??= $status >= Response::HTTP_OK && $status < Response::HTTP_MULTIPLE_CHOICES;

        return parent::jsonResponse($payload, $status);
    }

    /**
     * Override error page to return JSON.
     *
     * @param string $message
     * @param int    $status
     *
     * @return Response
     */
    protected function errorPage(string $message, int $status = Response::HTTP_BAD_REQUEST): Response
    {
        return $this->errorResponse($message, $status);
    }

    /**
     * Send a standardized JSON error response.
     *
     * @param string $message User-friendly error message
     * @param int    $status  HTTP Status Code
     * @param mixed  $debug   Optional debug data (only shown in developer mode)
     *
     * @return JsonResponse
     */
    protected function errorResponse(string $message, int $status = Response::HTTP_BAD_REQUEST, mixed $debug = null): JsonResponse
    {
        $data = [
            'success' => false,
            'message' => $message,
            'error_code' => $status,
        ];

        // Include debug info only if Moodle debugging is enabled
        if ($debug !== null && debugging('', DEBUG_DEVELOPER)) {
            $data['debug'] = $debug;
        }

        return new JsonResponse($data, $status);
    }

    /**
     * Helper for 201 Created.
     */
    protected function created(mixed $data = []): JsonResponse
    {
        return $this->jsonResponse($data, Response::HTTP_CREATED);
    }

    /**
     * Helper for 204 No Content.
     */
    protected function noContent(): Response
    {
        $this->ensurePreHandled();

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Helper for 404 Not Found.
     */
    protected function notFound(string $message = 'Resource not found'): JsonResponse
    {
        return $this->errorResponse($message, Response::HTTP_NOT_FOUND);
    }

    /**
     * Helper for 403 Forbidden.
     */
    protected function forbidden(string $message = 'Access denied'): JsonResponse
    {
        return $this->errorResponse($message, Response::HTTP_FORBIDDEN);
    }

    /**
     * Triggers the authentication/context pipeline if it has not run yet.
     *
     * Mirrors the render() behavior of the base controller: guarantees that
     * preHandle() runs before any response is sent, even for endpoints that
     * return JSON directly without going through render().
     */
    private function ensurePreHandled(): void
    {
        if (!$this->handled) {
            // Calls handle() directly (not preHandle()) to avoid re-running
            // authenticateApiRequest(). Covers controllers that override
            // preHandle() without calling parent, ensuring the configured
            // flags are actually applied before the response is sent.
            $this->handle();
        }
    }
}
