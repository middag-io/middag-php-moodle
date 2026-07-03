<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Hook;

use core\hook\described_hook;

/**
 * Abstract parent for the extend_extensions Moodle hook.
 *
 * Per PD-022 option C: lib hosts this abstract parent; the plugin class
 * `{component}\hook\extend_extensions` extends it and preserves the
 * load-bearing namespace for external subscriber compatibility.
 *
 * External Moodle plugins register extensions via `db/hooks.php` subscriptions
 * to `{component}\hook\extend_extensions` — that namespace must never change.
 *
 * @api
 */
abstract class AbstractExtendExtensions implements described_hook
{
    /**
     * @param array<int, array{class: string, slug: string, group: string, priority: int, hidden: bool}> $definitions
     *                                                                                                                Initial list of extension definitions collected by MIDDAG
     */
    public function __construct(
        protected array $definitions,
    ) {}

    /**
     * Return the current (possibly subscriber-augmented) extension definitions.
     *
     * @return array<int, array{class: string, slug: string, group: string, priority: int, hidden: bool}>
     */
    public function getDefinitions(): array
    {
        return $this->definitions;
    }

    /**
     * Add one or more extension definitions.
     *
     * Called by hook subscribers to inject third-party extensions into MIDDAG.
     *
     * @param array<int, array{class: string, slug: string, group: string, priority: int, hidden: bool}> $definitions
     */
    public function addExtensions(array $definitions): void
    {
        foreach ($definitions as $def) {
            $this->definitions[] = $def;
        }
    }

    public static function getHookDescription(): string
    {
        return 'Dispatched during MIDDAG extension discovery. Subscribers may inject additional extension definitions.';
    }

    /**
     * @return string[]
     */
    public static function getHookTags(): array
    {
        return ['middag', 'extensions'];
    }
}
