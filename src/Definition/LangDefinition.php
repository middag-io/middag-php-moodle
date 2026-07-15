<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Definition;

use InvalidArgumentException;
use Middag\Moodle\Definition\Contract\DefinitionInterface;

/**
 * Typed language-string definition for Moodle lang files.
 *
 * A catalog class declares the strings a library/framework layer consumes
 * (via `LangSupport::get()`), keyed by locale. The statics pipeline renders
 * them into a managed block inside the consumer plugin's
 * `lang/<locale>/<plugin>.php` files, preserving hand-written strings
 * outside the block.
 *
 * Unlike the db/* definitions this is not collected from extensions — the
 * catalog is passed explicitly to the generator (the keys belong to the
 * framework layer, not to a product extension).
 *
 * @api
 */
final readonly class LangDefinition implements DefinitionInterface
{
    /**
     * @param string                $key        lang string identifier (e.g. 'middag_core_task_outbox_worker')
     * @param array<string, string> $strings    locale => translated string; 'en' is mandatory
     *                                          (Moodle's fallback locale — a key missing in en
     *                                          is invisible to every other locale)
     * @param null|string           $min_moodle minimum Moodle version (null = no minimum)
     * @param null|string           $max_moodle maximum Moodle version (null = no maximum)
     */
    public function __construct(
        public string $key,
        public array $strings,
        public ?string $min_moodle = null,
        public ?string $max_moodle = null,
    ) {
        if (trim($this->key) === '') {
            throw new InvalidArgumentException('Lang key must be non-empty.');
        }

        if (!isset($this->strings['en']) || trim($this->strings['en']) === '') {
            throw new InvalidArgumentException(
                sprintf("Lang key '%s' must define a non-empty 'en' string.", $this->key),
            );
        }
    }

    /**
     * Render as locale => string map. The plugin name does not participate —
     * lang keys are file-scoped, the file already belongs to the component.
     */
    public function toMoodleArray(string $plugin_name): array
    {
        return $this->strings;
    }

    /**
     * Get the translated string for a locale, or null when the catalog does
     * not carry that locale (the renderer skips the key in that file).
     */
    public function getString(string $locale): ?string
    {
        return $this->strings[$locale] ?? null;
    }

    /**
     * @return list<string>
     */
    public function getLocales(): array
    {
        return array_keys($this->strings);
    }

    public function isCompatible(string $moodle_version): bool
    {
        if ($this->min_moodle !== null && version_compare($moodle_version, $this->min_moodle, '<')) {
            return false;
        }

        if ($this->max_moodle !== null && version_compare($moodle_version, $this->max_moodle, '>')) {
            return false;
        }

        return true;
    }

    public function getName(): string
    {
        return $this->key;
    }
}
