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

use InvalidArgumentException;
use Middag\Moodle\Config\ComponentContext;
use Middag\Moodle\Runtime\Kernel;
use Middag\Moodle\Shared\Util\Debug;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * Conditionally registers MIDDAG API routes in Moodle 5.1+ native router.
 *
 * This bridge enables:
 * - MIDDAG endpoints accessible via r.php (friendly URLs)
 * - MIDDAG OpenAPI spec visible in /admin/swaggerui.php
 *
 * The bridge is a thin proxy: Moodle Slim routes delegate to MIDDAG's HTTP
 * kernel with the full incoming request, and relay its full response. MIDDAG
 * keeps ownership of request handling: the same controllers, authorization and
 * middleware run whichever router the request came through.
 *
 * Safety:
 * - Uses VersionSupport::supports() + VersionSupport::symbolExists() to avoid
 *   fatal errors on Moodle < 5.1
 * - References Moodle 5.1 classes via strings, never via use statements
 *
 * Wiring helper, outside the supported public surface; consumers reach it
 * through their route proxy classes.
 *
 * @internal
 */
final class RouterBridgeSupport
{
    /**
     * Feature matrix for VersionSupport::supports().
     */
    private const array FEATURE_MATRIX = [
        'moodle_router' => ['since' => '5.1'],
    ];

    /**
     * Path prefix of MIDDAG API routes in the framework router.
     */
    private const string API_PREFIX = '/api/';

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
     * to proxyRequest() which forwards to the framework's HTTP kernel.
     */
    public static function register(): void
    {
        if (!self::isAvailable()) {
            return;
        }

        // Moodle 5.1+ route discovery is automatic via namespace convention.
        // Proxy classes in classes/route/api/ carry `route` attributes
        // and delegate to proxyRequest().
        // No programmatic registration needed — Moodle's route_loader
        // scans the namespace at boot time.
    }

    /**
     * Proxy a PSR-7 request from Moodle's native router to MIDDAG's HTTP kernel.
     *
     * The incoming request is forwarded whole: method, query string, headers,
     * cookies, parsed and raw body, uploaded files, attributes, protocol version
     * and server params. The URI path changes to `/api/<path>`, so the framework
     * router matches the MIDDAG API route. The entry-script server params change
     * with it: SCRIPT_NAME and SCRIPT_FILENAME are emptied, PHP_SELF is set to
     * the forwarded path and ORIG_SCRIPT_NAME is removed, so the framework
     * derives no base path from `r.php` and matches the forwarded path as is.
     *
     * The kernel's response is copied onto `$response`: status code and reason
     * phrase, every value of every header (a header the kernel sets replaces the
     * host's header of the same name; other host headers are kept) and the body
     * stream. A failure inside the kernel yields a generic 500 JSON body; details
     * go to the debug trace, never to the client.
     *
     * @param object $request  the PSR-7 ServerRequestInterface Moodle's router passes to the route
     * @param object $response the PSR-7 ResponseInterface Moodle's router passes to the route
     * @param string $path     the MIDDAG API path to forward to, relative to `/api/`
     *
     * @throws InvalidArgumentException when $request or $response is not the PSR-7 type above
     */
    public static function proxyRequest(object $request, object $response, string $path = ''): ResponseInterface
    {
        if (!$request instanceof ServerRequestInterface) {
            throw new InvalidArgumentException(sprintf('RouterBridgeSupport::proxyRequest() expects $request to implement %s, %s given.', ServerRequestInterface::class, $request::class));
        }

        if (!$response instanceof ResponseInterface) {
            throw new InvalidArgumentException(sprintf('RouterBridgeSupport::proxyRequest() expects $response to implement %s, %s given.', ResponseInterface::class, $response::class));
        }

        try {
            $middagResponse = Kernel::handleRequest(self::forwardedRequest($request, $path));

            return self::copyResponse($middagResponse, $response);
        } catch (Throwable $throwable) {
            Debug::traceException($throwable);

            return self::errorResponse($response);
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

    /**
     * Build the request the kernel sees: the incoming one at `/api/<path>`.
     */
    private static function forwardedRequest(ServerRequestInterface $request, string $path): ServerRequestInterface
    {
        $uri = $request->getUri()->withPath(self::API_PREFIX . ltrim($path, '/'));

        $serverParams = array_replace($request->getServerParams(), [
            'SCRIPT_NAME' => '',
            'SCRIPT_FILENAME' => '',
            'PHP_SELF' => $uri->getPath(),
        ]);
        unset($serverParams['ORIG_SCRIPT_NAME']);

        $forwarded = (new Psr17Factory())
            ->createServerRequest($request->getMethod(), $uri, $serverParams)
            ->withProtocolVersion($request->getProtocolVersion())
            ->withCookieParams($request->getCookieParams())
            ->withQueryParams($request->getQueryParams())
            ->withParsedBody($request->getParsedBody())
            ->withUploadedFiles($request->getUploadedFiles())
            ->withBody($request->getBody());

        foreach ($request->getHeaders() as $name => $values) {
            $forwarded = $forwarded->withHeader((string) $name, $values);
        }

        foreach ($request->getAttributes() as $name => $value) {
            $forwarded = $forwarded->withAttribute((string) $name, $value);
        }

        return $forwarded;
    }

    /**
     * Copy status, reason phrase, every header value and the body stream.
     */
    private static function copyResponse(ResponseInterface $from, ResponseInterface $to): ResponseInterface
    {
        foreach ($from->getHeaders() as $name => $values) {
            $to = $to->withHeader((string) $name, $values);
        }

        $body = $from->getBody();

        if ($body->isSeekable()) {
            $body->rewind();
        }

        return $to
            ->withBody($body)
            ->withStatus($from->getStatusCode(), $from->getReasonPhrase());
    }

    /**
     * The generic 500 returned when the kernel fails; it carries no detail.
     */
    private static function errorResponse(ResponseInterface $response): ResponseInterface
    {
        $body = (new Psr17Factory())->createStream(json_encode(['error' => 'Internal framework error'], JSON_THROW_ON_ERROR));

        return $response
            ->withBody($body)
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(500);
    }
}
