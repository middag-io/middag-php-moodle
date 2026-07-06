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
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Stringable;

/**
 * Line-coverage complement for StaticsWriter, driving every branch of write():
 * the idempotent no-op, dry-run, the real write (with and without parent-dir
 * creation), and both RuntimeException failure paths. A recording PSR-3 logger
 * lets the debug/info log lines be asserted as observable behaviour.
 *
 * @internal
 */
#[CoversClass(StaticsWriter::class)]
final class StaticsWriterCoverageTest extends TestCase
{
    private string $tmpRoot;

    protected function setUp(): void
    {
        $this->tmpRoot = sys_get_temp_dir() . '/middag-statics-writer-cov-' . uniqid('', true);
        mkdir($this->tmpRoot, 0o777, true);
    }

    protected function tearDown(): void
    {
        $this->deleteDir($this->tmpRoot);
    }

    #[Test]
    public function testWriteCreatesFileWhenParentDirectoryAlreadyExists(): void
    {
        // $dir === tmpRoot which exists, so the mkdir clause short-circuits on
        // the leading !is_dir() and the write proceeds directly.
        $logger = $this->recordingLogger();
        $path = $this->tmpRoot . '/access.php';
        $writer = new StaticsWriter($logger);

        self::assertTrue($writer->write($path, "<?php\n// body\n"));
        self::assertFileExists($path);
        self::assertSame("<?php\n// body\n", file_get_contents($path));
        self::assertSame([['info', 'WROTE: ' . $path]], $logger->records);
    }

    #[Test]
    public function testWriteCreatesMissingParentDirectories(): void
    {
        // $dir does not exist, so mkdir() runs and succeeds (its !mkdir() clause
        // is false), skipping the throw and reaching the write.
        $logger = $this->recordingLogger();
        $path = $this->tmpRoot . '/deeply/nested/db/caches.php';
        $writer = new StaticsWriter($logger);

        self::assertTrue($writer->write($path, '<?php content'));
        self::assertFileExists($path);
        self::assertDirectoryExists($this->tmpRoot . '/deeply/nested/db');
        self::assertSame([['info', 'WROTE: ' . $path]], $logger->records);
    }

    #[Test]
    public function testWriteIsIdempotentAndReturnsFalseWhenContentMatches(): void
    {
        $logger = $this->recordingLogger();
        $path = $this->tmpRoot . '/messages.php';
        file_put_contents($path, '<?php identical');
        $writer = new StaticsWriter($logger);

        self::assertFalse($writer->write($path, '<?php identical'));
        self::assertSame([['debug', 'UNCHANGED: ' . $path]], $logger->records);
    }

    #[Test]
    public function testDryRunReportsTheWriteButDoesNotTouchTheFilesystem(): void
    {
        $logger = $this->recordingLogger();
        $path = $this->tmpRoot . '/services.php';
        $writer = new StaticsWriter($logger, dryRun: true);

        self::assertTrue($writer->write($path, '<?php content'));
        self::assertFileDoesNotExist($path);
        self::assertSame([['info', 'WOULD WRITE: ' . $path]], $logger->records);
    }

    #[Test]
    public function testDryRunStillReturnsFalseForUnchangedExistingContent(): void
    {
        // The idempotency guard precedes the dry-run guard: an existing file with
        // identical content short-circuits to false even in dry-run mode.
        $logger = $this->recordingLogger();
        $path = $this->tmpRoot . '/existing.php';
        file_put_contents($path, '<?php same');
        $writer = new StaticsWriter($logger, dryRun: true);

        self::assertFalse($writer->write($path, '<?php same'));
        self::assertSame([['debug', 'UNCHANGED: ' . $path]], $logger->records);
    }

    #[Test]
    public function testWriteThrowsWhenTheTargetDirectoryCannotBeCreated(): void
    {
        // A regular file sits where a path component must be a directory, so
        // mkdir() cannot create $dir and is_dir() stays false afterwards —
        // driving the "Cannot create directory" throw. mkdir() emits an
        // E_WARNING on failure; a scoped handler swallows it so failOnWarning
        // is not tripped while the branch is still exercised.
        $blocker = $this->tmpRoot . '/blocker';
        file_put_contents($blocker, 'x');
        $path = $blocker . '/sub/file.php';
        $writer = new StaticsWriter();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot create directory: ' . $blocker . '/sub');

        set_error_handler(static fn (): bool => true);

        try {
            $writer->write($path, '<?php content');
        } finally {
            restore_error_handler();
        }
    }

    #[Test]
    public function testWriteThrowsWhenTheFileCannotBeWritten(): void
    {
        // $path is itself a directory: is_file() is false (so the idempotency
        // guard is skipped), its parent already exists (so mkdir is skipped),
        // and file_put_contents() then fails on the directory — driving the
        // "Cannot write file" throw. The E_WARNING is swallowed as above.
        $path = $this->tmpRoot . '/a-directory';
        mkdir($path, 0o777, true);
        $writer = new StaticsWriter();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot write file: ' . $path);

        set_error_handler(static fn (): bool => true);

        try {
            $writer->write($path, '<?php content');
        } finally {
            restore_error_handler();
        }
    }

    private function recordingLogger(): AbstractLogger
    {
        return new class extends AbstractLogger {
            /** @var list<array{string, string}> */
            public array $records = [];

            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->records[] = [(string) $level, (string) $message];
            }
        };
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
