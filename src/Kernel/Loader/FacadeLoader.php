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

use FilesystemIterator;
use Middag\Framework\Kernel\Contract\FacadeLoaderInterface as facade_loader_interface;
use Middag\Moodle\Config\ComponentContext;
use Middag\Moodle\Kernel\Kernel;
use Middag\Moodle\Shared\Util\Environment as environment;
use Middag\Moodle\Support\CacheSupport as cache_support;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;

/**
 * Facade Loader.
 *
 * Scans the `facade/` directories (Core and Extensions) to verify availability.
 * While Facades are static and autoloaded by Composer/Moodle, this loader can perform
 * warm-up tasks like checking if the service linked to the facade is active.
 *
 * @internal
 *
 * @see facade_loader_interface
 */
class FacadeLoader implements facade_loader_interface
{
    private const CACHE_AREA = 'loader';

    private const CACHE_KEY = 'facades';

    /**
     * Map of core subdirectories to scan.
     */
    private const CORE_PATHS = [
        ['/facade', 'base\facade'],
    ];

    /**
     * Constructor.
     *
     * @param null|string $projectRoot host plugin directory to scan; null
     *                                 resolves it through {@see Kernel::hostDirectory()}
     */
    public function __construct(
        private readonly ?string $projectRoot = null
    ) {}

    /**
     * Load facades.
     * Since Facades are static, "loading" here mostly means discovery/validation.
     */
    public function load(): void
    {
        // Discovery trigger
        $this->discoverFacades();
    }

    /**
     * Discover facade classes.
     *
     * @return array<string, string> Map of ShortName => FQCN
     */
    public function getDefinitions(): array
    {
        return $this->discoverFacades();
    }

    /**
     * Discover all facade classes in core and extensions.
     *
     * @return array|string[]
     */
    private function discoverFacades(): array
    {
        if (!environment::isDevelopment()) {
            $cached = cache_support::get(self::CACHE_KEY, self::CACHE_AREA);
            if ($cached !== false && is_array($cached)) {
                return $cached;
            }
        }

        $map = [];

        // 1. Core Facades
        foreach (self::CORE_PATHS as [$path, $ns_suffix]) {
            $map += $this->scanDirectory(
                $this->root() . $path,
                ComponentContext::name() . '\\' . $ns_suffix
            );
        }

        // 2. Extension Facades — legacy subdirectory pattern (extensions/{slug}/facade/)
        $extensions_dir = $this->root() . '/extensions/';
        if (is_dir($extensions_dir)) {
            foreach (glob($extensions_dir . '*', GLOB_ONLYDIR) as $dir) {
                $slug = basename($dir);
                $ns = sprintf('%s\extensions\%s\facade', ComponentContext::name(), $slug);
                $map += $this->scanDirectory($dir . '/facade', $ns);
            }
        }

        // 3. Extension Facades — suffix pattern (*_facade.php anywhere in extensions/)
        if (is_dir($extensions_dir)) {
            $map += $this->scanSuffixFacades($extensions_dir);
        }

        cache_support::set(self::CACHE_KEY, $map, self::CACHE_AREA);

        return $map;
    }

    /**
     * The directory to scan: the injected root, or the host plugin directory
     * resolved through Moodle's component registry.
     */
    private function root(): string
    {
        return $this->projectRoot ?? Kernel::hostDirectory();
    }

    /**
     * Scan a given directory for concrete facade classes.
     *
     * @param string $path      absolute directory path
     * @param string $namespace namespace prefix to prepend
     *
     * @return array<string, string>
     */
    private function scanDirectory(string $path, string $namespace): array
    {
        $map = [];

        if (!is_dir($path)) {
            return [];
        }

        foreach (glob($path . '/*.php') as $file) {
            $class_name = basename($file, '.php');
            $fqcn = $namespace . '\\' . $class_name;

            if (class_exists($fqcn)) {
                $ref = new ReflectionClass($fqcn);
                // Ensure it's not an abstract base class
                if (!$ref->isAbstract()) {
                    $map[$class_name] = $fqcn;
                }
            }
        }

        return $map;
    }

    /**
     * Recursively discover *_facade.php files in extensions.
     *
     * @param string $extensions_dir absolute path to extensions/ directory
     *
     * @return array<string, string>
     */
    private function scanSuffixFacades(string $extensions_dir): array
    {
        $map = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($extensions_dir, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            if (!str_ends_with($file->getFilename(), '_facade.php')) {
                continue;
            }
            $relative = str_replace($this->root() . '/', '', $file->getPathname());
            $relative = preg_replace('/\.php$/', '', $relative);
            $fqcn = ComponentContext::name() . '\\' . str_replace('/', '\\', $relative);

            if (class_exists($fqcn)) {
                $ref = new ReflectionClass($fqcn);
                if (!$ref->isAbstract()) {
                    $map[$ref->getShortName()] = $fqcn;
                }
            }
        }

        return $map;
    }
}
