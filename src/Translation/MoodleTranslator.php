<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Translation;

use Middag\Framework\Translation\Contract\TranslatorInterface;
use Middag\Moodle\Config\ComponentContext;
use stdClass;

/**
 * Moodle translator — bridges the framework {@see TranslatorInterface} to
 * Moodle's native string system (`get_string()` / `string_exists()`).
 *
 * The `$component` maps to the Moodle frankenstyle component; an empty
 * component falls back to the running plugin resolved through
 * {@see ComponentContext::name()}. Framework `$params` become the `$a`
 * placeholder object: each entry turns into a property of `$a`, with
 * Symfony-style `%name%` delimiters stripped (`'%count%'` → `$a->count`).
 *
 * Failures from `get_string()`/`string_exists()` (e.g. a missing string in
 * developer mode) propagate to the caller — the adapter does not swallow
 * host errors.
 */
final readonly class MoodleTranslator implements TranslatorInterface
{
    public function get(string $key, string $component = '', array $params = []): string
    {
        $component = $component !== '' ? $component : ComponentContext::name();

        return get_string($key, $component, $this->toPlaceholder($params));
    }

    public function has(string $key, string $component = ''): bool
    {
        $component = $component !== '' ? $component : ComponentContext::name();

        return get_string_manager()->string_exists($key, $component);
    }

    /**
     * Map framework params onto Moodle's `$a` placeholder object.
     *
     * Symfony-style `%name%` delimiters are stripped from the keys so lang
     * strings address the values as `{$a->name}`. Empty params map to null
     * (no placeholder).
     *
     * @param array<string, mixed> $params
     */
    private function toPlaceholder(array $params): ?stdClass
    {
        if ($params === []) {
            return null;
        }

        $a = new stdClass();

        foreach ($params as $name => $value) {
            $a->{trim((string) $name, '%')} = $value;
        }

        return $a;
    }
}
