<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Dto;

use JsonSerializable;
use Middag\Moodle\Dto\EventDto;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @internal
 *
 * @coversNothing
 */
final class EventDtoTest extends TestCase
{
    #[Test]
    public function canBeConstructedWithRequiredArgs(): void
    {
        $dto = new EventDto(
            fqcn: '\core\event\user_created',
            displayname: 'User created',
        );

        $this->assertSame('\core\event\user_created', $dto->fqcn);
        $this->assertSame('User created', $dto->displayname);
    }

    #[Test]
    public function optionalFieldsHaveCorrectDefaults(): void
    {
        $dto = new EventDto(
            fqcn: '\core\event\user_created',
            displayname: 'User created',
        );

        $this->assertSame(0, $dto->edulevel);
        $this->assertSame('core', $dto->pluginname);
        $this->assertSame('core', $dto->plugintype);
        $this->assertSame('', $dto->plugindisplayname);
    }

    #[Test]
    public function canBeConstructedWithAllArgs(): void
    {
        $dto = new EventDto(
            fqcn: '\mod_forum\event\discussion_created',
            displayname: 'Discussion created',
            edulevel: 1,
            pluginname: 'forum',
            plugintype: 'mod',
            plugindisplayname: 'Forum',
        );

        $this->assertSame('\mod_forum\event\discussion_created', $dto->fqcn);
        $this->assertSame('Discussion created', $dto->displayname);
        $this->assertSame(1, $dto->edulevel);
        $this->assertSame('forum', $dto->pluginname);
        $this->assertSame('mod', $dto->plugintype);
        $this->assertSame('Forum', $dto->plugindisplayname);
    }

    #[Test]
    public function toArrayReturnsCompleteRepresentation(): void
    {
        $dto = new EventDto(
            fqcn: '\mod_forum\event\discussion_created',
            displayname: 'Discussion created',
            edulevel: 1,
            pluginname: 'forum',
            plugintype: 'mod',
            plugindisplayname: 'Forum',
        );

        $expected = [
            'fqcn' => '\mod_forum\event\discussion_created',
            'displayname' => 'Discussion created',
            'edulevel' => 1,
            'pluginname' => 'forum',
            'plugintype' => 'mod',
            'plugindisplayname' => 'Forum',
        ];

        $this->assertSame($expected, $dto->toArray());
    }

    #[Test]
    public function toArrayWithDefaults(): void
    {
        $dto = new EventDto(
            fqcn: '\core\event\user_created',
            displayname: 'User created',
        );

        $expected = [
            'fqcn' => '\core\event\user_created',
            'displayname' => 'User created',
            'edulevel' => 0,
            'pluginname' => 'core',
            'plugintype' => 'core',
            'plugindisplayname' => '',
        ];

        $this->assertSame($expected, $dto->toArray());
    }

    #[Test]
    public function implementsJsonSerializable(): void
    {
        $dto = new EventDto(
            fqcn: '\core\event\user_created',
            displayname: 'User created',
        );

        // EventDto extends AbstractDto which implements JsonSerializable
        $this->assertInstanceOf(JsonSerializable::class, $dto);
    }

    #[Test]
    public function jsonSerializeReturnsSameAsToArray(): void
    {
        $dto = new EventDto(
            fqcn: '\core\event\user_created',
            displayname: 'User created',
            edulevel: 2,
            pluginname: 'assign',
            plugintype: 'mod',
            plugindisplayname: 'Assignment',
        );

        $this->assertSame($dto->toArray(), $dto->jsonSerialize());
    }

    #[Test]
    public function isFinal(): void
    {
        $reflection = new ReflectionClass(EventDto::class);
        $this->assertTrue($reflection->isFinal());
    }
}
