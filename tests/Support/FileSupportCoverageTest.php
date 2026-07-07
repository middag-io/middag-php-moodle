<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Support;

use file_storage;
use Middag\Moodle\Config\ComponentContext;
use Middag\Moodle\Domain\File\StoredFile;
use Middag\Moodle\Support\FileSupport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stored_file;

/**
 * @internal
 */
#[CoversClass(FileSupport::class)]
final class FileSupportCoverageTest extends TestCase
{
    private mixed $prevCfg;

    protected function setUp(): void
    {
        $this->prevCfg = $GLOBALS['CFG'] ?? null;

        $GLOBALS['CFG'] = (object) ['wwwroot' => 'https://moodle.test'];

        ComponentContext::configure('local_example', 'local_example_autoload');

        foreach ([
            '__middag_test_area_files',
            '__middag_test_get_file',
            '__middag_test_get_file_by_id',
            '__middag_test_created_file',
            '__middag_test_delete_area_result',
            '__middag_test_throw_area_files',
            '__middag_test_throw_get_file',
            '__middag_test_throw_get_file_by_id',
            '__middag_test_throw_create_file',
            '__middag_test_throw_delete_area',
        ] as $key) {
            unset($GLOBALS[$key]);
        }
    }

    protected function tearDown(): void
    {
        $GLOBALS['CFG'] = $this->prevCfg;
    }

    #[Test]
    public function testComponentReturnsConfiguredComponent(): void
    {
        self::assertSame('local_example', FileSupport::component());
    }

    #[Test]
    public function testGetStorageReturnsFileStorage(): void
    {
        self::assertInstanceOf(file_storage::class, FileSupport::getStorage());
    }

    #[Test]
    public function testGetFileReturnsFirstNonDotFile(): void
    {
        $GLOBALS['__middag_test_area_files'] = [
            '.' => new stored_file(['filename' => '.']),
            'f' => new stored_file(['filename' => 'real.txt']),
        ];

        $file = FileSupport::getFile(1, 'local_example', 'area', 0);

        self::assertInstanceOf(stored_file::class, $file);
        self::assertSame('real.txt', $file->get_filename());
    }

    #[Test]
    public function testGetFileReturnsValidImageWhenRequested(): void
    {
        $valid = new stored_file(['filename' => 'logo.png']);
        $valid->validImage = true;
        $GLOBALS['__middag_test_area_files'] = ['f' => $valid];

        self::assertSame($valid, FileSupport::getFile(1, 'local_example', 'area', 0, true));
    }

    #[Test]
    public function testGetFileReturnsFalseForInvalidImage(): void
    {
        $invalid = new stored_file(['filename' => 'broken.png']);
        $invalid->validImage = false;
        $GLOBALS['__middag_test_area_files'] = ['f' => $invalid];

        self::assertFalse(FileSupport::getFile(1, 'local_example', 'area', 0, true));
    }

    #[Test]
    public function testGetFileReturnsNullWhenOnlyDirectoryEntry(): void
    {
        $GLOBALS['__middag_test_area_files'] = ['.' => new stored_file(['filename' => '.'])];

        self::assertNull(FileSupport::getFile(1, 'local_example', 'area', 0));
    }

    #[Test]
    public function testGetFileReturnsNullWhenStorageThrows(): void
    {
        $GLOBALS['__middag_test_throw_area_files'] = true;

        self::assertNull(FileSupport::getFile(1, 'local_example', 'area', 0));
    }

    #[Test]
    public function testGetAreaFilesExcludesDirectories(): void
    {
        $dir = new stored_file(['filename' => '.']);
        $dir->directory = true;

        $file = new stored_file(['filename' => 'a.txt']);

        $GLOBALS['__middag_test_area_files'] = ['d' => $dir, 'f' => $file];

        $files = FileSupport::getAreaFiles(1, 'local_example', 'area', 0);

        self::assertCount(1, $files);
        self::assertSame('a.txt', $files[0]->get_filename());
    }

    #[Test]
    public function testGetAreaFilesReturnsEmptyArrayWhenStorageThrows(): void
    {
        $GLOBALS['__middag_test_throw_area_files'] = true;

        self::assertSame([], FileSupport::getAreaFiles(1, 'local_example', 'area', 0));
    }

