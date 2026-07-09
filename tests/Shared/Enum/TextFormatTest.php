<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Shared\Enum;

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
        $this->assertSame(0, TextFormat::Plain->value);
    }

    #[Test]
    public function htmlHasValue1(): void
    {
        $this->assertSame(1, TextFormat::Html->value);
    }

    #[Test]
    public function wikiHasValue3(): void
    {
        $this->assertSame(3, TextFormat::Wiki->value);
    }

    #[Test]
    public function markdownHasValue4(): void
    {
        $this->assertSame(4, TextFormat::Markdown->value);
    }

    #[Test]
    public function isHtmlReturnsTrueOnlyForHtml(): void
    {
        $this->assertFalse(TextFormat::Plain->isHtml());
        $this->assertTrue(TextFormat::Html->isHtml());
        $this->assertFalse(TextFormat::Wiki->isHtml());
        $this->assertFalse(TextFormat::Markdown->isHtml());
    }

    #[Test]
    public function isMarkdownReturnsTrueOnlyForMarkdown(): void
    {
        $this->assertFalse(TextFormat::Plain->isMarkdown());
        $this->assertFalse(TextFormat::Html->isMarkdown());
        $this->assertFalse(TextFormat::Wiki->isMarkdown());
        $this->assertTrue(TextFormat::Markdown->isMarkdown());
    }

    #[Test]
    public function labelReturnsHumanReadableString(): void
    {
        $this->assertSame('Plain text', TextFormat::Plain->label());
        $this->assertSame('HTML', TextFormat::Html->label());
        $this->assertSame('Wiki-like', TextFormat::Wiki->label());
        $this->assertSame('Markdown', TextFormat::Markdown->label());
    }

    #[Test]
    public function resolveReturnsMatchingCase(): void
    {
        $this->assertSame(TextFormat::Plain, TextFormat::resolve(0));
        $this->assertSame(TextFormat::Html, TextFormat::resolve(1));
        $this->assertSame(TextFormat::Wiki, TextFormat::resolve(3));
        $this->assertSame(TextFormat::Markdown, TextFormat::resolve(4));
    }

    #[Test]
    public function resolveDefaultsToPlainForUnknownValue(): void
    {
        $this->assertSame(TextFormat::Plain, TextFormat::resolve(99));
        $this->assertSame(TextFormat::Plain, TextFormat::resolve(2));
        $this->assertSame(TextFormat::Plain, TextFormat::resolve(-1));
    }
}
