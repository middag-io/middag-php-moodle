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

use Middag\Moodle\Config\ComponentContext;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * URL generator that respects per-route plugin base URLs.
 *
 * When a route declares `_plugin_base` in its defaults (e.g. `/local/yourplugin/index.php`),
 * this generator temporarily overrides the RequestContext base URL before generating,
 * then restores it. Routes without `_plugin_base` use the default MIDDAG base URL.
 *
 * This allows plugins like `local_yourplugin` to have their routes generate URLs
 * under their own entry point instead of the running host plugin's default.
 *
 * @internal
 */
class PluginAwareUrlGenerator extends UrlGenerator
{
    /** @var string Default entry point for routes that declare no `_plugin_base`. */
    protected readonly string $defaultBaseUrl;

    public function __construct(
        RouteCollection $routes,
        RequestContext $context,
        ?string $defaultBaseUrl = null,
    ) {
        // Derive from the running host component (e.g. local_middag →
        // /local/middag/index.php) when the caller doesn't pin an entry point.
        $this->defaultBaseUrl = $defaultBaseUrl ?? ComponentContext::baseUrlPath() . '/index.php';
        parent::__construct($routes, $context);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
    {
        $route = $this->routes->get($name);
        $original_base = $this->context->getBaseUrl();

        // Determine the correct entry point for this route:
        // - Routes with _plugin_base: use their declared entry point (external plugin).
        // - Routes without _plugin_base: always use the default middag entry point,
        //   regardless of what kernel::handle() may have set on the context for matching.
        if ($route instanceof Route && $route->hasDefault('_plugin_base')) {
            $target_base = $route->getDefault('_plugin_base');
        } else {
            $target_base = $this->defaultBaseUrl;
        }

        if ($target_base === $original_base) {
            return parent::generate($name, $parameters, $referenceType);
        }

        $this->context->setBaseUrl($target_base);

        try {
            return parent::generate($name, $parameters, $referenceType);
        } finally {
            $this->context->setBaseUrl($original_base);
        }
    }
}