    #[Test]
    public function testGetFileByPathReturnsFile(): void
    {
        $file = new stored_file(['filename' => 'x.txt']);
        $GLOBALS['__middag_test_get_file'] = $file;

        self::assertSame($file, FileSupport::getFileByPath(1, 'local_example', 'area', 0, '/', 'x.txt'));
    }

    #[Test]
    public function testGetFileByPathReturnsNullWhenAbsent(): void
    {
        $GLOBALS['__middag_test_get_file'] = false;

        self::assertNull(FileSupport::getFileByPath(1, 'local_example', 'area', 0, '/', 'x.txt'));
    }

    #[Test]
    public function testGetFileByPathReturnsNullWhenStorageThrows(): void
    {
        $GLOBALS['__middag_test_throw_get_file'] = true;

        self::assertNull(FileSupport::getFileByPath(1, 'local_example', 'area', 0, '/', 'x.txt'));
    }

    #[Test]
    public function testGetFileByIdReturnsFile(): void
    {
        $file = new stored_file(['id' => 5]);
        $GLOBALS['__middag_test_get_file_by_id'] = $file;

        self::assertSame($file, FileSupport::getFileById(5));
    }

    #[Test]
    public function testGetFileByIdReturnsNullWhenAbsent(): void
    {
        $GLOBALS['__middag_test_get_file_by_id'] = false;

        self::assertNull(FileSupport::getFileById(5));
    }

    #[Test]
    public function testGetFileByIdReturnsNullWhenStorageThrows(): void
    {
        $GLOBALS['__middag_test_throw_get_file_by_id'] = true;

        self::assertNull(FileSupport::getFileById(5));
    }

    #[Test]
    public function testCreateFileFromStoredWithExplicitFilename(): void
    {
        $created = new stored_file(['filename' => 'copy.txt']);
        $GLOBALS['__middag_test_created_file'] = $created;

        $result = FileSupport::createFileFromStored(new stored_file(), 1, 'local_example', 'area', 0, '/', 'copy.txt');

        self::assertSame($created, $result);
    }

    #[Test]
    public function testCreateFileFromStoredWithoutFilename(): void
    {
        $created = new stored_file(['filename' => 'orig.txt']);
        $GLOBALS['__middag_test_created_file'] = $created;

        $result = FileSupport::createFileFromStored(new stored_file(), 1, 'local_example', 'area', 0);

        self::assertSame($created, $result);
    }

    #[Test]
    public function testCreateFileFromStoredReturnsNullWhenStorageThrows(): void
    {
        $GLOBALS['__middag_test_throw_create_file'] = true;

        self::assertNull(FileSupport::createFileFromStored(new stored_file(), 1, 'local_example', 'area', 0));
    }

    #[Test]
    public function testCreateFileFromStringReturnsCreatedFile(): void
    {
        $created = new stored_file(['filename' => 's.txt']);
        $GLOBALS['__middag_test_created_file'] = $created;

        $result = FileSupport::createFileFromString(1, 'local_example', 'area', 0, '/', 's.txt', 'content');

        self::assertSame($created, $result);
    }

    #[Test]
    public function testCreateFileFromStringReturnsNullWhenStorageThrows(): void
    {
        $GLOBALS['__middag_test_throw_create_file'] = true;

        self::assertNull(FileSupport::createFileFromString(1, 'local_example', 'area', 0, '/', 's.txt', 'content'));
    }

    #[Test]
    public function testCreateFileFromPathnameReturnsCreatedFile(): void
    {
        $created = new stored_file(['filename' => 'p.txt']);
        $GLOBALS['__middag_test_created_file'] = $created;

        $result = FileSupport::createFileFromPathname(1, 'local_example', 'area', 0, '/', 'p.txt', '/tmp/p.txt');

        self::assertSame($created, $result);
    }

    #[Test]
    public function testCreateFileFromPathnameReturnsNullWhenStorageThrows(): void
    {
        $GLOBALS['__middag_test_throw_create_file'] = true;

        self::assertNull(FileSupport::createFileFromPathname(1, 'local_example', 'area', 0, '/', 'p.txt', '/tmp/p.txt'));
    }

