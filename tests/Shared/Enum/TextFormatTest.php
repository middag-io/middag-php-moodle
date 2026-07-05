<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Enum;

use Middag\Moodle\Shared\Enum\TextFormat;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(TextFormat::class)]
final class TextFormatTest extends TestCase
{
    #[Test]
    public function allCasesExist(): void
    {
        $cases = TextFormat::cases();
        $this->assertCount(4, $cases);
    }

    #[Test]
    public function plainHasValue0(): void
    {
        $this->assertSame(0, TextFormat::PLAIN->value);
    }

    #[Test]
    public function htmlHasValue1(): void
    {
        $this->assertSame(1, TextFormat::HTML->value);
    }

    #[Test]
    public function wikiHasValue3(): void
    {
        $this->assertSame(3, TextFormat::WIKI->value);
    }

    #[Test]
    public function markdownHasValue4(): void
    {
        $this->assertSame(4, TextFormat::MARKDOWN->value);
    }

    #[Test]
    public function isHtmlReturnsTrueOnlyForHtml(): void
    {
        $this->assertFalse(TextFormat::PLAIN->isHtml());
        $this->assertTrue(TextFormat::HTML->isHtml());
        $this->assertFalse(TextFormat::WIKI->isHtml());
        $this->assertFalse(TextFormat::MARKDOWN->isHtml());
    }

    #[Test]
    public function isMarkdownReturnsTrueOnlyForMarkdown(): void
    {
        $this->assertFalse(TextFormat::PLAIN->isMarkdown());
        $this->assertFalse(TextFormat::HTML->isMarkdown());
        $this->assertFalse(TextFormat::WIKI->isMarkdown());
        $this->assertTrue(TextFormat::MARKDOWN->isMarkdown());
    }

    #[Test]
    public function labelReturnsHumanReadableString(): void
    {
        $this->assertSame('Plain text', TextFormat::PLAIN->label());
        $this->assertSame('HTML', TextFormat::HTML->label());
        $this->assertSame('Wiki-like', TextFormat::WIKI->label());
        $this->assertSame('Markdown', TextFormat::MARKDOWN->label());
    }

    #[Test]
    public function resolveReturnsMatchingCase(): void
    {
        $this->assertSame(TextFormat::PLAIN, TextFormat::resolve(0));
        $this->assertSame(TextFormat::HTML, TextFormat::resolve(1));
        $this->assertSame(TextFormat::WIKI, TextFormat::resolve(3));
        $this->assertSame(TextFormat::MARKDOWN, TextFormat::resolve(4));
    }

    #[Test]
    public function resolveDefaultsToPlainForUnknownValue(): void
    {
        $this->assertSame(TextFormat::PLAIN, TextFormat::resolve(99));
        $this->assertSame(TextFormat::PLAIN, TextFormat::resolve(2));
        $this->assertSame(TextFormat::PLAIN, TextFormat::resolve(-1));
    }
}
