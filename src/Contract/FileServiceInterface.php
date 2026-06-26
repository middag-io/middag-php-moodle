<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Contract;

use stored_file;

/**
 * File service contract — typed file operations with access validation.
 *
 * Composes file_support + context_support + capability_support into a single API
 * for file operations that respect Moodle's context and capability model.
 *
 * @api
 */
interface FileServiceInterface
{
    /**
     * Get a file by its file area coordinates.
     *
     * @param int    $contextid Moodle context ID
     * @param string $component Plugin component (e.g. 'local_example')
     * @param string $filearea  File area name
     * @param int    $itemid    Item ID within the area
     */
    public function getFile(int $contextid, string $component, string $filearea, int $itemid): ?stored_file;

    /**
     * Get all files in a file area.
     *
     * @return array<stored_file>
     */
    public function getAreaFiles(int $contextid, string $component, string $filearea, int $itemid): array;

    /**
     * Get a file by its database ID.
     */
    public function getFileById(int $fileid): ?stored_file;

    /**
     * Store a file from a string content.
     */
    public function storeFromString(
        int $contextid,
        string $component,
        string $filearea,
        int $itemid,
        string $filepath,
        string $filename,
        string $content,
    ): ?stored_file;

    /**
     * Store a file from a local filesystem path.
     */
    public function storeFromPath(
        int $contextid,
        string $component,
        string $filearea,
        int $itemid,
        string $filepath,
        string $filename,
        string $pathname,
    ): ?stored_file;

    /**
     * Delete a specific file.
     */
    public function delete(stored_file $file): bool;

    /**
     * Delete all files in a file area.
     */
    public function deleteArea(int $contextid, string $component, string $filearea, false|int $itemid = false): bool;

    /**
     * Get the URL for serving a file.
     */
    public function getUrl(stored_file $file, bool $forcedownload = false): string;

    /**
     * Whether files exist in a given area.
     */
    public function hasFiles(int $contextid, string $component, string $filearea, int $itemid): bool;

    /**
     * Get the total size of files in an area (bytes).
     */
    public function getAreaSize(int $contextid, string $component, string $filearea, int $itemid): int;

    /**
     * Get file count in an area.
     */
    public function countFiles(int $contextid, string $component, string $filearea, int $itemid): int;
}
