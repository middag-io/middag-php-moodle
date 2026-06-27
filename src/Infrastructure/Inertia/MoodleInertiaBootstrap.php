<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Infrastructure\Inertia;

use Middag\Framework\Http\Inertia\InertiaAdapter;
use Middag\Framework\Http\Inertia\InertiaFactory;
use Middag\Framework\Http\Inertia\InertiaVersionManager;
use Middag\Moodle\Kernel\Config\ComponentContext;
use Middag\Moodle\Kernel\Http\Contract\RouterInterface as router_interface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Wires the platform-agnostic Inertia runtime (framework) into the Moodle host.
 *
 * Called once during Kernel boot (PD-008 C). Registers:
 *  - Compiled bundle path → {@see InertiaVersionManager::setBundlePath()}
 *  - URL generator closure → {@see InertiaAdapter::setUrlGenerator()}
 *  - HTML bootstrap closure → {@see InertiaFactory::setHtmlBootstrap()}
 *
 * The AMD module name is held in the adapter (the bootstrap closure captures it)
 * rather than the framework runtime — the framework no longer exposes the
 * `InertiaManager::{set,get}AmdModule()` setter/getter (host-specific concern).
 *
 * Must run before {@see InertiaSharedProps::register()} so that the first
 * outbound Inertia response sees a configured runtime.
 *
 * @internal
 */
final class MoodleInertiaBootstrap
{
    /**
     * Register every host hook the framework Inertia runtime expects.
     */
    public static function registerHooks(router_interface $router): void
    {
        // AMD module name is adapter-owned state, captured by the bootstrap
        // closure below (the framework no longer holds it via InertiaManager).
        // The 1.x convention is one React Inertia bundle per component: each
        // plugin ships {component}/inertia_app_react as its standard entry point.
        $amdModule = ComponentContext::name() . '/inertia_app_react';

        // Moodle plugin compiled frontend bundle, used for cache-busting Inertia version.
        global $CFG;
        InertiaVersionManager::setBundlePath(
            $CFG->dirroot . '/local/' . ComponentContext::name() . '/amd/build/app.min.js'
        );

        InertiaAdapter::setUrlGenerator(
            static fn (string $route, array $params): string => $router->generateUrl($route, $params, UrlGeneratorInterface::ABSOLUTE_PATH)
        );

        InertiaFactory::setHtmlBootstrap(
            static fn (array $page, string $json, string $attr): Response => self::htmlBootstrap($amdModule, $page, $json, $attr)
        );
    }

    /**
     * Render the first-visit HTML shell hosting the Inertia mount point.
     *
     * Hooks into Moodle's `$PAGE->requires` pipeline so AMD modules and plugin
     * stylesheets load alongside MIDDAG-specific assets. Emits the appearance
     * script first to avoid a flash of wrong theme before React mounts.
     *
     * @param string               $amdModule AMD module name (adapter-owned state)
     * @param array<string, mixed> $page      Inertia page payload (component/props/url/version)
     */
    public static function htmlBootstrap(string $amdModule, array $page, string $json, string $attr): Response
    {
        global $PAGE;
        $PAGE->requires->js_call_amd($amdModule, 'init');
        $PAGE->requires->css('/local/' . ComponentContext::name() . '/styles/middag-app.css');
        $PAGE->requires->css('/local/' . ComponentContext::name() . '/styles/isolation.css');

        // Blocking appearance script — toggles .dark on <html> before React mount
        // to prevent flash of wrong theme (~200 bytes, runs synchronously).
        $appearance = <<<'JS'
        <script>(function(){var s=localStorage.getItem('middag-appearance')||'system';var t=s==='system'?matchMedia('(prefers-color-scheme:dark)').matches?'dark':'light':s;document.documentElement.classList.toggle('dark',t==='dark')})()</script>
        JS;

        $html = <<<HTML
            {$appearance}
            <div id="app" class="middag-root" data-page="{$attr}"></div>
            <script>window.__INERTIA_PAGE__ = {$json};</script>
        HTML;

        return new Response($html);
    }
}
