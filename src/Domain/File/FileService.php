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

use Middag\Moodle\Domain\File\Contract\FileServiceInterface as file_service_interface;
use Middag\Moodle\Support\FileSupport as file_support;
use stored_file;

/**
 * File service — typed file operations composing file_support.
 *
 * Moodle-specific service: wraps file_support methods in a DI-injectable,
 * interface-driven service for extensions that need file operations.
 *
 * @internal
 *
 * @see file_service_interface
 */
class FileService implements file_service_interface
{
    public function getFile(int $contextid, string $component, string $filearea, int $itemid): ?stored_file
    {
        $file = file_support::getFile($contextid, $component, $filearea, $itemid);

        return ($file instanceof stored_file) ? $file : null;
    }

    public function getAreaFiles(int $contextid, string $component, string $filearea, int $itemid): array
    {
        return file_support::getAreaFiles($contextid, $component, $filearea, $itemid);
    }

    public function getFileById(int $fileid): ?stored_file
    {
        return file_support::getFileById($fileid);
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
        return file_support::createFileFromString($contextid, $component, $filearea, $itemid, $filepath, $filename, $content);
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
        return file_support::createFileFromPathname($contextid, $component, $filearea, $itemid, $filepath, $filename, $pathname);
    }

    public function delete(stored_file $file): bool
    {
        return file_support::deleteFile($file);
    }

    public function deleteArea(int $contextid, string $component, string $filearea, false|int $itemid = false): bool
    {
        return file_support::deleteAreaFiles($contextid, $component, $filearea, $itemid);
    }

    public function getUrl(stored_file $file, bool $forcedownload = false): string
    {
        return file_support::getFileUrl($file, $forcedownload);
    }

    public function hasFiles(int $contextid, string $component, string $filearea, int $itemid): bool
    {
        return file_support::hasFiles($contextid, $component, $filearea, $itemid);
    }

    public function getAreaSize(int $contextid, string $component, string $filearea, int $itemid): int
    {
        return file_support::getAreaSize($contextid, $component, $filearea, $itemid);
    }

    public function countFiles(int $contextid, string $component, string $filearea, int $itemid): int
    {
        return file_support::countAreaFiles($contextid, $component, $filearea, $itemid);
    }
}
