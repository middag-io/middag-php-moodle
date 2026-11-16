<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Form;

use Middag\Framework\Exception\MiddagValidationException;
use Middag\Moodle\Exception\MoodleAdapterException;
use Middag\Moodle\Form\DraftFileValueMapper;
use Middag\Ui\Form\FileValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stored_file;

/**
 * @internal
 */
#[CoversClass(DraftFileValueMapper::class)]
final class DraftFileValueMapperTest extends TestCase
{
    private const int USER_ID = 7;

    private const int DRAFT_ITEM_ID = 424242;

    private mixed $prevUser;

    private DraftFileValueMapper $mapper;

    protected function setUp(): void
    {
        $this->prevUser = $GLOBALS['USER'] ?? null;
        $GLOBALS['USER'] = (object) ['id' => self::USER_ID];

        foreach ([
            '__middag_test_area_files',
            '__middag_test_area_files_calls',
            '__middag_test_throw_area_files',
            '__middag_test_prepared_draft',
            '__middag_test_draft_prepare_calls',
            '__middag_test_draft_save_calls',
            '__middag_test_throw_draft_api',
            '__middag_test_deleted_files',
        ] as $key) {
            unset($GLOBALS[$key]);
        }

        $this->mapper = new DraftFileValueMapper();
    }

    protected function tearDown(): void
    {
        $GLOBALS['USER'] = $this->prevUser;
    }

    #[Test]
    public function fromDraftFileSetsTheDraftItemIdAsUploadToken(): void
    {
        $value = $this->mapper->fromDraftFile($this->draftFile());

        self::assertSame((string) self::DRAFT_ITEM_ID, $value->uploadToken);
        self::assertSame('https://moodle.test/draftfile.php/5/user/draft/424242/report.pdf', $value->url);
        self::assertSame('report.pdf', $value->name);
        self::assertSame(2048, $value->size);
        self::assertSame('application/pdf', $value->type);
        self::assertSame('91', $value->id);
    }

    #[Test]
    public function fromDraftFileRejectsAFileOutsideADraftArea(): void
    {
        $this->expectException(MoodleAdapterException::class);

        $this->mapper->fromDraftFile(new stored_file(['component' => 'local_example', 'filearea' => 'attachments', 'itemid' => 3]));
    }

    #[Test]
    public function fromDraftAreaListsTheCurrentUserDraftFiles(): void
    {
        $GLOBALS['__middag_test_area_files'] = ['a' => $this->draftFile(), 'b' => $this->draftFile(filename: 'b.png')];

        $values = $this->mapper->fromDraftArea(self::DRAFT_ITEM_ID);

        self::assertCount(2, $values);
        self::assertSame(['424242', '424242'], array_map(static fn (FileValue $v): ?string => $v->uploadToken, $values));
        self::assertSame([[self::USER_ID, 'user', 'draft', self::DRAFT_ITEM_ID, false]], $GLOBALS['__middag_test_area_files_calls']);
    }

    #[Test]
    public function fromFileAreaPreparesADraftAreaAndEmitsItsItemId(): void
    {
        $GLOBALS['__middag_test_prepared_draft'] = 555;
        $GLOBALS['__middag_test_area_files'] = [$this->draftFile(itemid: 555)];

        $values = $this->mapper->fromFileArea(30, 'local_example', 'logo', 12, ['maxfiles' => 1]);

        self::assertSame([[30, 'local_example', 'logo', 12, ['maxfiles' => 1]]], $GLOBALS['__middag_test_draft_prepare_calls']);
        self::assertSame([[self::USER_ID, 'user', 'draft', 555, false]], $GLOBALS['__middag_test_area_files_calls']);
        self::assertCount(1, $values);
        self::assertSame('555', $values[0]->uploadToken);
    }

    #[Test]
    public function toDraftItemIdReturnsTheItemIdOfAnOwnedDraftArea(): void
    {
        $GLOBALS['__middag_test_area_files'] = [$this->draftFile()];

        $itemId = $this->mapper->toDraftItemId(new FileValue(url: '/f', uploadToken: '424242'));

        self::assertSame(self::DRAFT_ITEM_ID, $itemId);
        self::assertSame([[self::USER_ID, 'user', 'draft', self::DRAFT_ITEM_ID, false]], $GLOBALS['__middag_test_area_files_calls']);
    }

    #[Test]
    public function toDraftItemIdReturnsNullWhenNoValueCarriesAToken(): void
    {
        self::assertNull($this->mapper->toDraftItemId(new FileValue(url: '/stored.pdf', id: '3')));
        self::assertNull($this->mapper->toDraftItemId([]));
        self::assertArrayNotHasKey('__middag_test_area_files_calls', $GLOBALS);
    }

    #[Test]
    public function toDraftItemIdAcceptsAListSharingOneToken(): void
    {
        $GLOBALS['__middag_test_area_files'] = [$this->draftFile()];

        $itemId = $this->mapper->toDraftItemId([
            new FileValue(url: '/a', uploadToken: '424242'),
            new FileValue(url: '/b', uploadToken: '424242'),
        ]);

        self::assertSame(self::DRAFT_ITEM_ID, $itemId);
    }

