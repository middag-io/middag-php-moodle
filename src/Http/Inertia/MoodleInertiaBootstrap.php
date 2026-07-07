<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Http\Inertia;

use core\component as core_component;
use Middag\Framework\Http\Inertia\InertiaAdapter;
use Middag\Framework\Http\Inertia\InertiaFactory;
use Middag\Framework\Http\Inertia\InertiaVersionManager;
use Middag\Moodle\Config\ComponentContext;
use Middag\Moodle\Http\Contract\RouterInterface;
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
    public static function registerHooks(RouterInterface $router): void
    {
        // AMD module name is adapter-owned state, captured by the bootstrap
        // closure below (the framework no longer holds it via InertiaManager).
        // The 1.x convention is one Inertia bundle per component: each plugin
        // ships {component}/inertia_app as its standard entry point — a thin AMD
        // wrapper exposing init() (Moodle's js_call_amd contract) that side-effect
        // boots the compiled SPA bundle. The AMD namespace is the frankenstyle
        // component (Moodle resolves local_middag/* to local/middag/amd/build/*).
        $amdModule = ComponentContext::name() . '/inertia_app';

        // Moodle plugin compiled frontend bundle, used for cache-busting Inertia
        // version. Path is derived from the component directory (correct for any
        // plugin type, never doubles the type prefix) and points at the conventional
        // inertia_app entry so the lib stays product-agnostic.
        global $CFG;
        InertiaVersionManager::setBundlePath(
            $CFG->dirroot . self::componentWebBase() . '/amd/build/inertia_app.min.js'
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
        $webBase = self::componentWebBase();
        $PAGE->requires->js_call_amd($amdModule, 'init');
        $PAGE->requires->css($webBase . '/styles/middag-app.css');
        $PAGE->requires->css($webBase . '/styles/isolation.css');

        // Blocking appearance script — toggles .dark on <html> before React mount
        // to prevent flash of wrong theme (~200 bytes, runs synchronously).
        $appearance = <<<'JS'
        <script>(function(){var s=localStorage.getItem('middag-appearance')||'system';var t=s==='system'?matchMedia('(prefers-color-scheme:dark)').matches?'dark':'light':s;document.documentElement.classList.toggle('dark',t==='dark')})()</script>
        JS;

        // Inertia v3 server-side bootstrap: the client reads the initial page
        // from a <script type="application/json" data-page="{id}"> element whose
        // data-page matches the createInertiaApp id, not from a div[data-page]
        // attribute (the legacy v1/v2 convention). The mount div carries the same
        // id so createInertiaApp's getElementById(id) finds it. The id comes from
        // InertiaFactory (the product composition root sets it via setAppId),
        // keeping this generic Moodle adapter product-agnostic.
        // $json is JSON_HEX_TAG/APOS/QUOT/AMP-encoded, so '<', '>', '&', quotes are
        // \u-escaped — no </script> breakout is possible inside the JSON block.
        $appId = InertiaFactory::getAppId();
        $html = <<<HTML
            {$appearance}
            <div id="{$appId}" class="middag-root"></div>
            <script type="application/json" data-page="{$appId}">{$json}</script>
        HTML;

        return new Response($html);
    }

    /**
     * Web path (relative to wwwroot) where this component's plugin files are
     * served, e.g. local_middag → /local/middag. Derived from the Moodle
     * component directory so it is correct for any plugin type (local, mod,
     * block, …) and never doubles the type prefix the way `'/local/' . component`
     * would for a frankenstyle name like `local_middag`.
     */
    private static function componentWebBase(): string
    {
        global $CFG;
        $dir = core_component::get_component_directory(ComponentContext::name());

        return substr((string) $dir, strlen($CFG->dirroot));
    }
}
