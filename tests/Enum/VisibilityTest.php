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

use Middag\Moodle\Enum\Visibility;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class VisibilityTest extends TestCase
{
    #[Test]
    public function allCasesExist(): void
    {
        $cases = Visibility::cases();
        $this->assertCount(2, $cases);
    }

    #[Test]
    public function hiddenHasValue0(): void
    {
        $this->assertSame(0, Visibility::HIDDEN->value);
    }

    #[Test]
    public function visibleHasValue1(): void
    {
        $this->assertSame(1, Visibility::VISIBLE->value);
    }

    #[Test]
    public function isVisibleReturnsTrueOnlyForVisible(): void
    {
        $this->assertFalse(Visibility::HIDDEN->isVisible());
        $this->assertTrue(Visibility::VISIBLE->isVisible());
    }

    #[Test]
    public function labelReturnsHumanReadableString(): void
    {
        $this->assertSame('Hidden', Visibility::HIDDEN->label());
        $this->assertSame('Visible', Visibility::VISIBLE->label());
    }

    #[Test]
    public function resolveReturnsMatchingCase(): void
    {
        $this->assertSame(Visibility::HIDDEN, Visibility::resolve(0));
        $this->assertSame(Visibility::VISIBLE, Visibility::resolve(1));
    }

    #[Test]
    public function resolveDefaultsToVisibleForUnknownValue(): void
    {
        $this->assertSame(Visibility::VISIBLE, Visibility::resolve(99));
        $this->assertSame(Visibility::VISIBLE, Visibility::resolve(-1));
    }
}