    #[Test]
    public function toDraftItemIdRejectsAListMixingStoredAndDraftFiles(): void
    {
        // Saving the draft area would drop the stored entry the client kept.
        $GLOBALS['__middag_test_area_files'] = [$this->draftFile()];

        $exception = $this->captureRejection([
            new FileValue(url: '/stored', id: '3'),
            new FileValue(url: '/new', id: '91', uploadToken: '424242'),
        ], 'attachments');

        self::assertSame(['attachments'], array_keys($exception->errors()));
        self::assertArrayNotHasKey('__middag_test_area_files_calls', $GLOBALS);
    }

    #[Test]
    public function toDraftItemIdRejectsAListWithDifferentTokens(): void
    {
        $GLOBALS['__middag_test_area_files'] = [$this->draftFile()];

        $exception = $this->captureRejection([
            new FileValue(url: '/a', uploadToken: '424242'),
            new FileValue(url: '/b', uploadToken: '424243'),
        ], 'attachments');

        self::assertArrayHasKey('attachments', $exception->errors());
    }

    #[Test]
    public function toDraftItemIdRejectsAnEntryThatIsNotAFileValue(): void
    {
        $this->captureRejection([['url' => '/a', 'uploadToken' => '424242']]);
    }

    #[Test]
    #[DataProvider('malformedTokens')]
    public function toDraftItemIdRejectsAMalformedToken(string $token): void
    {
        // An owned area must not rescue a malformed token.
        $GLOBALS['__middag_test_area_files'] = [$this->draftFile()];

        $exception = $this->captureRejection(new FileValue(url: '/f', uploadToken: $token), 'logo');

        self::assertSame(['logo'], array_keys($exception->errors()));
        self::assertArrayNotHasKey('__middag_test_area_files_calls', $GLOBALS);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedTokens(): iterable
    {
        yield 'empty' => [''];

        yield 'zero' => ['0'];

        yield 'leading zero' => ['012'];

        yield 'plus sign' => ['+5'];

        yield 'minus sign' => ['-5'];

        yield 'leading space' => [' 12'];

        yield 'trailing newline' => ["12\n"];

        yield 'decimal' => ['1.0'];

        yield 'exponent' => ['1e3'];

        yield 'hex' => ['0x1A'];

        yield 'opaque string' => ['upload-99'];

        yield 'above PHP_INT_MAX' => ['9223372036854775808'];

        yield 'twenty digits' => ['12345678901234567890'];
    }

    #[Test]
    public function toDraftItemIdAcceptsPhpIntMax(): void
    {
        $GLOBALS['__middag_test_area_files'] = [$this->draftFile()];

        self::assertSame(PHP_INT_MAX, $this->mapper->toDraftItemId(new FileValue(url: '/f', uploadToken: (string) PHP_INT_MAX)));
    }

    #[Test]
    public function toDraftItemIdRejectsADraftAreaWithoutFilesOfTheCurrentUser(): void
    {
        // Another user's draft itemid: the lookup in the current user's context finds nothing.
        $GLOBALS['__middag_test_area_files'] = [];

        $exception = $this->captureRejection(new FileValue(url: '/f', uploadToken: '424242'));

        self::assertSame(['uploadToken'], array_keys($exception->errors()));
        self::assertSame([[self::USER_ID, 'user', 'draft', self::DRAFT_ITEM_ID, false]], $GLOBALS['__middag_test_area_files_calls']);
    }

    #[Test]
    public function toDraftItemIdRejectsAnyTokenWithoutALoggedInUser(): void
    {
        $GLOBALS['USER'] = (object) ['id' => 0];
        $GLOBALS['__middag_test_area_files'] = [$this->draftFile()];

        $this->captureRejection(new FileValue(url: '/f', uploadToken: '424242'));

        self::assertArrayNotHasKey('__middag_test_area_files_calls', $GLOBALS);
    }

    #[Test]
    public function toDraftItemIdRejectsWhenTheFileApiFails(): void
    {
        $GLOBALS['__middag_test_throw_area_files'] = true;

        $this->captureRejection(new FileValue(url: '/f', uploadToken: '424242'));
    }

    #[Test]
    public function saveToFileAreaStoresTheResolvedDraftArea(): void
    {
        $GLOBALS['__middag_test_area_files'] = [$this->draftFile()];

        $saved = $this->mapper->saveToFileArea(new FileValue(url: '/f', id: '91', uploadToken: '424242'), 30, 'local_example', 'logo', 12, ['maxfiles' => 1]);

        self::assertTrue($saved);
        self::assertSame([[self::DRAFT_ITEM_ID, 30, 'local_example', 'logo', 12, ['maxfiles' => 1]]], $GLOBALS['__middag_test_draft_save_calls']);
        self::assertArrayNotHasKey('__middag_test_deleted_files', $GLOBALS);
    }

    #[Test]
    public function saveToFileAreaDeletesTheDraftFilesTheClientRemoved(): void
    {
        $GLOBALS['__middag_test_area_files'] = [
            $this->draftFile(id: 91),
            $this->draftFile(filename: 'removed.pdf', id: 92),
            $this->draftFile(filename: 'kept.pdf', id: 93),
        ];

        $saved = $this->mapper->saveToFileArea([
            new FileValue(url: '/a', id: '91', uploadToken: '424242'),
            new FileValue(url: '/c', id: '93', uploadToken: '424242'),
        ], 30, 'local_example', 'attachments', 12);

        self::assertTrue($saved);
        self::assertSame([92], $GLOBALS['__middag_test_deleted_files']);
        self::assertCount(1, $GLOBALS['__middag_test_draft_save_calls']);
    }

    #[Test]
    #[DataProvider('entriesNamingNoDraftFile')]
    public function saveToFileAreaRejectsAnEntryThatNamesNoDraftFile(?string $id): void
    {
        $GLOBALS['__middag_test_area_files'] = [$this->draftFile(id: 91), $this->draftFile(filename: 'b.pdf', id: 92)];

        try {
            $this->mapper->saveToFileArea([
                new FileValue(url: '/a', id: '91', uploadToken: '424242'),
                new FileValue(url: '/x', id: $id, uploadToken: '424242'),
            ], 30, 'local_example', 'attachments', 12, [], 'attachments');
            self::fail('Expected the entry to be rejected.');
        } catch (MiddagValidationException $middagValidationException) {
            self::assertSame(['attachments'], array_keys($middagValidationException->errors()));
        }

        self::assertArrayNotHasKey('__middag_test_deleted_files', $GLOBALS);
        self::assertArrayNotHasKey('__middag_test_draft_save_calls', $GLOBALS);
    }

    /**
     * @return iterable<string, array{null|string}>
     */
    public static function entriesNamingNoDraftFile(): iterable
    {
        yield 'no id' => [null];

        yield 'id outside the draft area' => ['3'];
    }

    #[Test]
    public function saveToFileAreaFailsWhenARemovedDraftFileCannotBeDeleted(): void
    {
        $removed = $this->draftFile(filename: 'removed.pdf', id: 92);
        $removed->deleteResult = false;
        $GLOBALS['__middag_test_area_files'] = [$this->draftFile(id: 91), $removed];

        try {
            $this->mapper->saveToFileArea([new FileValue(url: '/a', id: '91', uploadToken: '424242')], 30, 'local_example', 'attachments', 12);
            self::fail('Expected the failed delete to stop the save.');
        } catch (MoodleAdapterException) {
            // Expected.
        }

        self::assertArrayNotHasKey('__middag_test_draft_save_calls', $GLOBALS);
    }

    #[Test]
    public function saveToFileAreaLeavesTheTargetUnchangedWhenTheFieldIsCleared(): void
    {
        $saved = $this->mapper->saveToFileArea([], 30, 'local_example', 'attachments', 12);

        self::assertFalse($saved);
        self::assertArrayNotHasKey('__middag_test_draft_save_calls', $GLOBALS);
    }

    #[Test]
    public function saveToFileAreaDoesNothingWithoutAToken(): void
    {
        $saved = $this->mapper->saveToFileArea(new FileValue(url: '/stored.pdf'), 30, 'local_example', 'logo', 12);

        self::assertFalse($saved);
        self::assertArrayNotHasKey('__middag_test_draft_save_calls', $GLOBALS);
    }

    #[Test]
    public function saveToFileAreaNeverSavesARejectedToken(): void
    {
        $GLOBALS['__middag_test_area_files'] = [];

        try {
            $this->mapper->saveToFileArea(new FileValue(url: '/f', uploadToken: '424242'), 30, 'local_example', 'logo', 12);
            self::fail('Expected the token to be rejected.');
        } catch (MiddagValidationException $middagValidationException) {
            self::assertSame(422, $middagValidationException->getStatusCode());
        }

        self::assertArrayNotHasKey('__middag_test_draft_save_calls', $GLOBALS);
    }

    #[Test]
    public function emittedValueRoundTripsToTheSameDraftItemId(): void
    {
        $GLOBALS['__middag_test_area_files'] = [$this->draftFile()];

        $emitted = $this->mapper->fromDraftArea(self::DRAFT_ITEM_ID);

        self::assertSame(self::DRAFT_ITEM_ID, $this->mapper->toDraftItemId($emitted));
    }

    /**
     * @param array<mixed>|FileValue $value
     */
    private function captureRejection(array|FileValue $value, ?string $field = null): MiddagValidationException
    {
        try {
            $this->mapper->toDraftItemId($value, $field);
        } catch (MiddagValidationException $middagValidationException) {
            self::assertSame(422, $middagValidationException->getStatusCode());

            return $middagValidationException;
        }

        self::fail('Expected the upload token to be rejected.');
    }

    private function draftFile(int $itemid = self::DRAFT_ITEM_ID, string $filename = 'report.pdf', int $id = 91): stored_file
    {
        return new stored_file([
            'id' => $id,
            'contextid' => self::USER_ID,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => $itemid,
            'filepath' => '/',
            'filename' => $filename,
            'filesize' => 2048,
            'mimetype' => 'application/pdf',
        ]);
    }
}
