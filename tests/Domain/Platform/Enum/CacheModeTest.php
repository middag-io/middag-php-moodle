<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Domain\Platform\Enum;

use Middag\Moodle\Domain\Platform\Enum\CacheMode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(CacheMode::class)]
final class CacheModeTest extends TestCase
{
    #[Test]
    public function allCasesExist(): void
    {
        $cases = CacheMode::cases();
        $this->assertCount(3, $cases);
    }

    #[Test]
    public function applicationHasValue1(): void
    {
        $this->assertSame(1, CacheMode::Application->value);
    }

    #[Test]
    public function sessionHasValue2(): void
    {
        $this->assertSame(2, CacheMode::Session->value);
    }

    #[Test]
    public function requestHasValue4(): void
    {
        $this->assertSame(4, CacheMode::Request->value);
    }

    #[Test]
    public function toMoodleValueReturnsBackingValue(): void
    {
        $this->assertSame(1, CacheMode::Application->toMoodleValue());
        $this->assertSame(2, CacheMode::Session->toMoodleValue());
        $this->assertSame(4, CacheMode::Request->toMoodleValue());
    }

    #[Test]
    public function canBeCreatedFromValue(): void
    {
        $this->assertSame(CacheMode::Application, CacheMode::from(1));
        $this->assertSame(CacheMode::Session, CacheMode::from(2));
        $this->assertSame(CacheMode::Request, CacheMode::from(4));
    }

    #[Test]
    public function tryFromReturnsNullForInvalidValue(): void
    {
        $this->assertNull(CacheMode::tryFrom(0));
        $this->assertNull(CacheMode::tryFrom(3));
        $this->assertNull(CacheMode::tryFrom(5));
    }
}
