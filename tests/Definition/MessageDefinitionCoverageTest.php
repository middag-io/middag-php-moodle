<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Definition;

use Middag\Moodle\Definition\Contract\DefinitionInterface;
use Middag\Moodle\Definition\MessageDefinition;
use Middag\Moodle\Domain\Message\MessagePermission;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * MessageDefinition is a readonly value object describing a message provider for
 * db/messages.php. It has no Moodle runtime dependency: toMoodleArray() folds the
 * popup/email MessagePermission enums onto their Moodle int values, isCompatible()
 * gates on version_compare against min/max, and getName() echoes the provider key.
 *
 * @internal
 */
#[CoversClass(MessageDefinition::class)]
final class MessageDefinitionCoverageTest extends TestCase
{
    #[Test]
    public function constructorDefaultsPermissionsToPermittedAndVersionsToNull(): void
    {
        $definition = new MessageDefinition(name: 'submission');

        self::assertSame('submission', $definition->name);
        self::assertSame(MessagePermission::Permitted, $definition->popup);
        self::assertSame(MessagePermission::Permitted, $definition->email);
        self::assertNull($definition->min_moodle);
        self::assertNull($definition->max_moodle);
    }

    #[Test]
    public function constructorRetainsAllProvidedArguments(): void
    {
        $definition = new MessageDefinition(
            name: 'grading',
            popup: MessagePermission::Forced,
            email: MessagePermission::Disallowed,
            min_moodle: '4.0',
            max_moodle: '4.5',
        );

        self::assertSame('grading', $definition->name);
        self::assertSame(MessagePermission::Forced, $definition->popup);
        self::assertSame(MessagePermission::Disallowed, $definition->email);
        self::assertSame('4.0', $definition->min_moodle);
        self::assertSame('4.5', $definition->max_moodle);
    }

    #[Test]
    public function implementsDefinitionInterface(): void
    {
        self::assertInstanceOf(DefinitionInterface::class, new MessageDefinition(name: 'submission'));
    }

    #[Test]
    public function getNameReturnsTheProviderName(): void
    {
        $definition = new MessageDefinition(name: 'reminder');

        self::assertSame('reminder', $definition->getName());
    }

    #[Test]
    public function toMoodleArrayWrapsDefaultPermittedPermissionsAsMoodleValues(): void
    {
        $definition = new MessageDefinition(name: 'submission');

        self::assertSame(
            ['defaults' => ['popup' => 8, 'email' => 8]],
            $definition->toMoodleArray('local_example'),
        );
    }

    #[Test]
    public function toMoodleArrayFoldsEachPermissionOntoItsMoodleValue(): void
    {
        $definition = new MessageDefinition(
            name: 'grading',
            popup: MessagePermission::Forced,
            email: MessagePermission::Disallowed,
        );

        self::assertSame(
            ['defaults' => ['popup' => 12, 'email' => 4]],
            $definition->toMoodleArray('mod_forum'),
        );
    }

    #[Test]
    public function isCompatibleReturnsTrueWhenNoVersionConstraintsAreSet(): void
    {
        $definition = new MessageDefinition(name: 'submission');

        self::assertTrue($definition->isCompatible('4.5'));
        self::assertTrue($definition->isCompatible('1.0'));
    }

    #[Test]
    public function isCompatibleReturnsFalseWhenVersionIsBelowMinMoodle(): void
    {
        $definition = new MessageDefinition(name: 'submission', min_moodle: '4.0');

        self::assertFalse($definition->isCompatible('3.11'));
    }

    #[Test]
    public function isCompatibleReturnsTrueWhenVersionMeetsMinMoodle(): void
    {
        $definition = new MessageDefinition(name: 'submission', min_moodle: '4.0');

        self::assertTrue($definition->isCompatible('4.0'));
        self::assertTrue($definition->isCompatible('4.5'));
    }

    #[Test]
    public function isCompatibleReturnsFalseWhenVersionIsAboveMaxMoodle(): void
    {
        $definition = new MessageDefinition(name: 'submission', max_moodle: '4.5');

        self::assertFalse($definition->isCompatible('4.6'));
    }

    #[Test]
    public function isCompatibleReturnsTrueWhenVersionIsWithinMaxMoodle(): void
    {
        $definition = new MessageDefinition(name: 'submission', max_moodle: '4.5');

        self::assertTrue($definition->isCompatible('4.5'));
        self::assertTrue($definition->isCompatible('4.0'));
    }

    #[Test]
    public function isCompatibleRespectsBothBoundsOfTheVersionWindow(): void
    {
        $definition = new MessageDefinition(name: 'submission', min_moodle: '4.0', max_moodle: '4.5');

        self::assertTrue($definition->isCompatible('4.3'));
        self::assertFalse($definition->isCompatible('3.11'));
        self::assertFalse($definition->isCompatible('4.6'));
    }
}
