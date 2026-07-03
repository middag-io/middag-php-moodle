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
use Middag\Framework\Exception\MiddagAuthenticationException as middag_authentication_exception;
use Middag\Framework\Exception\MiddagAuthorizationException as middag_authorization_exception;
use Middag\Moodle\Settings\framework_config;
use Middag\Moodle\Support\DbSupport as db_support;
use Middag\Moodle\Support\LangSupport as lang_support;
use Middag\Moodle\Support\SettingsSupport as settings_support;
use stdClass;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

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
    /** Setado pelo kernel quando a rota tem #[auth(login: false)]. */
    private bool $publicRoute = false;

    /**
     * Sinaliza ao controller que esta rota é pública.
     * Chamado pelo kernel ao detectar #[auth(login: false)].
     * Não deve ser chamado manualmente nas actions.
     */
    public function disableAuthentication(): void
    {
        $this->publicRoute = true;
    }

    /**
     * Pre-handle hook: autentica a requisição API e executa o pipeline de auth.
     *
     * Chamado automaticamente pelo kernel antes da action. Roda dual auth
     * (wstoken → session fallback) e em seguida chama handle() para aplicar
     * require_login, check_capabilities e setup de página.
     *
     * Subclasses que precisam de flags adicionais (set_require_capabilities etc.)
     * devem sobrescrever, configurar os flags e chamar parent::pre_handle().
     */
    public function preHandle(): void
    {
        if ($this->requiresAuthentication()) {
            $this->authenticateApiRequest();
        }

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
     * Retorna false quando a rota foi marcada com #[auth(login: false)].
     * Pode ser sobrescrito na subclasse para desabilitar auth em todo o controller.
     */
    protected function requiresAuthentication(): bool
    {
        return !$this->publicRoute;
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
     * - Not logged in / guest → middag_authentication_exception (401)
     * - Invalid sesskey       → middag_authorization_exception (403)
     */
    protected function requireLogin(): void
    {
        if ($this->requireLogin) {
            if (!$this->authentication()->isLoggedIn() || $this->authentication()->isGuest()) {
                throw new middag_authentication_exception(
                    lang_support::get('api_unauthorized'),
                );
            }
            $this->requiredLogin = true;
        }

        if ($this->requireSesskey && $this->request instanceof Request) {
            $method = strtoupper($this->request->getMethod());
            if (!in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)
                && !$this->isWsRequest()) {
                try {
                    $this->authentication()->requireSesskey();
                } catch (Throwable) {
                    throw new middag_authorization_exception(
                        lang_support::get('invalidsesskey', 'error') ?? 'Invalid session key',
                    );
                }
            }
        }
    }

    /**
     * Authenticate the API request.
     *
     * Dual auth: wstoken (Moodle webservice token) first,
     * then fallback to Moodle session. Also checks api_enabled setting.
     *
     * @throws middag_authorization_exception  if API is disabled
     * @throws middag_authentication_exception if auth fails
     */
    protected function authenticateApiRequest(): void
    {
        if (!settings_support::get(framework_config::api_enabled)) {
            throw new middag_authorization_exception(
                lang_support::get('api_disabled'),
            );
        }

        if ($this->authentication()->isLoggedIn()) {
            return;
        }

        $token = $this->resolveApiToken();
        if ($token !== null) {
            $this->authenticateViaWstoken($token);

            return;
        }

        // Fallback: Moodle session
        $this->setRequireLogin();
        if ($this->request->getMethod() !== 'GET') {
            $this->setRequireSesskey();
        }
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
     * @throws middag_authentication_exception if token is invalid, expired, or user is inactive
     */
    protected function authenticateViaWstoken(string $token): void
    {
        $tokenrecord = db_support::getRecord('external_tokens', ['token' => $token]);

        if (!$tokenrecord instanceof stdClass) {
            throw new middag_authentication_exception(
                lang_support::get('api_unauthorized'),
            );
        }

        if ($tokenrecord->validuntil > 0 && $tokenrecord->validuntil < time()) {
            throw new middag_authentication_exception(
                lang_support::get('api_unauthorized'),
            );
        }

        $user = db_support::getRecord('user', ['id' => $tokenrecord->userid]);

        if (!$user instanceof stdClass || $user->deleted || $user->suspended) {
            throw new middag_authentication_exception(
                lang_support::get('api_unauthorized'),
            );
        }

        manager::set_user($user);
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

        // Ensure 'success' key exists if not provided
        if (!isset($payload['success'])) {
            $payload['success'] = $status >= Response::HTTP_OK && $status < Response::HTTP_MULTIPLE_CHOICES;
        }

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
     * Dispara o pipeline de autenticação/contexto se ainda não executado.
     *
     * Espelha o comportamento de render() no controller base: garante que
     * pre_handle() rode antes de qualquer resposta ser enviada, mesmo em
     * endpoints que retornam JSON diretamente sem passar por render().
     */
    private function ensurePreHandled(): void
    {
        if (!$this->handled) {
            // Chama handle() diretamente (não pre_handle()) para evitar re-executar
            // authenticate_api_request(). Cobre controllers que sobrescrevem
            // pre_handle() sem chamar parent, garantindo que os flags configurados
            // sejam de fato aplicados antes da resposta ser enviada.
            $this->handle();
        }
    }
}