    #[Test]
    public function testDeleteFileReturnsResult(): void
    {
        $file = new stored_file();
        $file->deleteResult = true;

        self::assertTrue(FileSupport::deleteFile($file));
    }

    #[Test]
    public function testDeleteFileReturnsFalseWhenDeleteThrows(): void
    {
        $file = new stored_file();
        $file->throwOnDelete = true;

        self::assertFalse(FileSupport::deleteFile($file));
    }

    #[Test]
    public function testDeleteFileByIdReturnsFalseWhenFileMissing(): void
    {
        $GLOBALS['__middag_test_get_file_by_id'] = false;

        self::assertFalse(FileSupport::deleteFileById(5));
    }

    #[Test]
    public function testDeleteFileByIdDeletesExistingFile(): void
    {
        $file = new stored_file(['id' => 5]);
        $GLOBALS['__middag_test_get_file_by_id'] = $file;

        self::assertTrue(FileSupport::deleteFileById(5));
    }

    #[Test]
    public function testDeleteFileByIdReturnsFalseWhenDeleteThrows(): void
    {
        $file = new stored_file(['id' => 5]);
        $file->throwOnDelete = true;
        $GLOBALS['__middag_test_get_file_by_id'] = $file;

        self::assertFalse(FileSupport::deleteFileById(5));
    }

    #[Test]
    public function testDeleteAreaFilesReturnsResult(): void
    {
        $GLOBALS['__middag_test_delete_area_result'] = true;

        self::assertTrue(FileSupport::deleteAreaFiles(1, 'local_example', 'area'));
    }

    #[Test]
    public function testDeleteAreaFilesReturnsFalseWhenStorageThrows(): void
    {
        $GLOBALS['__middag_test_throw_delete_area'] = true;

        self::assertFalse(FileSupport::deleteAreaFiles(1, 'local_example', 'area'));
    }

    #[Test]
    public function testGetFileUrlReturnsPluginfileUrl(): void
    {
        $file = new stored_file(['contextid' => 12, 'component' => 'local_example', 'filearea' => 'area', 'itemid' => 3, 'filepath' => '/', 'filename' => 'doc.pdf']);

        $url = FileSupport::getFileUrl($file);

        self::assertStringContainsString('pluginfile.php', $url);
        self::assertStringContainsString('doc.pdf', $url);
    }

    #[Test]
    public function testGetFileUrlFallsBackToManualUrlOnError(): void
    {
        $file = new stored_file(['contextid' => 12, 'component' => 'local_example', 'filearea' => 'area', 'itemid' => 3, 'filepath' => '/', 'filename' => 'doc.pdf']);
        $file->throwContextOnce = true;

        $url = FileSupport::getFileUrl($file, true);

        self::assertStringStartsWith('https://moodle.test/pluginfile.php/', $url);
        self::assertStringContainsString('forcedownload=1', $url);
    }

    #[Test]
    public function testHasFilesReturnsTrueWhenNonDirectoryPresent(): void
    {
        $GLOBALS['__middag_test_area_files'] = ['f' => new stored_file(['filename' => 'a.txt'])];

        self::assertTrue(FileSupport::hasFiles(1, 'local_example', 'area', 0));
    }

    #[Test]
    public function testHasFilesReturnsFalseWhenOnlyDirectories(): void
    {
        $dir = new stored_file(['filename' => '.']);
        $dir->directory = true;
        $GLOBALS['__middag_test_area_files'] = ['d' => $dir];

        self::assertFalse(FileSupport::hasFiles(1, 'local_example', 'area', 0));
    }

    #[Test]
    public function testHasFilesReturnsFalseWhenStorageThrows(): void
    {
        $GLOBALS['__middag_test_throw_area_files'] = true;

        self::assertFalse(FileSupport::hasFiles(1, 'local_example', 'area', 0));
    }

    #[Test]
    public function testGetAreaSizeSumsFileSizes(): void
    {
        $GLOBALS['__middag_test_area_files'] = [
            'a' => new stored_file(['filename' => 'a', 'filesize' => 100]),
            'b' => new stored_file(['filename' => 'b', 'filesize' => 250]),
        ];

        self::assertSame(350, FileSupport::getAreaSize(1, 'local_example', 'area', 0));
    }

