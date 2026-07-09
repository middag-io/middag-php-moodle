<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Runtime;

use core\component as core_component;
use LogicException;
use Middag\Framework\Kernel\Contract\HostComponentContextInterface;
use Middag\Framework\Kernel\HostContext;
use Middag\Moodle\Config\ComponentContext;
use Middag\Moodle\Support\ConfigSupport;

/**
 * Neutral Moodle host context.
 *
 * The Kernel builds this once during boot and registers it via
 * {@see HostContext::set()} so adapter helpers (Inertia asset versioning, bundle
 * path resolution, ...) read the running plugin's identity, version, and base
 * path through the framework's neutral contract instead of hard-coding a
 * consumer plugin's component or constants.
 *
 * It is a small DTO so it stays trivially testable; {@see self::resolve()} is the
 * Moodle-native factory that reads the live environment. Sibling of
 * {@see MoodleComponentNameResolver}: that resolver feeds boot-failure
 * classification (native vs third-party FQCNs), while this context feeds neutral
 * runtime lookups — they overlap only on the component identifier.
 *
 * @api
 */
final readonly class MoodleHostContext implements HostComponentContextInterface
{
    /** Stable cache-busting token used when the plugin version is not yet installed/readable. */
    private const DEFAULT_ASSET_VERSION = '0';

    public function __construct(
        private string $componentName,
        private string $assetVersion,
        private ?string $basePath = null,
    ) {}

    /**
     * Build the context from the running Moodle environment.
     *
     * Resolves the component from the {@see ComponentContext} composition-root
     * seam, the asset version from the installed plugin version, and the base
     * path from {@see core_component}. Called by {@see Kernel::boot()} before any
     * Inertia wiring can read it.
     *
     * @throws LogicException when the product composition root has not configured {@see ComponentContext}
     */
    public static function resolve(): self
    {
        $component = ComponentContext::name();

        return new self(
            componentName: $component,
            assetVersion: self::resolveAssetVersion(),
            basePath: self::resolveBasePath($component),
        );
    }

    public function componentName(): string
    {
        return $this->componentName;
    }

    public function assetVersion(): string
    {
        return $this->assetVersion;
    }

    public function basePath(): ?string
    {
        return $this->basePath;
    }

    /**
     * The installed plugin version (e.g. {@code 2024010100}) as a cache-busting
     * token, degrading to a stable fallback when the plugin is not yet installed
     * or its config is unreadable.
     */
    private static function resolveAssetVersion(): string
    {
        $version = ConfigSupport::get('version');

        if (is_string($version) && $version !== '') {
            return $version;
        }

        if (is_int($version) || is_float($version)) {
            return (string) $version;
        }

        return self::DEFAULT_ASSET_VERSION;
    }

    /**
     * The absolute plugin directory for the running frankenstyle component, or
     * null when Moodle cannot resolve it (callers degrade per the contract).
     */
    private static function resolveBasePath(string $component): ?string
    {
        if (!class_exists(core_component::class)) {
            return null;
        }

        // moodle-stubs type the return as string, but the real API yields ?string
        // (null for an unknown/uninstalled component). The empty-string ternary
        // normalizes both '' and a runtime null to null without a static-analysis
        // "always false" comparison against null.
        $directory = core_component::get_component_directory($component);

        return $directory === '' ? null : $directory;
    }
}
