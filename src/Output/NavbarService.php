<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Output;

use Closure;
use core\context\system as context_system;
use core\exception\moodle_exception;
use Middag\Moodle\Config\ComponentContext;
use Middag\Moodle\Support\CapabilitySupport;
use Middag\Moodle\Support\LangSupport;
use Middag\Moodle\Support\UrlSupport;
use Middag\Moodle\Support\VersionSupport;

/**
 * Renders the MIDDAG dropdown in the Moodle user navigation bar.
 *
 * Uses only moodle/support/ dependencies (deptrac: MoodleInternal layer).
 * Extensibility via hooks is handled by the caller (lib.php) which is
 * outside the framework boundary and free to call any layer.
 *
 * @internal Called exclusively from lib.php::local_example_render_navbar_output()
 */
final class NavbarService
{
    /**
     * Render the MIDDAG navbar dropdown HTML.
     *
     * Items support extended fields beyond {url, name}:
     *  - icon: FontAwesome icon name (e.g. 'home', 'plus', 'cog')
     *  - separator: true to render a divider instead of a link
     *  - badge: string badge text (e.g. '3', 'new')
     *  - badge_color: Bootstrap badge color (e.g. 'danger', 'warning', 'info')
     *
     * Health badge data and extension alerts are injected via hooks by the caller
     * (lib.php), which is outside the framework boundary and can access any layer.
     *
     * @param array  $extra_items  Additional items from hooks (injected by caller)
     * @param ?array $health_badge Optional health badge data {health_score: int, health_color: string}
     *
     * @return string HTML rendered via Mustache template
     *
     * @throws moodle_exception
     */
    public static function render(array $extra_items = [], ?array $health_badge = null, ?Closure $url_generator = null): string
    {
        global $OUTPUT;

        if (!CapabilitySupport::has('moodle/site:config', context_system::instance())) {
            return '';
        }

        $items = array_merge(self::defaultItems($url_generator), $extra_items);

        // Sanitize: keep valid items (url+name) and separators.
        $items = array_values(array_filter($items, static fn (array $item): bool => !empty($item['separator']) || (isset($item['url'], $item['name']))));

        if ($items === []) {
            return '';
        }

        // Compatibility: Moodle < 5.0 (BS4) vs >= 5.0 (BS5).
        $is50plus = VersionSupport::atLeast('5.0');

        $templatecontext = [
            'hasitems' => count($items),
            'actionitems' => $items,
            'health_badge' => $health_badge,
            'dropdown_toggle_attr' => $is50plus ? 'data-bs-toggle="dropdown"' : 'data-toggle="dropdown"',
            'dropdown_menu_align' => $is50plus ? 'dropdown-menu-end' : 'dropdown-menu-right',
            'aria_label_user' => 'MIDDAG Menu',
            'toggle_id' => 'middag-user-menu-toggle',
            'menu_id' => 'middag-user-action-menu',
        ];

        return $OUTPUT->render_from_template(ComponentContext::name() . '/navbar-usernavigation', $templatecontext);
    }

    /**
     * Default menu items for the MIDDAG navbar dropdown.
     *
     * Organized as: Overview, quick actions (New Segment, New Connector),
     * separator, tools (System Status, Maintenance), separator, Settings.
     * Docs/Support removed per NAVIGATION-SPEC.md — low operational value.
     *
     * @return array<int, array{url?: string, name?: string, icon?: string, separator?: bool}>
     */
    /**
     * Default menu items for the MIDDAG navbar dropdown.
     *
     * URLs use PATH_INFO style (/local/middag/index.php/{route}) to match
     * the Symfony router. Query-parameter style (?route=) is legacy.
     *
     * @param ?Closure $url_generator Optional route-name → URL resolver.
     *                                When null, falls back to PATH_INFO concatenation.
     *
     * @return array<int, array{url?: string, name?: string, icon?: string, separator?: bool}>
     */
    private static function defaultItems(?Closure $url_generator = null): array
    {
        $url = $url_generator ?? static fn (string $route): string => UrlSupport::get(ComponentContext::baseUrlPath() . '/index.php/' . $route)->out();

        return [
            [
                'url' => $url('admin_home'),
                'name' => LangSupport::get('overview'),
                'icon' => 'home',
            ],
            ['separator' => true],
            [
                'url' => $url('segments_create'),
                'name' => LangSupport::getStringOrIdentifier('new_segment', ComponentContext::name()),
                'icon' => 'plus',
            ],
            [
                'url' => $url('admin_connectors_create'),
                'name' => LangSupport::getStringOrIdentifier('new_connector', ComponentContext::name()),
                'icon' => 'plus',
            ],
            [
                'url' => $url('workflow_create'),
                'name' => LangSupport::getStringOrIdentifier('new_action', ComponentContext::name()),
                'icon' => 'plus',
            ],
            ['separator' => true],
            [
                'url' => $url('admin_system_status'),
                'name' => LangSupport::get('systemstatus'),
                'icon' => 'heartbeat',
            ],
            [
                'url' => $url('admin_tools'),
                'name' => LangSupport::getStringOrIdentifier('maintenance', ComponentContext::name()),
                'icon' => 'wrench',
            ],
            ['separator' => true],
            [
                'url' => UrlSupport::get('/admin/settings.php', ['section' => 'middagtabcore'])->out(),
                'name' => LangSupport::get('settings'),
                'icon' => 'cog',
            ],
        ];
    }
}