    #[Test]
    public function testGetAreaSizeReturnsZeroWhenSizeReadThrows(): void
    {
        $file = new stored_file(['filename' => 'a']);
        $file->throwOnFilesize = true;
        $GLOBALS['__middag_test_area_files'] = ['a' => $file];

        self::assertSame(0, FileSupport::getAreaSize(1, 'local_example', 'area', 0));
    }

    #[Test]
    public function testCountAreaFilesCountsNonDirectoryFiles(): void
    {
        $GLOBALS['__middag_test_area_files'] = [
            'a' => new stored_file(['filename' => 'a']),
            'b' => new stored_file(['filename' => 'b']),
        ];

        self::assertSame(2, FileSupport::countAreaFiles(1, 'local_example', 'area', 0));
    }

    #[Test]
    public function testIsValidImageReturnsResult(): void
    {
        $file = new stored_file();
        $file->validImage = true;

        self::assertTrue(FileSupport::isValidImage($file));
    }

    #[Test]
    public function testIsValidImageReturnsFalseWhenCheckThrows(): void
    {
        $file = new stored_file();
        $file->throwOnValidImage = true;

        self::assertFalse(FileSupport::isValidImage($file));
    }

    #[Test]
    public function testGetContentReturnsFileContent(): void
    {
        $file = new stored_file(['content' => 'HELLO']);

        self::assertSame('HELLO', FileSupport::getContent($file));
    }

    #[Test]
    public function testGetContentReturnsNullWhenReadThrows(): void
    {
        $file = new stored_file();
        $file->throwOnContent = true;

        self::assertNull(FileSupport::getContent($file));
    }

    #[Test]
    public function testGetContentFileHandleReturnsHandle(): void
    {
        $handle = FileSupport::getContentFileHandle(new stored_file());

        self::assertIsResource($handle);
        fclose($handle);
    }

    #[Test]
    public function testGetContentFileHandleReturnsNullWhenReadThrows(): void
    {
        $file = new stored_file();
        $file->throwOnContent = true;

        self::assertNull(FileSupport::getContentFileHandle($file));
    }

    #[Test]
    public function testGetFileEntityMapsToStoredFileEntity(): void
    {
        $GLOBALS['__middag_test_get_file'] = new stored_file(['component' => 'local_example', 'filename' => 'doc.txt']);

        $entity = FileSupport::getFileEntity(1, 'local_example', 'area', 0, '/', 'doc.txt');

        self::assertInstanceOf(StoredFile::class, $entity);
        self::assertSame('local_example', $entity->get_component());
    }

    #[Test]
    public function testGetFileEntityReturnsNullWhenFileMissing(): void
    {
        $GLOBALS['__middag_test_get_file'] = false;

        self::assertNull(FileSupport::getFileEntity(1, 'local_example', 'area', 0, '/', 'doc.txt'));
    }

    #[Test]
    public function testGetFileEntityReturnsNullForDirectory(): void
    {
        $dir = new stored_file(['filename' => '.']);
        $dir->directory = true;
        $GLOBALS['__middag_test_get_file'] = $dir;

        self::assertNull(FileSupport::getFileEntity(1, 'local_example', 'area', 0, '/', '.'));
    }

    #[Test]
    public function testGetFileEntityReturnsNullWhenStorageThrows(): void
    {
        $GLOBALS['__middag_test_throw_get_file'] = true;

        self::assertNull(FileSupport::getFileEntity(1, 'local_example', 'area', 0, '/', 'doc.txt'));
    }

    #[Test]
    public function testGetAreaFilesTypedMapsEntities(): void
    {
        $GLOBALS['__middag_test_area_files'] = [
            'a' => new stored_file(['id' => 1, 'filename' => 'a.txt']),
            'b' => new stored_file(['id' => 2, 'filename' => 'b.txt']),
        ];

        $entities = FileSupport::getAreaFilesTyped(1, 'local_example', 'area', 0);

        self::assertCount(2, $entities);
        self::assertContainsOnlyInstancesOf(StoredFile::class, $entities);
    }

    #[Test]
    public function testGetAreaFilesTypedReturnsEmptyArrayWhenStorageThrows(): void
    {
        $GLOBALS['__middag_test_throw_area_files'] = true;

        self::assertSame([], FileSupport::getAreaFilesTyped(1, 'local_example', 'area', 0));
    }
}
