<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Filesystem;

use Middag\Framework\Exception\MiddagInfrastructureException;
use Middag\Moodle\Filesystem\MoodledataFilesystem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * @internal
 */
#[CoversClass(MoodledataFilesystem::class)]
final class MoodledataFilesystemTest extends TestCase
{
    private string $dataroot;

    protected function setUp(): void
    {
        $this->dataroot = sys_get_temp_dir() . '/middag-dataroot-' . uniqid();
        $cfg = new stdClass();
        $cfg->dataroot = $this->dataroot;
        $GLOBALS['CFG'] = $cfg;
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['CFG']);
        if (is_dir($this->dataroot)) {
            exec('rm -rf ' . escapeshellarg($this->dataroot));
        }
    }

    #[Test]
    public function writeReadExistsDeleteRoundTripInsideTheDatarootJail(): void
    {
        $filesystem = new MoodledataFilesystem('middag');

        $filesystem->write('exports/summary.txt', 'hello');

        self::assertTrue($filesystem->exists('exports/summary.txt'));
        self::assertSame('hello', $filesystem->read('exports/summary.txt'));
        self::assertFileExists($this->dataroot . '/middag/exports/summary.txt', 'rooted under dataroot/{subdirectory}');

        $filesystem->delete('exports/summary.txt');
        self::assertFalse($filesystem->exists('exports/summary.txt'));
    }

    #[Test]
    public function acceptsExplicitBaseDirOverride(): void
    {
        $base = $this->dataroot . '/custom';
        $filesystem = new MoodledataFilesystem(baseDir: $base);

        $filesystem->write('a.txt', 'x');

        self::assertFileExists($base . '/a.txt');
    }

    #[Test]
    public function throwsOutsideAMoodleRuntime(): void
    {
        unset($GLOBALS['CFG']);

        $this->expectException(MiddagInfrastructureException::class);
        $this->expectExceptionMessage('dataroot');

        new MoodledataFilesystem();
    }

    #[Test]
    public function escapingTheJailIsRejected(): void
    {
        $filesystem = new MoodledataFilesystem('middag');

        $this->expectException(MiddagInfrastructureException::class);

        $filesystem->read('../outside.txt');
    }

    #[Test]
    public function rejectsParentDirectoryTraversalInTheSubdirectory(): void
    {
        $escapeTarget = \dirname($this->dataroot) . '/middag-escape-' . uniqid();
        $relativeEscape = '../' . basename($escapeTarget);

        try {
            new MoodledataFilesystem($relativeEscape);
            self::fail('A ".." subdirectory segment must be rejected.');
        } catch (MiddagInfrastructureException $middagInfrastructureException) {
            self::assertStringContainsString('..', $middagInfrastructureException->getMessage());
        }

        self::assertDirectoryDoesNotExist(
            $escapeTarget,
            'a traversing subdirectory must never create a directory outside dataroot'
        );
    }

    #[Test]
    public function rejectsANullByteInTheSubdirectory(): void
    {
        $this->expectException(MiddagInfrastructureException::class);
        $this->expectExceptionMessage('null byte');

        new MoodledataFilesystem("evil\0dir");
    }
}
