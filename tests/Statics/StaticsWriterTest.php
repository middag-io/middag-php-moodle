<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Statics;

use FilesystemIterator;
use Middag\Moodle\Statics\StaticsWriter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * @internal
 */
#[CoversClass(StaticsWriter::class)]
final class StaticsWriterTest extends TestCase
{
    private string $tmpRoot;

    protected function setUp(): void
    {
        $this->tmpRoot = sys_get_temp_dir() . '/middag-statics-writer-' . uniqid('', true);
        mkdir($this->tmpRoot, 0o777, true);
    }

    protected function tearDown(): void
    {
        $this->deleteDir($this->tmpRoot);
    }

    public function testWriteCreatesFileAndReturnsTrue(): void
    {
        $path = $this->tmpRoot . '/db/access.php';
        $writer = new StaticsWriter();

        self::assertTrue($writer->write($path, "<?php\n// content\n"));
        self::assertFileExists($path);
        self::assertSame("<?php\n// content\n", file_get_contents($path));
    }

    public function testWriteIdempotentReturnsFalseWhenContentMatches(): void
    {
        $path = $this->tmpRoot . '/db/messages.php';
        $writer = new StaticsWriter();

        $writer->write($path, '<?php content');

        $mtime = filemtime($path);
        clearstatcache(true, $path);

        usleep(10_000);
        self::assertFalse($writer->write($path, '<?php content'));
        clearstatcache(true, $path);
        self::assertSame($mtime, filemtime($path), 'File must not be touched when content unchanged.');
    }

    public function testDryRunDoesNotCreateFileButReturnsTrue(): void
    {
        $path = $this->tmpRoot . '/db/services.php';
        $writer = new StaticsWriter(dryRun: true);

        self::assertTrue($writer->write($path, '<?php content'));
        self::assertFileDoesNotExist($path);
    }

    public function testWriteCreatesMissingParentDirectories(): void
    {
        $path = $this->tmpRoot . '/deeply/nested/path/db/caches.php';
        $writer = new StaticsWriter();

        self::assertTrue($writer->write($path, '<?php content'));
        self::assertFileExists($path);
    }

    private function deleteDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iter as $entry) {
            if ($entry->isDir()) {
                @rmdir($entry->getPathname());
            } else {
                @unlink($entry->getPathname());
            }
        }
        @rmdir($dir);
    }
}
