<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Shared\Enum;

/**
 * Typed enum wrapping Moodle's FORMAT_* constants.
 *
 * Note: FORMAT_MOODLE and FORMAT_PLAIN both map to 0; resolved as PLAIN.
 *
 * @api
 */
enum TextFormat: int
{
    case Plain = 0;

    case Html = 1;

    case Wiki = 3;

    case Markdown = 4;

    /**
     * Whether the format is HTML.
     */
    public function isHtml(): bool
    {
        return $this === self::Html;
    }

    /**
     * Whether the format is Markdown.
     */
    public function isMarkdown(): bool
    {
        return $this === self::Markdown;
    }

    /**
     * Human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Plain => 'Plain text',
            self::Html => 'HTML',
            self::Wiki => 'Wiki-like',
            self::Markdown => 'Markdown',
        };
    }

    /**
     * Resolve from Moodle's raw int value (defaults to PLAIN).
     */
    public static function resolve(int $value): self
    {
        return self::tryFrom($value) ?? self::Plain;
    }
}
