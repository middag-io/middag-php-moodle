<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Http\Routing;

use Middag\Framework\Http\Contract\RouteLoaderInterface as route_loader_interface;
use Middag\Moodle\Http\Contract\RouterInterface as router_interface;
use Middag\Moodle\Http\Routing\PluginAwareUrlGenerator as plugin_aware_url_generator;
use Middag\Moodle\Http\Routing\RouteLoader as route_loader;
use Middag\Moodle\Shared\Util\Debug as debug;
use Middag\Moodle\Support\UrlSupport as url_support;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Router.
 *
 * Handles the storage, context resolution, and URL generation for routes.
 * Delegates the discovery (loading) of routes to route_loader_interface.
 *
 * @internal
 *
 * @see router_interface
 */
class MoodleRouter implements router_interface
{
    /** @var string The entry point script within Moodle */
    private const ENTRY_POINT = '/local/middag/index.php';

    /** @var RouteCollection Collection of all registered routes */
    private readonly RouteCollection $routes;

    /** @var null|RequestContext Context containing request info (host, scheme, etc.) */
    private ?RequestContext $context = null;

    /** @var null|UrlGenerator Lazy-loaded URL generator */
    private ?UrlGenerator $generator = null;

    /**
     * @param null|route_loader_interface $loader Optional loader. Defaults to concrete route_loader.
     */
    public function __construct(private readonly ?route_loader_interface $loader = new route_loader())
    {
        $this->routes = new RouteCollection();
    }

    /**
     * Initializes the Routing Context based on the current Globals.
     * Sets the base URL specific to this Moodle plugin.
     */
    public function initializeContext(): void
    {
        $request = Request::createFromGlobals();
        $this->context = (new RequestContext())->fromRequest($request);

        // Hardcoded entry point for the plugin within Moodle
        // NOTE: If the entry point file changes, update the ENTRY_POINT constant.
        $this->context->setBaseUrl(self::ENTRY_POINT);
    }

    /**
     * Retrieve the registered route collection.
     *
     * @return RouteCollection
     */
    public function getRoutes(): RouteCollection
    {
        return $this->routes;
    }

    /**
     * Get the current routing context (host, scheme, base path).
     *
     * @return null|RequestContext
     */
    public function getContext(): ?RequestContext
    {
        return $this->context;
    }

    /**
     * Registers default system routes (e.g. 404) and global regex patterns.
     */
    public function registerDefaultRoutes(): void
    {
        $this->routes->add('route_not_found', new Route('/404', [
            '_controller' => fn (): Response => new Response('Page not found', 404),
        ]));

        // Global regex requirements for cleaner route definitions in Attributes
        $this->routes->addRequirements([
            'any' => '.*',
            'id' => '[0-9]+',
            'courseid' => '[0-9]+',
            'userid' => '[0-9]+',
            'uuid' => '[0-9a-fA-F\-]{36}',
        ]);
    }

    /**
     * Manually registers a route.
     *
     * @param string $name         unique route name
     * @param string $path         URL path (e.g., /users/{id}).
     * @param string $controller   class name of the controller
     * @param string $method       method name within the controller
     * @param array  $requirements regex requirements for parameters
     */
    public function register(string $name, string $path, string $controller, string $method, array $requirements = []): void
    {
        $this->routes->add($name, new Route($path, [
            '_controller' => [$controller, $method],
        ], $requirements));
    }

    /**
     * Delegates scanning to the Loader.
     *
     * @param ContainerInterface $container
     * @param null|string        $specific_class optional class to scan specifically
     */
    public function scanAnnotations(ContainerInterface $container, ?string $specific_class = null): void
    {
        $this->loader->loadRoutes($this->routes, $container, $specific_class);
    }

    /**
     * Generates a URL for a given route.
     *
     * @param string $route          route name
     * @param array  $parameters     route parameters
     * @param int    $reference_type absolute path or Absolute URL
     *
     * @return string
     */
    public function generateUrl(
        string $route,
        array $parameters = [],
        int $reference_type = UrlGeneratorInterface::ABSOLUTE_PATH
    ): string {
        if (!$this->generator instanceof UrlGenerator) {
            if (!$this->context instanceof RequestContext) {
                $this->initializeContext();
            }

            /** @var RequestContext $context */
            $context = $this->context;
            $this->generator = new plugin_aware_url_generator($this->routes, $context, self::ENTRY_POINT);
        }

        try {
            $path = $this->generator->generate($route, $parameters, $reference_type);
        } catch (RouteNotFoundException) {
            // Fallback to avoid crashing the UI if a link is broken
            if (class_exists(debug::class)) {
                debug::trace('Route not found: ' . $route);
            }
            // Fallback to 404 route
            $path = $this->generator->generate('route_not_found', [], $reference_type);
        }

        // RouterInterface contracts a string return (platform-agnostic). Normalize
        // the generated path through url_support (double-slash cleanup, absolute
        // base) and emit the URL string. Moodle-specific callers that need a
        // moodle_url (e.g. middag::url_generator) re-wrap this string themselves.
        return url_support::get($path)->out(false);
    }
}
