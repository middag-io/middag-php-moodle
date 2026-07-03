<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Kernel\Loader;

use Middag\Framework\Kernel\Loader\HookfileLoader;
use Middag\Moodle\Config\ComponentContext;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

/**
 * Moodle-specific hookfile discovery.
 *
 * Hookfiles are PHP scripts loaded at boot to register signal subscriptions,
 * extend services, or otherwise inject behavior into the MIDDAG runtime
 * without packaging a full extension. Four discovery sources, in load order:
 *
 *   1. `$CFG->dataroot/middag_hooks.php`               — site-local overrides
 *   2. `$CFG->dirroot/local/middag_hooks.php`          — repo-wide hooks
 *   3. `$CFG->dirroot/theme/{active}/middag_hooks.php` — active theme hooks
 *   4. Plugins implementing `{prefix}_extend_hookfiles()` in their lib.php
 *      and returning a list of absolute paths.
 *
 * Source order matters: later sources can override behavior registered by
 * earlier ones because the bus reflects last-wins semantics for re-registered
 * keys. Plugin contributions are last so they can patch site/local/theme.
 *
 * Cache invalidation: per-request, the base class memoizes discover() output.
 * For cross-request MUC caching, callers should wrap discover() externally
 * because the cache lifetime is tied to the request, not the loader instance.
 *
 * @api
 */
final class MoodleHookfileLoader extends HookfileLoader
{
    private const HOOKFILE_BASENAME = 'middag_hooks.php';

    /**
     * @param null|string $hookPrefix frankenstyle prefix used to derive the plugin
     *                                extension callback name (`{prefix}_extend_hookfiles`);
     *                                defaults to the configured {@see ComponentContext} when null
     */
    public function __construct(
        private readonly ?string $hookPrefix = null,
        LoggerInterface $logger = new NullLogger(),
    ) {
        parent::__construct($logger);
    }

    /**
     * @return string[] absolute, readable hookfile paths
     */
    protected function discoverPaths(): array
    {
        global $CFG;

        $paths = [];

        if (isset($CFG->dataroot) && is_string($CFG->dataroot)) {
            $this->maybeAdd($paths, $CFG->dataroot . '/' . self::HOOKFILE_BASENAME);
        }

        if (isset($CFG->dirroot) && is_string($CFG->dirroot)) {
            $this->maybeAdd($paths, $CFG->dirroot . '/local/' . self::HOOKFILE_BASENAME);

            if (isset($CFG->theme) && is_string($CFG->theme) && $CFG->theme !== '') {
                $this->maybeAdd(
                    $paths,
                    $CFG->dirroot . '/theme/' . $CFG->theme . '/' . self::HOOKFILE_BASENAME,
                );
            }
        }

        foreach ($this->pluginContributions() as $contributed) {
            $this->maybeAdd($paths, $contributed);
        }

        return array_values(array_unique($paths));
    }

    /**
     * @return string[]
     */
    private function pluginContributions(): array
    {
        if (!function_exists('get_plugins_with_function')) {
            return [];
        }

        $function_name = 'extend_' . ($this->hookPrefix ?? ComponentContext::name()) . '_hookfiles';
        $contributions = [];

        try {
            $plugins = get_plugins_with_function($function_name, 'lib.php');
        } catch (Throwable) {
            return [];
        }

        if (!is_array($plugins)) {
            return [];
        }

        foreach ($plugins as $by_type) {
            if (!is_array($by_type)) {
                continue;
            }

            foreach ($by_type as $callback) {
                if (!is_callable($callback)) {
                    continue;
                }

                try {
                    $result = $callback();
                } catch (Throwable) {
                    continue;
                }

                if (!is_array($result)) {
                    continue;
                }

                foreach ($result as $candidate) {
                    if (is_string($candidate)) {
                        $contributions[] = $candidate;
                    }
                }
            }
        }

        return $contributions;
    }

    /**
     * @param string[] $paths
     */
    private function maybeAdd(array &$paths, string $candidate): void
    {
        if ($candidate === '' || !is_file($candidate) || !is_readable($candidate)) {
            return;
        }

        $paths[] = $candidate;
    }
}
