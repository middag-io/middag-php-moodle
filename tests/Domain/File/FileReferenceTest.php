<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace MiddagMoodleTestsDomainile;

use Middag\Moodle\Domain\File\FileReference;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Stringable;

/**
 * @internal
 *
 * @coversNothing
 */
final class FileReferenceTest extends TestCase
{
    #[Test]
    public function canBeConstructedWithAllArgs(): void
    {
        $ref = new FileReference(
            contextid: 42,
            component: 'local_example',
            filearea: 'uploads',
            itemid: 7,
        );

        $this->assertSame(42, $ref->contextid);
        $this->assertSame('local_example', $ref->component);
        $this->assertSame('uploads', $ref->filearea);
        $this->assertSame(7, $ref->itemid);
    }

    #[Test]
    public function toStringReturnsColonSeparatedFormat(): void
    {
        $ref = new FileReference(
            contextid: 42,
            component: 'local_example',
            filearea: 'uploads',
            itemid: 7,
        );

        $this->assertSame('42:local_example:uploads:7', (string) $ref);
    }

    #[Test]
    public function implementsStringable(): void
    {
        $ref = new FileReference(contextid: 1, component: 'test', filearea: 'area', itemid: 0);
        $this->assertInstanceOf(Stringable::class, $ref);
    }

    #[Test]
    public function equalsReturnsTrueForSameValues(): void
    {
        $a = new FileReference(contextid: 42, component: 'local_example', filearea: 'uploads', itemid: 7);
        $b = new FileReference(contextid: 42, component: 'local_example', filearea: 'uploads', itemid: 7);
        $this->assertTrue($a->equals($b));
    }

    #[Test]
    public function equalsReturnsFalseForDifferentContextid(): void
    {
        $a = new FileReference(contextid: 42, component: 'local_example', filearea: 'uploads', itemid: 7);
        $b = new FileReference(contextid: 43, component: 'local_example', filearea: 'uploads', itemid: 7);
        $this->assertFalse($a->equals($b));
    }

    #[Test]
    public function equalsReturnsFalseForDifferentComponent(): void
    {
        $a = new FileReference(contextid: 42, component: 'local_example', filearea: 'uploads', itemid: 7);
        $b = new FileReference(contextid: 42, component: 'mod_assign', filearea: 'uploads', itemid: 7);
        $this->assertFalse($a->equals($b));
    }

    #[Test]
    public function equalsReturnsFalseForDifferentFilearea(): void
    {
        $a = new FileReference(contextid: 42, component: 'local_example', filearea: 'uploads', itemid: 7);
        $b = new FileReference(contextid: 42, component: 'local_example', filearea: 'content', itemid: 7);
        $this->assertFalse($a->equals($b));
    }

    #[Test]
    public function equalsReturnsFalseForDifferentItemid(): void
    {
        $a = new FileReference(contextid: 42, component: 'local_example', filearea: 'uploads', itemid: 7);
        $b = new FileReference(contextid: 42, component: 'local_example', filearea: 'uploads', itemid: 8);
        $this->assertFalse($a->equals($b));
    }

    #[Test]
    public function middagFactoryCreatesLocalMiddagReference(): void
    {
        $ref = FileReference::middag(contextid: 42, filearea: 'uploads', itemid: 7);
        $this->assertSame(42, $ref->contextid);
        $this->assertSame('local_example', $ref->component);
        $this->assertSame('uploads', $ref->filearea);
        $this->assertSame(7, $ref->itemid);
    }

    #[Test]
    public function middagFactoryWithZeroItemid(): void
    {
        $ref = FileReference::middag(contextid: 1, filearea: 'content', itemid: 0);
        $this->assertSame(0, $ref->itemid);
        $this->assertSame('1:local_example:content:0', (string) $ref);
    }

    #[Test]
    public function isReadonly(): void
    {
        $reflection = new ReflectionClass(FileReference::class);
        $this->assertTrue($reflection->isReadOnly());
    }

    #[Test]
    public function isFinal(): void
    {
        $reflection = new ReflectionClass(FileReference::class);
        $this->assertTrue($reflection->isFinal());
    }
}
