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

use core\context\system;
use core\output\user_picture;
use Middag\Framework\Http\Inertia\InertiaManager;
use Middag\Moodle\Config\ComponentContext;
use Middag\Moodle\Kernel\Kernel;
use Middag\Moodle\Support\ThemeSupport;
use Middag\Ui\Navigation\Contract\NavigationRegistryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Throwable;

/**
 * Registers Inertia shared props available on every response.
 *
 * Props are lazy (closures) — resolved only when the response is rendered.
 * Called once during http_kernel boot.
 *
 * @internal
 *
 * @see ADR-807 ref/shell-and-navigation §3-4
 */
class InertiaSharedProps
{
    /**
     * Register all default shared props.
     */
    public static function register(): void
    {
        // Navigation — resolved from the DI container where extensions register items during boot().
        InertiaManager::share('navigation', fn (): array => self::buildNavigation());

        // Auth — current user + capabilities.
        InertiaManager::share('auth', fn (): array => self::buildAuth());

        // Theme — appearance preference + brand color.
        InertiaManager::share('theme', fn (): array => self::buildTheme());

        // Scope — global scope data (extensions populate in boot).
        InertiaManager::share('scope', fn (): array => self::buildScope());

        // Flash — one-shot messages from session.
        InertiaManager::share('flash', fn (): ?array => self::buildFlash());

        // Locale + version (static, no closure needed).
        InertiaManager::share('locale', fn (): string => current_language());
        InertiaManager::share('version', ComponentContext::name());
    }

    /**
     * Build the 3-level navigation tree from the DI-registered navigation_registry.
     *
     * Returns NavigationTreePayload format (tree + activeKey + footer).
     * The React SidebarNav detects this format and uses the TreeSidebarNav renderer
     * with drill-down support.
     *
     * Navigation items are registered by extensions during boot() via
     * $this->navigation() (ADR-807). The registry singleton lives in the
     * DI container, bound by core_extension::register().
     *
     * @return array{tree: array, activeKey: string, footer: array}
     */
    public static function buildNavigation(): array
    {
        /** @var NavigationRegistryInterface $registry */
        $registry = Kernel::get(NavigationRegistryInterface::class);

        return $registry->build(self::resolveCurrentRoute());
    }

    /**
     * Resolve the current route name from the request path.
     *
     * Uses the kernel router's routes and context to match the current
     * request URI against registered routes, extracting the Symfony
     * `_route` name. Returns empty string on non-routed requests
     * (e.g. Moodle pages outside the MIDDAG router).
     */
    private static function resolveCurrentRoute(): string
    {
        try {
            $router = Kernel::routing();
            $routes = $router->getRoutes();
            $context = $router->getContext();

            if (!$context instanceof RequestContext) {
                return '';
            }

            $request = Request::createFromGlobals();
            $matcher = new UrlMatcher($routes, $context);
            $parameters = $matcher->match($request->getPathInfo());

            return $parameters['_route'] ?? '';
        } catch (ResourceNotFoundException) {
            // Current URL does not match any registered route.
            return '';
        } catch (Throwable) {
            // Graceful degradation: navigation renders without active state.
            return '';
        }
    }

    /**
     * Build auth shared prop from current Moodle user.
     *
     * @return array{id: int, name: string, email: string, avatarUrl: null|string, capabilities: string[]}
     */
    private static function buildAuth(): array
    {
        global $USER, $PAGE;

        $capabilities = [];

        // Check MIDDAG-specific capabilities in the system context.
        $context = system::instance();
        $middag_caps = [
            'local/middag:manage',
            'local/middag:moderate',
            'local/middag:view',
        ];

        foreach ($middag_caps as $cap) {
            if (has_capability($cap, $context)) {
                $capabilities[] = $cap;
            }
        }

        $avatar_url = null;

        try {
            $userpicture = new user_picture($USER);
            $userpicture->size = 100;
            $avatar_url = $userpicture->get_url($PAGE)->out(false);
        } catch (Throwable) {
            // Avatar unavailable — leave null.
        }

        return [
            'id' => (int) $USER->id,
            'name' => fullname($USER),
            'email' => $USER->email ?? '',
            'avatarUrl' => $avatar_url,
            'capabilities' => $capabilities,
        ];
    }

    /**
     * Build theme shared prop.
     *
     * Delegates brand color resolution to ThemeSupport (Theme Bridge, ADR-807 ref-807-06 §3).
     *
     * @return array{strings: array, appearance: null|string, brandColor: null|string, inherit: bool}
     */
    private static function buildTheme(): array
    {
        $theme = ThemeSupport::buildTheme();

        return [
            'strings' => [],
            'appearance' => null,
            'brandColor' => $theme['brandColor'],
            'inherit' => $theme['inherit'],
        ];
    }

    /**
     * Build global scope data.
     *
     * Extensions populate scope data via Inertia::share() in their boot().
     * This returns a base structure; extensions merge their data.
     *
     * @return array{identifiers: string[]}
     */
    private static function buildScope(): array
    {
        return [
            'identifiers' => [],
        ];
    }

    /**
     * Build flash messages from session.
     *
     * @return null|array{success?: string, error?: string, info?: string, warning?: string}
     */
    private static function buildFlash(): ?array
    {
        global $SESSION;

        $flash = [];

        if (!empty($SESSION->middag_flash_success)) {
            $flash['success'] = $SESSION->middag_flash_success;
            unset($SESSION->middag_flash_success);
        }

        if (!empty($SESSION->middag_flash_error)) {
            $flash['error'] = $SESSION->middag_flash_error;
            unset($SESSION->middag_flash_error);
        }

        if (!empty($SESSION->middag_flash_info)) {
            $flash['info'] = $SESSION->middag_flash_info;
            unset($SESSION->middag_flash_info);
        }

        if (!empty($SESSION->middag_flash_warning)) {
            $flash['warning'] = $SESSION->middag_flash_warning;
            unset($SESSION->middag_flash_warning);
        }

        return $flash === [] ? null : $flash;
    }
}
