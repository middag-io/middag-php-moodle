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
        self::assertSame(0, UserVisibility::Nobody->value);
        self::assertSame(1, UserVisibility::Everyone->value);
        self::assertSame(2, UserVisibility::Self->value);
    }

    #[Test]
    public function isPublicTrueOnlyForEveryone(): void
    {
        self::assertTrue(UserVisibility::Everyone->isPublic());
        self::assertFalse(UserVisibility::Nobody->isPublic());
        self::assertFalse(UserVisibility::Self->isPublic());
    }

    #[Test]
    public function isPrivateTrueOnlyForNobody(): void
    {
        self::assertTrue(UserVisibility::Nobody->isPrivate());
        self::assertFalse(UserVisibility::Everyone->isPrivate());
        self::assertFalse(UserVisibility::Self->isPrivate());
    }

    #[Test]
    public function labelReturnsHumanReadableTextForEachCase(): void
    {
        self::assertSame('Not visible', UserVisibility::Nobody->label());
        self::assertSame('Visible to everyone', UserVisibility::Everyone->label());
        self::assertSame('Visible to user only', UserVisibility::Self->label());
    }

    #[Test]
    public function resolveMapsKnownIntsToCases(): void
    {
        self::assertSame(UserVisibility::Nobody, UserVisibility::resolve(0));
        self::assertSame(UserVisibility::Everyone, UserVisibility::resolve(1));
        self::assertSame(UserVisibility::Self, UserVisibility::resolve(2));
    }

    #[Test]
    public function resolveFallsBackToNobodyForUnknownInt(): void
    {
        self::assertSame(UserVisibility::Nobody, UserVisibility::resolve(99));
    }
}
