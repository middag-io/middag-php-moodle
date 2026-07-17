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
     * Proxy a PSR-7 request from Moodle's native router to MIDDAG's http_kernel.
     *
     * Uses {@see Kernel::handleReturning()}, which dispatches through the framework's
     * PSR-15 `HttpKernel::handle()` and hands back a `ResponseInterface` directly — no
     * output buffering, no header conflicts with Moodle's Slim pipeline.
     *
     * @param object $request  PSR-7 ServerRequestInterface (reserved — required by handler contract)
     * @param object $response PSR-7 ResponseInterface from Moodle's Slim app; its status/headers/body
     *                         are populated from MIDDAG's response and returned
     * @param string $path     matched route path to forward
     *
     * @return object PSR-7 ResponseInterface
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

            $middagResponse = Kernel::handleReturning('/api/' . ltrim($path, '/'));

            $body = (string) $middagResponse->getBody();

            if ($body !== '') {
                $response->getBody()->write($body);
            }

            // withHeader (not withAddedHeader) — matches the single-value-per-name
            // shape MIDDAG responses carry today (Content-Type); a header repeated
            // with multiple values would only keep the last one.
            foreach ($middagResponse->getHeaders() as $name => $values) {
                foreach ($values as $value) {
                    $response = $response->withHeader($name, $value);
                }
            }

            return $response->withStatus($middagResponse->getStatusCode());
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
