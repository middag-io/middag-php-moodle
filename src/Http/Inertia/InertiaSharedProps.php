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
use Middag\Moodle\Support\SessionSupport;
use Middag\Ui\Form\FormErrors;
use Middag\Ui\Navigation\Contract\NavigationRegistryInterface;
use Middag\Ui\Navigation\NavigationTree;
use Middag\Ui\Shared\ValueObject\FlashMessages;
use Middag\Ui\Shared\ValueObject\Identity;
use Middag\Ui\Shared\ValueObject\SharedAuth;
use Middag\Ui\Shared\ValueObject\SharedTheme;
use stdClass;
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
    /**
     * Register all default shared props.
     */
    public static function register(): void
    {
        // Navigation — resolved from the DI container where extensions register items during boot().
        InertiaManager::share('navigation', fn (): array => self::buildNavigation()->jsonSerialize());

        // Auth — current user + capabilities.
        InertiaManager::share('auth', fn (): array => self::buildAuth()->jsonSerialize());

        // Theme — appearance preference + string overrides.
        InertiaManager::share('theme', fn (): array => self::buildTheme()->jsonSerialize());

        // Scope — global scope data (extensions populate in boot).
        InertiaManager::share('scope', fn (): stdClass => self::buildScope());

        // Flash — one-shot messages from the framework FlashBag; the slot is
        // dropped from the wire when there is nothing to show.
        InertiaManager::share('flash', fn (): array|stdClass|null => self::buildFlash()?->jsonSerialize());

        // Kernel validation errors, in the shape useForm().errors expects.
        InertiaManager::share('errors', fn (): array|stdClass => self::buildErrors()->jsonSerialize());

        // CSRF — the Moodle sesskey, under the name the @middag-io/react
        // shared-props contract already declares. Clients that cannot read the
        // XSRF-TOKEN cookie (plain fetch) take the token from here and send it
        // as X-CSRF-Token.
        InertiaManager::share('csrf_token', fn (): string => SessionSupport::sesskey());

        InertiaManager::share('locale', fn (): string => current_language());
    }

    /**
     * Build the 3-level navigation tree from the DI-registered navigation_registry.
     *
     * Returns the NavigationTree value object (tree + activeKey + footer);
     * the shared closure serialises it. The React SidebarNav detects this format and uses the TreeSidebarNav renderer
     * with drill-down support.
     *
     * Navigation items are registered by extensions during boot() via
     * $this->navigation(). The registry singleton lives in the
     * DI container, bound by core_extension::register().
     */
    public static function buildNavigation(): NavigationTree
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
     * The id is the string form of the Moodle user id. Email and avatar are
     * left off the wire when absent (never an empty string or null).
     */
    private static function buildAuth(): SharedAuth
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
            // Avatar unavailable — omitted from the wire.
        }

        $email = $USER->email ?? '';

        return new SharedAuth(
            identity: new Identity(
                id: (string) $USER->id,
                name: fullname($USER),
                email: $email === '' ? null : $email,
                avatarUrl: $avatar_url === '' ? null : $avatar_url,
            ),
            capabilities: $capabilities,
        );
    }

    /**
     * Build theme shared prop.
     *
     * Moodle exposes no appearance preference or string overrides here, so
     * the prop is the bare contract shape (empty strings map).
     */
    private static function buildTheme(): SharedTheme
    {
        return new SharedTheme();
    }

    /**
     * Build global scope data.
     *
     * Extensions populate scope data via Inertia::share() in their boot().
     * The base value is an empty object ({} on the wire).
     */
    private static function buildScope(): stdClass
    {
        return new stdClass();
    }

    /**
     * Messages left in the framework FlashBag. Resolved from the container;
     * when no FlashBag is wired, returns an empty array.
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

    /** Map of field => errors from the last rejected validation. */
    private static function buildErrors(): FormErrors
    {
        $bag = self::flashBag();

        return new FormErrors($bag instanceof FlashBag ? $bag->pullErrors() : []);
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
     * Build flash messages from the framework FlashBag; null when empty so
     * the slot is dropped from the wire.
     */
    private static function buildFlash(): ?FlashMessages
    {
        $flash = self::pullFrameworkFlash();

        return $flash === [] ? null : FlashMessages::fromArray($flash);
    }
}
