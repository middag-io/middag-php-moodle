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
use Middag\Moodle\Definition\FileAreaDefinition;
use Middag\Moodle\Domain\Context\ContextLevel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @internal
 */
#[CoversClass(FileAreaDefinition::class)]
final class FileAreaDefinitionCoverageTest extends TestCase
{
    #[Test]
    public function canBeConstructedWithNameOnly(): void
    {
        $area = new FileAreaDefinition(name: 'attachments');

        self::assertSame('attachments', $area->name);
    }

    #[Test]
    public function hasCorrectDefaults(): void
    {
        $area = new FileAreaDefinition(name: 'attachments');

        self::assertSame(ContextLevel::SYSTEM, $area->context_level);
        self::assertNull($area->handler);
        self::assertFalse($area->supports_preview);
        self::assertSame('', $area->description);
        self::assertNull($area->min_moodle);
        self::assertNull($area->max_moodle);
    }

    #[Test]
    public function canBeConstructedWithAllArgs(): void
    {
        $area = new FileAreaDefinition(
            name: 'gallery',
            context_level: ContextLevel::MODULE,
            handler: 'Some\Handler\Class',
            supports_preview: true,
            description: 'Image gallery files',
            min_moodle: '4.0',
            max_moodle: '4.5',
        );

        self::assertSame('gallery', $area->name);
        self::assertSame(ContextLevel::MODULE, $area->context_level);
        self::assertSame('Some\Handler\Class', $area->handler);
        self::assertTrue($area->supports_preview);
        self::assertSame('Image gallery files', $area->description);
        self::assertSame('4.0', $area->min_moodle);
        self::assertSame('4.5', $area->max_moodle);
    }

    #[Test]
    public function implementsDefinitionInterface(): void
    {
        $area = new FileAreaDefinition(name: 'attachments');

        self::assertInstanceOf(DefinitionInterface::class, $area);
    }

    #[Test]
    public function getNameReturnsAreaName(): void
    {
        $area = new FileAreaDefinition(name: 'attachments');

        self::assertSame('attachments', $area->getName());
    }

    #[Test]
    public function toMoodleArrayReturnsContextLevelAndPreviewFlag(): void
    {
        $area = new FileAreaDefinition(
            name: 'attachments',
            context_level: ContextLevel::MODULE,
            supports_preview: true,
        );

        $result = $area->toMoodleArray('local_example');

        self::assertSame(ContextLevel::MODULE->toMoodleValue(), $result['contextlevel']);
        self::assertSame(70, $result['contextlevel']);
        self::assertTrue($result['supports_preview']);
        self::assertSame(['contextlevel', 'supports_preview'], array_keys($result));
    }

    #[Test]
    public function toMoodleArrayReflectsFalsePreviewAndSystemContext(): void
    {
        $area = new FileAreaDefinition(name: 'attachments');

        $result = $area->toMoodleArray('local_example');

        self::assertSame(ContextLevel::SYSTEM->value, $result['contextlevel']);
        self::assertFalse($result['supports_preview']);
    }

    #[Test]
    public function getQualifiedNameWithNullExtensionReturnsBareName(): void
    {
        $area = new FileAreaDefinition(name: 'attachments');

        self::assertSame('attachments', $area->get_qualified_name());
        self::assertSame('attachments', $area->get_qualified_name());
    }

    #[Test]
    public function getQualifiedNameWithCoreExtensionReturnsBareName(): void
    {
        $area = new FileAreaDefinition(name: 'attachments');

        self::assertSame('attachments', $area->get_qualified_name('core'));
    }

    #[Test]
    public function getQualifiedNameWithCustomExtensionPrefixesTheName(): void
    {
        $area = new FileAreaDefinition(name: 'attachments');

        self::assertSame('myext_attachments', $area->get_qualified_name('myext'));
    }

    #[Test]
    public function isCompatibleReturnsTrueWithNoVersionConstraints(): void
    {
        $area = new FileAreaDefinition(name: 'attachments');

        self::assertTrue($area->isCompatible('4.5'));
        self::assertTrue($area->isCompatible('3.0'));
    }

    #[Test]
    public function isCompatibleRespectsMinMoodle(): void
    {
        $area = new FileAreaDefinition(name: 'attachments', min_moodle: '4.0');

        self::assertTrue($area->isCompatible('4.0'));
        self::assertTrue($area->isCompatible('4.5'));
        self::assertFalse($area->isCompatible('3.11'));
    }

    #[Test]
    public function isCompatibleRespectsMaxMoodle(): void
    {
        $area = new FileAreaDefinition(name: 'attachments', max_moodle: '4.5');

        self::assertTrue($area->isCompatible('4.5'));
        self::assertTrue($area->isCompatible('4.0'));
        self::assertFalse($area->isCompatible('4.6'));
    }

    #[Test]
    public function isCompatibleRespectsMinAndMaxMoodle(): void
    {
        $area = new FileAreaDefinition(name: 'attachments', min_moodle: '4.0', max_moodle: '4.5');

        self::assertTrue($area->isCompatible('4.0'));
        self::assertTrue($area->isCompatible('4.3'));
        self::assertTrue($area->isCompatible('4.5'));
        self::assertFalse($area->isCompatible('3.11'));
        self::assertFalse($area->isCompatible('4.6'));
    }

    #[Test]
    public function isReadonly(): void
    {
        $reflection = new ReflectionClass(FileAreaDefinition::class);

        self::assertTrue($reflection->isReadOnly());
    }

    #[Test]
    public function isFinal(): void
    {
        $reflection = new ReflectionClass(FileAreaDefinition::class);

        self::assertTrue($reflection->isFinal());
    }
}
