<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Support;

use Middag\Moodle\Support\VersionSupport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * A trait fixture so {@see VersionSupport::symbolExists()} can be proven to
 * detect traits, which {@see class_exists()} alone would not.
 *
 * @internal
 */
trait VersionSupportSymbolFixtureTrait {}

/**
 * @internal
 */
#[CoversClass(VersionSupport::class)]
final class VersionSupportSymbolTest extends TestCase
{
    #[Test]
    public function testDetectsAnExistingClass(): void
    {
        self::assertTrue(VersionSupport::symbolExists(VersionSupport::class));
    }

    #[Test]
    public function testDetectsAnExistingInterface(): void
    {
        // class_exists() alone returns false for interfaces; the seam must not.
        self::assertTrue(VersionSupport::symbolExists(Throwable::class));
    }

    #[Test]
    public function testDetectsAnExistingTrait(): void
    {
        // class_exists() alone returns false for traits; the seam must not.
        self::assertTrue(VersionSupport::symbolExists(VersionSupportSymbolFixtureTrait::class));
    }

    #[Test]
    public function testReturnsFalseForAnAbsentSymbol(): void
    {
        // Mirrors probing an optional newer-Moodle symbol that is not present on
        // the current floor (e.g. the 5.1+ native router interface at runtime).
        self::assertFalse(VersionSupport::symbolExists('Middag\Moodle\This\Symbol\Does\Not\Exist'));
    }
}
