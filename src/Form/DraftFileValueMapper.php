<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Form;

use Middag\Framework\Exception\MiddagValidationException;
use Middag\Moodle\Exception\MoodleAdapterException;
use Middag\Moodle\Support\FileSupport;
use Middag\Moodle\Support\UserSupport;
use Middag\Ui\Form\FileValue;
use stored_file;

/**
 * Maps the opaque `FileValue::$uploadToken` to a Moodle draft area and back.
 *
 * On Moodle a pending upload is a file in the current user's draft area
 * (user context, component `user`, file area `draft`), keyed by a draft
 * itemid. This mapper owns both directions of that mapping:
 *
 * - emit: every `FileValue` it builds carries the draft itemid, as a decimal
 *   string, in `uploadToken`;
 * - submit: it reads `uploadToken` back, accepts only a canonical positive
 *   decimal string, checks that the draft area holds at least one file of the
 *   current user, and returns the draft itemid.
 *
 * A token that fails either check is rejected with a
 * `MiddagValidationException` (HTTP 422): it is client input, never trusted.
 *
 * @api
 */
final class DraftFileValueMapper
{
    /** Longest decimal string that can still fit a 64-bit PHP int. */
    private const int MAX_TOKEN_LENGTH = 19;

    /** Error key used when the caller names no field. */
    private const string DEFAULT_FIELD = 'uploadToken';

    /**
     * Builds the wire value of one file in a draft area.
     *
     * @throws MoodleAdapterException when the file is not in a draft area
     */
    public function fromDraftFile(stored_file $file): FileValue
    {
        if ($file->get_component() !== 'user' || $file->get_filearea() !== 'draft') {
            throw new MoodleAdapterException('A FileValue upload token can only point to a draft area file.');
        }

        return new FileValue(
            url: FileSupport::getDraftFileUrl($file),
            name: $file->get_filename(),
            size: (int) $file->get_filesize(),
            type: $file->get_mimetype(),
            id: (string) (int) $file->get_id(),
            uploadToken: (string) (int) $file->get_itemid(),
        );
    }

    /**
     * Builds the wire values of the files in a draft area of the current user.
     *
     * @return list<FileValue> empty when the area holds no file of the current user
     */
    public function fromDraftArea(int $draftItemId): array
    {
        return array_map(
            $this->fromDraftFile(...),
            FileSupport::getDraftAreaFiles(UserSupport::getCurrentUserId(), $draftItemId),
        );
    }

    /**
     * Copies a stored file area into a new draft area and builds its wire values.
     *
     * Use it to fill an edit form: the values carry the new draft itemid, so the
     * submitted form can be stored with {@see saveToFileArea()}.
     *
     * @param array<string, mixed> $options file manager options (`subdirs`, `maxfiles`, `maxbytes`, ...)
     *
     * @return list<FileValue>
     *
     * @throws MoodleAdapterException when the file API fails
     */
    public function fromFileArea(int $contextId, string $component, string $fileArea, ?int $itemId, array $options = []): array
    {
        return $this->fromDraftArea(FileSupport::prepareDraftArea($contextId, $component, $fileArea, $itemId, $options));
    }

    /**
     * Resolves the draft itemid a submitted file value points to.
     *
     * A multiple-file field submits a list: every entry that carries a token
     * must carry the same one. Entries without a token are files already stored
     * and need no draft area. A list entry that is not a `FileValue` is rejected.
     *
     * @param array<mixed>|FileValue $value the submitted file value(s)
     * @param null|string            $field form field name used as the error key
     *
     * @return null|int the draft itemid, or null when no entry carries a token
     *
     * @throws MiddagValidationException when a token is malformed, when the entries
     *                                   disagree, or when the draft area holds no file
     *                                   of the current user
     */
    public function toDraftItemId(array|FileValue $value, ?string $field = null): ?int
    {
        $tokens = [];

        foreach ($value instanceof FileValue ? [$value] : $value as $entry) {
            if (!$entry instanceof FileValue) {
                throw $this->invalid($field, 'The submitted value is not a file value.');
            }

            if ($entry->uploadToken !== null && !in_array($entry->uploadToken, $tokens, true)) {
                $tokens[] = $entry->uploadToken;
            }
        }

        if ($tokens === []) {
            return null;
        }

        if (count($tokens) > 1) {
            throw $this->invalid($field, 'The file values carry different upload tokens.');
        }

        $draftItemId = $this->parseToken($tokens[0]);

        if ($draftItemId === null) {
            throw $this->invalid($field, 'The upload token is not a draft area reference.');
        }

        if (FileSupport::getDraftAreaFiles(UserSupport::getCurrentUserId(), $draftItemId) === []) {
            throw $this->invalid($field, 'The upload token does not match a draft area of the current user.');
        }

        return $draftItemId;
    }

    /**
     * Stores the draft area a submitted file value points to into a file area.
     *
     * @param array<mixed>|FileValue $value   the submitted file value(s), as for {@see toDraftItemId()}
     * @param array<string, mixed>   $options file manager options (`subdirs`, `maxfiles`, `maxbytes`, ...)
     * @param null|string            $field   form field name used as the error key
     *
     * @return bool true when a draft area was stored, false when no entry carries a token
     *
     * @throws MiddagValidationException when the token is rejected (see {@see toDraftItemId()})
     * @throws MoodleAdapterException    when the file API fails
     */
    public function saveToFileArea(
        array|FileValue $value,
        int $contextId,
        string $component,
        string $fileArea,
        int $itemId,
        array $options = [],
        ?string $field = null,
    ): bool {
        $draftItemId = $this->toDraftItemId($value, $field);

        if ($draftItemId === null) {
            return false;
        }

        FileSupport::saveDraftArea($draftItemId, $contextId, $component, $fileArea, $itemId, $options);

        return true;
    }

    /**
     * Parses a canonical positive decimal string that fits a PHP int.
     *
     * Rejects the empty string, signs, spaces, leading zeros, decimals,
     * exponents and values above PHP_INT_MAX.
     */
    private function parseToken(string $token): ?int
    {
        if ($token === '' || strlen($token) > self::MAX_TOKEN_LENGTH || !ctype_digit($token) || $token[0] === '0') {
            return null;
        }

        $itemId = (int) $token;

        return (string) $itemId === $token ? $itemId : null;
    }

    private function invalid(?string $field, string $message): MiddagValidationException
    {
        return new MiddagValidationException($message, [$field ?? self::DEFAULT_FIELD => $message]);
    }
}
