<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Adapter;

use Middag\Moodle\Contract\TranslatorInterface as translator_interface;
use Middag\Moodle\Kernel\Config\ComponentContext;
use Throwable;

/**
 * Adapter that provides translations using Moodle's native string system.
 *
 * @internal
 */
class Translator implements translator_interface
{
    /**
     * {@inheritDoc}
     */
    public function trans(string $identifier, ?string $component = null, mixed $args = null): string
    {
        $comp = $component ?? ComponentContext::name();

        try {
            return get_string($identifier, $comp, $args);
        } catch (Throwable) {
            return sprintf('[[%s]]', $identifier);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function has(string $identifier, ?string $component = null): bool
    {
        $comp = $component ?? ComponentContext::name();

        try {
            return get_string_manager()->string_exists($identifier, $comp);
        } catch (Throwable) {
            return false;
        }
    }
}
