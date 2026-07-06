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

use Middag\Moodle\Domain\Message\NotificationDto;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use stdClass;

/**
 * NotificationDto is a pure value object over the framework AbstractDto. The
 * promoted-constructor defaults, the camelCase→snake_case toArray() mapping,
 * the stdClass projection (toObject) and the inherited jsonSerialize() are all
 * exercised on locally instantiated objects.
 *
 * @internal
 */
#[CoversClass(NotificationDto::class)]
final class NotificationDtoCoverageTest extends TestCase
{
    #[Test]
    public function testConstructorAppliesDefaults(): void
    {
        $dto = new NotificationDto(
            component: 'local_example',
            name: 'welcome',
            useridTo: 42,
            subject: 'Hello',
            fullMessage: 'Full text',
            fullMessageHtml: '<p>Full text</p>',
        );

        self::assertSame('local_example', $dto->component);
        self::assertSame('welcome', $dto->name);
        self::assertSame(42, $dto->useridTo);
        self::assertSame('Hello', $dto->subject);
        self::assertSame('Full text', $dto->fullMessage);
        self::assertSame('<p>Full text</p>', $dto->fullMessageHtml);
        self::assertSame('', $dto->shortMessage);
        self::assertNull($dto->useridFrom);
        self::assertNull($dto->contextUrl);
        self::assertNull($dto->contextUrlName);
        self::assertNull($dto->courseid);
    }

    #[Test]
    public function testConstructorAcceptsAllArguments(): void
    {
        $dto = new NotificationDto(
            component: 'mod_unidade',
            name: 'reminder',
            useridTo: 7,
            subject: 'Subject',
            fullMessage: 'Body',
            fullMessageHtml: '<b>Body</b>',
            shortMessage: 'Short',
            useridFrom: 3,
            contextUrl: 'https://example.test/view.php',
            contextUrlName: 'Open activity',
            courseid: 12,
        );

        self::assertSame('mod_unidade', $dto->component);
        self::assertSame('reminder', $dto->name);
        self::assertSame(7, $dto->useridTo);
        self::assertSame('Short', $dto->shortMessage);
        self::assertSame(3, $dto->useridFrom);
        self::assertSame('https://example.test/view.php', $dto->contextUrl);
        self::assertSame('Open activity', $dto->contextUrlName);
        self::assertSame(12, $dto->courseid);
    }

    #[Test]
    public function testToArrayMapsCamelCasePropertiesToSnakeCaseKeys(): void
    {
        $dto = new NotificationDto(
            component: 'local_example',
            name: 'reminder',
            useridTo: 7,
            subject: 'Subject',
            fullMessage: 'Body',
            fullMessageHtml: '<b>Body</b>',
            shortMessage: 'Short',
            useridFrom: 3,
            contextUrl: 'https://example.test',
            contextUrlName: 'Open',
            courseid: 12,
        );

        self::assertSame([
            'component' => 'local_example',
            'name' => 'reminder',
            'userid_to' => 7,
            'subject' => 'Subject',
            'full_message' => 'Body',
            'full_message_html' => '<b>Body</b>',
            'short_message' => 'Short',
            'userid_from' => 3,
            'context_url' => 'https://example.test',
            'context_url_name' => 'Open',
            'courseid' => 12,
        ], $dto->toArray());
    }

    #[Test]
    public function testJsonSerializeDelegatesToToArray(): void
    {
        $dto = new NotificationDto(
            component: 'local_example',
            name: 'welcome',
            useridTo: 42,
            subject: 'Hello',
            fullMessage: 'Full',
            fullMessageHtml: '<p>Full</p>',
        );

        self::assertSame($dto->toArray(), $dto->jsonSerialize());
    }

    #[Test]
    public function testToObjectProjectsEverySnakeCaseKeyOntoStdClass(): void
    {
        $dto = new NotificationDto(
            component: 'local_example',
            name: 'welcome',
            useridTo: 42,
            subject: 'Hello',
            fullMessage: 'Full',
            fullMessageHtml: '<p>Full</p>',
            shortMessage: 'Short',
        );

        $obj = $dto->toObject();

        self::assertInstanceOf(stdClass::class, $obj);
        self::assertSame('local_example', $obj->component);
        self::assertSame('welcome', $obj->name);
        self::assertSame(42, $obj->userid_to);
        self::assertSame('Hello', $obj->subject);
        self::assertSame('Full', $obj->full_message);
        self::assertSame('<p>Full</p>', $obj->full_message_html);
        self::assertSame('Short', $obj->short_message);
        self::assertNull($obj->userid_from);
        self::assertNull($obj->context_url);
        self::assertNull($obj->context_url_name);
        self::assertNull($obj->courseid);
        self::assertEquals((object) $dto->toArray(), $obj);
    }

    #[Test]
    public function testIsFinal(): void
    {
        self::assertTrue((new ReflectionClass(NotificationDto::class))->isFinal());
    }
}
