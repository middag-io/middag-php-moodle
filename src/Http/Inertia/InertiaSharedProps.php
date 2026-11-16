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
use Middag\Framework\Http\Session\FlashBag;
use Middag\Moodle\Config\ComponentContext;
use Middag\Moodle\Runtime\Kernel;
use Middag\Moodle\Settings\SettingsNamingPolicy;
use Middag\Moodle\Support\SessionSupport;
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
 */
class InertiaSharedProps
{
    private static ?SettingsNamingPolicy $themePolicy = null;

    /**
     * Configure the naming policy used by the default theme shared prop.
     *
     * Consumers with product-owned settings should inject their explicit
     * prefix here; null preserves the library-neutral default.
     */
    public static function configureThemePolicy(?SettingsNamingPolicy $policy): void
    {
        self::$themePolicy = $policy;
    }

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

        // Kernel validation errors, in the shape useForm().errors expects.
        InertiaManager::share('errors', fn (): array => self::buildErrors());

        // CSRF — the Moodle sesskey, under the name the @middag-io/react
        // shared-props contract already declares. Clients that cannot read the
        // XSRF-TOKEN cookie (plain fetch) take the token from here and send it
        // as X-CSRF-Token.
        InertiaManager::share('csrf_token', fn (): string => SessionSupport::sesskey());

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
     * $this->navigation(). The registry singleton lives in the
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

        // Check the running host plugin's conventional capabilities in the
        // system context, deriving the component prefix (e.g. local/middag)
        // from ComponentContext so the adapter stays product-agnostic.
        $context = system::instance();
        $host = ComponentContext::capabilityComponent();
        $host_caps = [
            $host . ':manage',
            $host . ':moderate',
            $host . ':view',
        ];

        foreach ($host_caps as $cap) {
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
     * Delegates brand color resolution to ThemeSupport (the theme bridge).
     *
     * @return array{strings: array, appearance: null|string, brandColor: null|string, inherit: bool}
     */
    private static function buildTheme(): array
    {
        $theme = ThemeSupport::buildTheme(self::$themePolicy);

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
     * Messages left in the framework FlashBag, normalised to the same shape
     * as the legacy flash. Resolved from the container; when no FlashBag is
     * wired, returns an empty array and the legacy flash keeps working alone.
     *
     * @return array<string, string>
     */
    private static function pullFrameworkFlash(): array
    {
        $bag = self::flashBag();
        if (!$bag instanceof FlashBag) {
            return [];
        }

        $out = [];
        foreach ($bag->pull() as $key => $value) {
            if (is_string($value) && $value !== '') {
                $out[(string) $key] = $value;
            }
        }

        return $out;
    }

    /**
     * Map of field => errors from the last rejected validation.
     *
     * @return array<string, mixed>
     */
    private static function buildErrors(): array
    {
        $bag = self::flashBag();

        return $bag instanceof FlashBag ? $bag->pullErrors() : [];
    }

    /** The container's FlashBag, or null when it is not registered. */
    private static function flashBag(): ?FlashBag
    {
        try {
            $container = Kernel::container();
            if (!$container->has(FlashBag::class)) {
                return null;
            }
            $bag = $container->get(FlashBag::class);

            return $bag instanceof FlashBag ? $bag : null;
        } catch (Throwable) {
            // Partial boot / CLI: without a container there is no flash to drain.
            return null;
        }
    }

    /**
     * Build flash messages from session.
     *
     * @return null|array{success?: string, error?: string, info?: string, warning?: string}
     */
    private static function buildFlash(): ?array
    {
        global $SESSION;

        // Drain the framework FlashBag FIRST. There are two separate stores: the
        // kernel writes domain rejections there (createValidationResponse ->
        // 303 + messages) through $_SESSION['_middag_flash'], while legacy
        // controllers use $SESSION->middag_flash_*. Without this drain the
        // message was written to one store and read from the other, so the user
        // got the page back with no explanation of why it was rejected.
        $flash = self::pullFrameworkFlash();

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
