<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Domain\User;

use Middag\Moodle\Domain\User\UserVisibility;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(UserVisibility::class)]
final class UserVisibilityCoverageTest extends TestCase
{
    #[Test]
    public function casesCarryMoodleIntValues(): void
    {
        self::assertSame(0, UserVisibility::NOBODY->value);
        self::assertSame(1, UserVisibility::EVERYONE->value);
        self::assertSame(2, UserVisibility::SELF->value);
    }

    #[Test]
    public function isPublicTrueOnlyForEveryone(): void
    {
        self::assertTrue(UserVisibility::EVERYONE->isPublic());
        self::assertFalse(UserVisibility::NOBODY->isPublic());
        self::assertFalse(UserVisibility::SELF->isPublic());
    }

    #[Test]
    public function isPrivateTrueOnlyForNobody(): void
    {
        self::assertTrue(UserVisibility::NOBODY->isPrivate());
        self::assertFalse(UserVisibility::EVERYONE->isPrivate());
        self::assertFalse(UserVisibility::SELF->isPrivate());
    }

    #[Test]
    public function labelReturnsHumanReadableTextForEachCase(): void
    {
        self::assertSame('Not visible', UserVisibility::NOBODY->label());
        self::assertSame('Visible to everyone', UserVisibility::EVERYONE->label());
        self::assertSame('Visible to user only', UserVisibility::SELF->label());
    }

    #[Test]
    public function resolveMapsKnownIntsToCases(): void
    {
        self::assertSame(UserVisibility::NOBODY, UserVisibility::resolve(0));
        self::assertSame(UserVisibility::EVERYONE, UserVisibility::resolve(1));
        self::assertSame(UserVisibility::SELF, UserVisibility::resolve(2));
    }

    #[Test]
    public function resolveFallsBackToNobodyForUnknownInt(): void
    {
        self::assertSame(UserVisibility::NOBODY, UserVisibility::resolve(99));
    }
}
