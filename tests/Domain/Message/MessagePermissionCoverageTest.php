<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Domain\Message;

use Middag\Moodle\Domain\Message\MessagePermission;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * MessagePermission is a pure backed enum wrapping Moodle's MESSAGE_* permission
 * bitmask. Every case's backing value and each arm of toMoodleValue() (the
 * 0xc/0x8/0x4 mapping) are asserted directly.
 *
 * @internal
 */
#[CoversClass(MessagePermission::class)]
final class MessagePermissionCoverageTest extends TestCase
{
    #[Test]
    public function testBackingValues(): void
    {
        self::assertSame('forced', MessagePermission::Forced->value);
        self::assertSame('permitted', MessagePermission::Permitted->value);
        self::assertSame('disallowed', MessagePermission::Disallowed->value);
    }

    #[Test]
    public function testFromResolvesEachBackingValue(): void
    {
        self::assertSame(MessagePermission::Forced, MessagePermission::from('forced'));
        self::assertSame(MessagePermission::Permitted, MessagePermission::from('permitted'));
        self::assertSame(MessagePermission::Disallowed, MessagePermission::from('disallowed'));
    }

    #[Test]
    public function testToMoodleValueMapsForcedTo0xC(): void
    {
        self::assertSame(0xC, MessagePermission::Forced->toMoodleValue());
        self::assertSame(12, MessagePermission::Forced->toMoodleValue());
    }

    #[Test]
    public function testToMoodleValueMapsPermittedTo0x8(): void
    {
        self::assertSame(0x8, MessagePermission::Permitted->toMoodleValue());
        self::assertSame(8, MessagePermission::Permitted->toMoodleValue());
    }

    #[Test]
    public function testToMoodleValueMapsDisallowedTo0x4(): void
    {
        self::assertSame(0x4, MessagePermission::Disallowed->toMoodleValue());
        self::assertSame(4, MessagePermission::Disallowed->toMoodleValue());
    }
}
