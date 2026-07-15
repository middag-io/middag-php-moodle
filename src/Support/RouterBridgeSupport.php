<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Support;

use Middag\Moodle\Config\ComponentContext;
use Middag\Moodle\Runtime\Kernel;
use Middag\Moodle\Shared\Util\Debug;
use Throwable;

/**
 * Conditionally registers MIDDAG API routes in Moodle 5.1+ native router.
 *
 * This bridge enables:
 * - MIDDAG endpoints accessible via r.php (friendly URLs)
 * - MIDDAG OpenAPI spec visible in /admin/swaggerui.php
 *
 * The bridge is a thin proxy: Moodle Slim routes delegate to MIDDAG's
 * http_kernel. MIDDAG retains full ownership of request handling.
 *
 * Safety:
 * - Uses VersionSupport::supports() + VersionSupport::symbolExists() to avoid
 *   fatal errors on Moodle < 5.1
 * - References Moodle 5.1 classes via strings, never via use statements
 * - Compatible with PHP 8.1+ (no PHP 8.2+ features)
 *
 * @internal
 */
final class RouterBridgeSupport
{
    /**
     * Feature matrix for VersionSupport::supports().
     */
    private const FEATURE_MATRIX = [
        'moodle_router' => ['since' => '5.1'],
    ];

    /**
     * Check if the Moodle native router is available.
     */
    public static function isAvailable(): bool
    {
        return VersionSupport::supports('moodle_router', self::FEATURE_MATRIX)
            && VersionSupport::symbolExists('core\router\route_loader_interface');
    }

    /**
     * Register MIDDAG routes in the Moodle native router.
     *
     * Called conditionally during plugin boot. Does nothing on Moodle < 5.1.
     *
     * Moodle auto-discovers routes from the consumer plugin's `route\api\*` and
     * `route\controller\*` namespaces. Proxy classes in those namespaces delegate
     * to proxy_request() which forwards to the framework's http_kernel.
     */
    public static function register(): void
    {
        if (!self::isAvailable()) {
            return;
        }

        // Moodle 5.1+ route discovery is automatic via namespace convention.
        // Proxy classes in classes/route/api/ carry `route` attributes
        // and delegate to proxy_request().
        // No programmatic registration needed — Moodle's route_loader
        // scans the namespace at boot time.
    }

    /**
     * Stub: proxy a PSR-7 request from Moodle's native router to MIDDAG's http_kernel.
     *
     * NOT RELIABLE in 5.0.0 — http_kernel::handle() calls Response::send() internally,
     * causing header conflicts with Moodle's Slim pipeline. This stub uses output
     * buffering as best-effort but is NOT production-ready.
     *
     * Full implementation requires ADR-208 (Symfony HttpKernel migration) which makes
     * handle() return Response instead of calling send(). Planned for post-5.0.0.
     *
     * @param object $request  PSR-7 ServerRequestInterface (reserved — required by handler contract)
     * @param object $response PSR-7 ResponseInterface from Moodle's Slim app
     * @param string $path     matched route path to forward
     *
     * @return object PSR-7 ResponseInterface
     *
     * @todo Implement with PSR-7 ↔ HttpFoundation bridge after ADR-208 migration
     */
    public static function proxyRequest(object $request, object $response, string $path = ''): object
    {
        try {
            if (!class_exists(Kernel::class)) {
                // @codeCoverageIgnoreStart — the framework Kernel is a hard
                // dependency of this library and always autoloadable inside the
                // suite; the guard only matters for broken installs (R-05).
                $response->getBody()->write(json_encode(['error' => 'Framework not available'], JSON_THROW_ON_ERROR));

                return $response
                    ->withHeader('Content-Type', 'application/json')
                    ->withStatus(503);
                // @codeCoverageIgnoreEnd
            }

            // Best-effort output buffering.
            // Limitation: headers sent by Symfony Response::send() via header()
            // leak directly and conflict with Slim's response pipeline.
            ob_start();
            Kernel::handle('/api/' . ltrim($path, '/'));
            $output = (string) ob_get_clean();

            if ($output !== '') {
                $response->getBody()->write($output);
            }

            $status = http_response_code() ?: 200;

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus($status);
        } catch (Throwable $throwable) {
            Debug::traceException($throwable);

            $response->getBody()->write(json_encode([
                'error' => 'Internal framework error',
            ], JSON_THROW_ON_ERROR));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }
    }

    /**
     * Get the URL to MIDDAG's OpenAPI spec (JSON format).
     *
     * Can be used to configure Swagger UI or external documentation tools.
     */
    public static function getOpenapiJsonUrl(): string
    {
        global $CFG;

        return $CFG->wwwroot . ComponentContext::baseUrlPath() . '/index.php/api/openapi.json';
    }

    /**
     * Get the URL to MIDDAG's OpenAPI spec (YAML format).
     */
    public static function getOpenapiYamlUrl(): string
    {
        global $CFG;

        return $CFG->wwwroot . ComponentContext::baseUrlPath() . '/index.php/api/openapi.yaml';
    }
}
