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
        self::assertSame('forced', MessagePermission::FORCED->value);
        self::assertSame('permitted', MessagePermission::PERMITTED->value);
        self::assertSame('disallowed', MessagePermission::DISALLOWED->value);
    }

    #[Test]
    public function testFromResolvesEachBackingValue(): void
    {
        self::assertSame(MessagePermission::FORCED, MessagePermission::from('forced'));
        self::assertSame(MessagePermission::PERMITTED, MessagePermission::from('permitted'));
        self::assertSame(MessagePermission::DISALLOWED, MessagePermission::from('disallowed'));
    }

    #[Test]
    public function testToMoodleValueMapsForcedTo0xC(): void
    {
        self::assertSame(0xC, MessagePermission::FORCED->toMoodleValue());
        self::assertSame(12, MessagePermission::FORCED->toMoodleValue());
    }

    #[Test]
    public function testToMoodleValueMapsPermittedTo0x8(): void
    {
        self::assertSame(0x8, MessagePermission::PERMITTED->toMoodleValue());
        self::assertSame(8, MessagePermission::PERMITTED->toMoodleValue());
    }

    #[Test]
    public function testToMoodleValueMapsDisallowedTo0x4(): void
    {
        self::assertSame(0x4, MessagePermission::DISALLOWED->toMoodleValue());
        self::assertSame(4, MessagePermission::DISALLOWED->toMoodleValue());
    }
}
