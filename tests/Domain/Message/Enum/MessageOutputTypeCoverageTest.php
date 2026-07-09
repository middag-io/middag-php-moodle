<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Domain\Message\Enum;

use Middag\Moodle\Domain\Message\Enum\MessageOutputType;
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
        self::assertSame('popup', MessageOutputType::Popup->value);
        self::assertSame('email', MessageOutputType::Email->value);
        self::assertSame('airnotifier', MessageOutputType::Airnotifier->value);
        self::assertSame('mobile', MessageOutputType::Mobile->value);
    }

    #[Test]
    public function testIsRealtimeIsTrueForPopupMobileAndAirnotifier(): void
    {
        self::assertTrue(MessageOutputType::Popup->isRealtime());
        self::assertTrue(MessageOutputType::Mobile->isRealtime());
        self::assertTrue(MessageOutputType::Airnotifier->isRealtime());
    }

    #[Test]
    public function testIsRealtimeIsFalseForEmail(): void
    {
        self::assertFalse(MessageOutputType::Email->isRealtime());
    }

    #[Test]
    public function testLabelCoversEveryCase(): void
    {
        self::assertSame('Web notification', MessageOutputType::Popup->label());
        self::assertSame('Email', MessageOutputType::Email->label());
        self::assertSame('Push notification', MessageOutputType::Airnotifier->label());
        self::assertSame('Mobile app', MessageOutputType::Mobile->label());
    }

    #[Test]
    public function testResolveReturnsCaseForKnownValue(): void
    {
        self::assertSame(MessageOutputType::Email, MessageOutputType::resolve('email'));
        self::assertSame(MessageOutputType::Popup, MessageOutputType::resolve('popup'));
    }

    #[Test]
    public function testResolveReturnsNullForUnknownValue(): void
    {
        self::assertNull(MessageOutputType::resolve('slack'));
    }
}
