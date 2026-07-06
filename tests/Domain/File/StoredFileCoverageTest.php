<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Domain\File;

use Middag\Moodle\Domain\File\StoredFile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * StoredFile's only concrete members over the entity base are the table binding
 * and the directory predicate; every accessor is inherited from
 * AbstractMoodleEntity.
 *
 * @internal
 */
#[CoversClass(StoredFile::class)]
final class StoredFileCoverageTest extends TestCase
{
    #[Test]
    public function testTableIsFiles(): void
    {
        self::assertSame('files', StoredFile::getTable());
    }

    #[Test]
    public function testIsDirectoryIsTrueForTheDirectoryPlaceholderFilename(): void
    {
        $entity = StoredFile::fromRecord(['filename' => '.']);

        self::assertTrue($entity->isDirectory());
    }

    #[Test]
    public function testIsDirectoryIsFalseForARegularFile(): void
    {
        $entity = StoredFile::fromRecord(['filename' => 'report.pdf']);

        self::assertFalse($entity->isDirectory());
    }
}
