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

use Middag\Moodle\Exception\MoodleAdapterException;
use Middag\Moodle\Support\FileSupport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stored_file;

/**
 * @internal
 */
#[CoversClass(FileSupport::class)]
final class FileSupportDraftAreaTest extends TestCase
{
    protected function setUp(): void
    {
        foreach ([
            '__middag_test_area_files',
            '__middag_test_area_files_calls',
            '__middag_test_throw_area_files',
            '__middag_test_prepared_draft',
            '__middag_test_draft_prepare_calls',
            '__middag_test_draft_save_calls',
            '__middag_test_throw_draft_api',
        ] as $key) {
            unset($GLOBALS[$key]);
        }
    }

    #[Test]
    public function getDraftAreaFilesQueriesTheUserDraftAreaWithoutDirectories(): void
    {
        $file = new stored_file(['filename' => 'a.txt']);
        $GLOBALS['__middag_test_area_files'] = ['hash' => $file];

        self::assertSame([$file], FileSupport::getDraftAreaFiles(7, 99));
        self::assertSame([[7, 'user', 'draft', 99, false]], $GLOBALS['__middag_test_area_files_calls']);
    }

    #[Test]
    public function getDraftAreaFilesSkipsTheLookupForANonPositiveUserOrItem(): void
    {
        $GLOBALS['__middag_test_area_files'] = [new stored_file()];

        self::assertSame([], FileSupport::getDraftAreaFiles(0, 99));
        self::assertSame([], FileSupport::getDraftAreaFiles(7, 0));
        self::assertArrayNotHasKey('__middag_test_area_files_calls', $GLOBALS);
    }

    #[Test]
    public function getDraftAreaFilesReturnsEmptyWhenTheFileApiFails(): void
    {
        $GLOBALS['__middag_test_throw_area_files'] = true;

        self::assertSame([], FileSupport::getDraftAreaFiles(7, 99));
    }

    #[Test]
    public function prepareDraftAreaReturnsTheNewDraftItemId(): void
    {
        $GLOBALS['__middag_test_prepared_draft'] = 321;

        self::assertSame(321, FileSupport::prepareDraftArea(30, 'local_example', 'logo', null, ['subdirs' => 0]));
        self::assertSame([[30, 'local_example', 'logo', null, ['subdirs' => 0]]], $GLOBALS['__middag_test_draft_prepare_calls']);
    }

    #[Test]
    public function prepareDraftAreaWrapsAFileApiFailure(): void
    {
        $GLOBALS['__middag_test_throw_draft_api'] = true;

        $this->expectException(MoodleAdapterException::class);

        FileSupport::prepareDraftArea(30, 'local_example', 'logo', 12);
    }

    #[Test]
    public function saveDraftAreaForwardsToTheFileApi(): void
    {
        FileSupport::saveDraftArea(321, 30, 'local_example', 'logo', 12, ['maxfiles' => 1]);

        self::assertSame([[321, 30, 'local_example', 'logo', 12, ['maxfiles' => 1]]], $GLOBALS['__middag_test_draft_save_calls']);
    }

    #[Test]
    public function saveDraftAreaWrapsAFileApiFailure(): void
    {
        $GLOBALS['__middag_test_throw_draft_api'] = true;

        $this->expectException(MoodleAdapterException::class);

        FileSupport::saveDraftArea(321, 30, 'local_example', 'logo', 12);
    }

    #[Test]
    public function getDraftFileUrlPointsAtDraftfile(): void
    {
        $file = new stored_file(['itemid' => 321, 'filepath' => '/sub/', 'filename' => 'a.txt']);

        self::assertSame('https://moodle.test/draftfile.php/5/user/draft/321/sub/a.txt', FileSupport::getDraftFileUrl($file));
    }
}
