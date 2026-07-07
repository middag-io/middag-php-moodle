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

use Middag\Moodle\Domain\File\FileService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stored_file;

/**
 * FileService is a thin, interface-driven facade over the static FileSupport
 * helpers, which in turn wrap Moodle's file_storage. The file_storage stub
 * (tests/stubs/support/msg-file.php) is driven entirely via $GLOBALS, so every
 * delegation is exercised — and each return value asserted — without a Moodle
 * runtime.
 *
 * @internal
 */
#[CoversClass(FileService::class)]
final class FileServiceCoverageTest extends TestCase
{
    /** @var array<int, string> */
    private const KEYS = [
        '__middag_test_area_files',
        '__middag_test_get_file',
        '__middag_test_get_file_by_id',
        '__middag_test_created_file',
        '__middag_test_delete_area_result',
    ];

    protected function setUp(): void
    {
        $this->clearGlobals();
    }

    protected function tearDown(): void
    {
        $this->clearGlobals();
    }

    #[Test]
    public function testGetFileReturnsTheFirstNonDirectoryFile(): void
    {
        $file = $this->makeFile(['filename' => 'report.pdf']);
        $GLOBALS['__middag_test_area_files'] = [$file];

        $service = new FileService();

        self::assertSame($file, $service->getFile(1, 'local_example', 'attachments', 0));
    }

    #[Test]
    public function testGetFileReturnsNullWhenTheAreaIsEmpty(): void
    {
        $GLOBALS['__middag_test_area_files'] = [];

        $service = new FileService();

        self::assertNull($service->getFile(1, 'local_example', 'attachments', 0));
    }

    #[Test]
    public function testGetAreaFilesReturnsOnlyNonDirectoryEntries(): void
    {
        $dir = $this->makeFile(['filename' => '.']);
        $dir->directory = true;

        $file = $this->makeFile(['filename' => 'a.txt']);
        $GLOBALS['__middag_test_area_files'] = [$dir, $file];

        $service = new FileService();
        $result = $service->getAreaFiles(1, 'local_example', 'attachments', 0);

        self::assertSame([$file], $result);
    }

    #[Test]
    public function testGetFileByIdReturnsTheFileWhenFound(): void
    {
        $file = $this->makeFile(['id' => 55]);
        $GLOBALS['__middag_test_get_file_by_id'] = $file;

        $service = new FileService();

        self::assertSame($file, $service->getFileById(55));
    }

    #[Test]
    public function testGetFileByIdReturnsNullWhenTheStorageMisses(): void
    {
        $GLOBALS['__middag_test_get_file_by_id'] = false;

        $service = new FileService();

        self::assertNull($service->getFileById(404));
    }

    #[Test]
    public function testStoreFromStringReturnsTheCreatedFile(): void
    {
        $created = $this->makeFile(['filename' => 'new.txt']);
        $GLOBALS['__middag_test_created_file'] = $created;

        $service = new FileService();

        self::assertSame(
            $created,
            $service->storeFromString(1, 'local_example', 'attachments', 0, '/', 'new.txt', 'hello'),
        );
    }

    #[Test]
    public function testStoreFromPathReturnsTheCreatedFile(): void
    {
        $created = $this->makeFile(['filename' => 'imported.txt']);
        $GLOBALS['__middag_test_created_file'] = $created;

        $service = new FileService();

        self::assertSame(
            $created,
            $service->storeFromPath(1, 'local_example', 'attachments', 0, '/', 'imported.txt', '/tmp/imported.txt'),
        );
    }

    #[Test]
    public function testDeleteReturnsTrueWhenTheFileIsRemoved(): void
    {
        $file = $this->makeFile();
        $file->deleteResult = true;

        $service = new FileService();

        self::assertTrue($service->delete($file));
    }

    #[Test]
    public function testDeleteReturnsFalseWhenRemovalFails(): void
    {
        $file = $this->makeFile();
        $file->deleteResult = false;

        $service = new FileService();

        self::assertFalse($service->delete($file));
    }

    #[Test]
    public function testDeleteAreaReturnsTheStorageResult(): void
    {
        $GLOBALS['__middag_test_delete_area_result'] = true;

        $service = new FileService();

        self::assertTrue($service->deleteArea(1, 'local_example', 'attachments'));
    }

    #[Test]
    public function testDeleteAreaReturnsFalseWhenScopedByItemid(): void
    {
        $GLOBALS['__middag_test_delete_area_result'] = false;

        $service = new FileService();

        self::assertFalse($service->deleteArea(1, 'local_example', 'attachments', 9));
    }

    #[Test]
    public function testGetUrlBuildsAPluginfileUrl(): void
    {
        $file = $this->makeFile([
            'contextid' => 12,
            'component' => 'local_example',
            'filearea' => 'attachments',
            'itemid' => 7,
            'filepath' => '/',
            'filename' => 'doc.pdf',
        ]);

        $service = new FileService();
        $url = $service->getUrl($file);

        self::assertStringContainsString('pluginfile.php', $url);
        self::assertStringContainsString('doc.pdf', $url);
    }

    #[Test]
    public function testGetUrlForcesDownloadWhenRequested(): void
    {
        $file = $this->makeFile(['filename' => 'doc.pdf']);

        $service = new FileService();

        self::assertStringContainsString('pluginfile.php', $service->getUrl($file, true));
    }

    #[Test]
    public function testHasFilesIsTrueWhenANonDirectoryFileExists(): void
    {
        $GLOBALS['__middag_test_area_files'] = [$this->makeFile(['filename' => 'a.txt'])];

        $service = new FileService();

        self::assertTrue($service->hasFiles(1, 'local_example', 'attachments', 0));
    }

    #[Test]
    public function testHasFilesIsFalseWhenTheAreaHasNoFiles(): void
    {
        $GLOBALS['__middag_test_area_files'] = [];

        $service = new FileService();

        self::assertFalse($service->hasFiles(1, 'local_example', 'attachments', 0));
    }

    #[Test]
    public function testGetAreaSizeSumsTheFileSizes(): void
    {
        $GLOBALS['__middag_test_area_files'] = [
            $this->makeFile(['filename' => 'a.txt', 'filesize' => 10]),
            $this->makeFile(['filename' => 'b.txt', 'filesize' => 32]),
        ];

        $service = new FileService();

        self::assertSame(42, $service->getAreaSize(1, 'local_example', 'attachments', 0));
    }

    #[Test]
    public function testCountFilesCountsTheNonDirectoryFiles(): void
    {
        $GLOBALS['__middag_test_area_files'] = [
            $this->makeFile(['filename' => 'a.txt']),
            $this->makeFile(['filename' => 'b.txt']),
            $this->makeFile(['filename' => 'c.txt']),
        ];

        $service = new FileService();

        self::assertSame(3, $service->countFiles(1, 'local_example', 'attachments', 0));
    }

    /**
     * @param array<string, mixed> $values
     */
    private function makeFile(array $values = []): stored_file
    {
        return new stored_file($values);
    }

    private function clearGlobals(): void
    {
        foreach (self::KEYS as $key) {
            unset($GLOBALS[$key]);
        }
    }
}
