<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Domain\File;

use Middag\Moodle\Domain\File\Contract\FileServiceInterface;
use Middag\Moodle\Support\FileSupport;
use stored_file;

/**
 * File service — typed file operations composing FileSupport.
 *
 * Moodle-specific service: wraps FileSupport methods in a DI-injectable,
 * interface-driven service for extensions that need file operations.
 *
 * @internal
 *
 * @see FileServiceInterface
 */
class FileService implements FileServiceInterface
{
    public function getFile(int $contextid, string $component, string $filearea, int $itemid): ?stored_file
    {
        $file = FileSupport::getFile($contextid, $component, $filearea, $itemid);

        return ($file instanceof stored_file) ? $file : null;
    }

    public function getAreaFiles(int $contextid, string $component, string $filearea, int $itemid): array
    {
        return FileSupport::getAreaFiles($contextid, $component, $filearea, $itemid);
    }

    public function getFileById(int $fileid): ?stored_file
    {
        return FileSupport::getFileById($fileid);
    }

    public function storeFromString(
        int $contextid,
        string $component,
        string $filearea,
        int $itemid,
        string $filepath,
        string $filename,
        string $content,
    ): ?stored_file {
        return FileSupport::createFileFromString($contextid, $component, $filearea, $itemid, $filepath, $filename, $content);
    }

    public function storeFromPath(
        int $contextid,
        string $component,
        string $filearea,
        int $itemid,
        string $filepath,
        string $filename,
        string $pathname,
    ): ?stored_file {
        return FileSupport::createFileFromPathname($contextid, $component, $filearea, $itemid, $filepath, $filename, $pathname);
    }

    public function delete(stored_file $file): bool
    {
        return FileSupport::deleteFile($file);
    }

    public function deleteArea(int $contextid, string $component, string $filearea, false|int $itemid = false): bool
    {
        return FileSupport::deleteAreaFiles($contextid, $component, $filearea, $itemid);
    }

    public function getUrl(stored_file $file, bool $forcedownload = false): string
    {
        return FileSupport::getFileUrl($file, $forcedownload);
    }

    public function hasFiles(int $contextid, string $component, string $filearea, int $itemid): bool
    {
        return FileSupport::hasFiles($contextid, $component, $filearea, $itemid);
    }

    public function getAreaSize(int $contextid, string $component, string $filearea, int $itemid): int
    {
        return FileSupport::getAreaSize($contextid, $component, $filearea, $itemid);
    }

    public function countFiles(int $contextid, string $component, string $filearea, int $itemid): int
    {
        return FileSupport::countAreaFiles($contextid, $component, $filearea, $itemid);
    }
}
