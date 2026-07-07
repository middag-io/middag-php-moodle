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

use Middag\Moodle\Domain\Message\MessageOutputType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * MessageOutputType is a pure backed enum over Moodle's output processor names.
 * Each case, every match arm of label(), the realtime membership test and the
 * resolve() known/unknown branches are exercised directly.
 *
 * @internal
 */
#[CoversClass(MessageOutputType::class)]
final class MessageOutputTypeCoverageTest extends TestCase
{
    #[Test]
    public function testBackingValues(): void
    {
        self::assertSame('popup', MessageOutputType::POPUP->value);
        self::assertSame('email', MessageOutputType::EMAIL->value);
        self::assertSame('airnotifier', MessageOutputType::AIRNOTIFIER->value);
        self::assertSame('mobile', MessageOutputType::MOBILE->value);
    }

    #[Test]
    public function testIsRealtimeIsTrueForPopupMobileAndAirnotifier(): void
    {
        self::assertTrue(MessageOutputType::POPUP->isRealtime());
        self::assertTrue(MessageOutputType::MOBILE->isRealtime());
        self::assertTrue(MessageOutputType::AIRNOTIFIER->isRealtime());
    }

    #[Test]
    public function testIsRealtimeIsFalseForEmail(): void
    {
        self::assertFalse(MessageOutputType::EMAIL->isRealtime());
    }

    #[Test]
    public function testLabelCoversEveryCase(): void
    {
        self::assertSame('Web notification', MessageOutputType::POPUP->label());
        self::assertSame('Email', MessageOutputType::EMAIL->label());
        self::assertSame('Push notification', MessageOutputType::AIRNOTIFIER->label());
        self::assertSame('Mobile app', MessageOutputType::MOBILE->label());
    }

    #[Test]
    public function testResolveReturnsCaseForKnownValue(): void
    {
        self::assertSame(MessageOutputType::EMAIL, MessageOutputType::resolve('email'));
        self::assertSame(MessageOutputType::POPUP, MessageOutputType::resolve('popup'));
    }

    #[Test]
    public function testResolveReturnsNullForUnknownValue(): void
    {
        self::assertNull(MessageOutputType::resolve('slack'));
    }
}
