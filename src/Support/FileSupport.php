<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Support;

use Exception;
use file_storage;
use Middag\Moodle\Config\ComponentContext;
use Middag\Moodle\Domain\File\StoredFile;
use Middag\Moodle\Shared\Util\Debug;
use stdClass;
use stored_file;
use Throwable;

/**
 * File storage utility wrapper for Moodle's File Storage API.
 *
 * This class centralizes file operations using Moodle's file_storage system,
 * providing a consistent interface for file management across the plugin.
 *
 * All file operations should go through this class to ensure proper handling
 * of Moodle's file storage architecture and to maintain consistency.
 *
 * @api
 */
class FileSupport
{
    /**
     * Component name used for file storage.
     *
     * Resolved from the composition-root {@see ComponentContext} seam instead of
     * a hard-coded plugin constant, keeping the adapter product-agnostic.
     */
    public static function component(): string
    {
        return ComponentContext::name();
    }

    /**
     * Retrieves the Moodle file storage instance.
     *
     * @return file_storage File storage instance
     */
    public static function getStorage(): file_storage
    {
        return get_file_storage();
    }

    /**
     * Retrieves a single file from a file area.
     *
     * Useful for areas that should contain only one file (e.g., profile pictures, logos).
     *
     * @param int    $contextid  Context ID
     * @param string $component  Component name (e.g., 'local_example')
     * @param string $filearea   File area name
     * @param int    $itemid     Item ID
     * @param bool   $validimage If true, only return valid image files
     *
     * @return null|false|stored_file File object, false if validation fails, null if not found
     */
    public static function getFile(
        int $contextid,
        string $component,
        string $filearea,
        int $itemid,
        bool $validimage = false
    ): false|stored_file|null {
        try {
            $fs = self::getStorage();
            $files = $fs->get_area_files($contextid, $component, $filearea, $itemid, 'filename');

            foreach ($files as $file) {
                if ($file->get_filename() !== '.') {
                    if ($validimage) {
                        return $file->is_valid_image() ? $file : false;
                    }

                    return $file;
                }
            }

            return null;
        } catch (Exception $exception) {
            Debug::traceException($exception);

            return null;
        }
    }

    /**
     * Retrieves all files from a file area (excluding directories).
     *
     * @param int    $contextid Context ID
     * @param string $component Component name
     * @param string $filearea  File area name
     * @param int    $itemid    Item ID
     *
     * @return stored_file[] Array of stored file objects
     */
    public static function getAreaFiles(
        int $contextid,
        string $component,
        string $filearea,
        int $itemid
    ): array {
        try {
            $files = [];
            $fs = self::getStorage();
            $storedfiles = $fs->get_area_files($contextid, $component, $filearea, $itemid, 'filename', false);

            foreach ($storedfiles as $file) {
                if (!$file->is_directory()) {
                    $files[] = $file;
                }
            }

            return $files;
        } catch (Exception $exception) {
            Debug::traceException($exception);

            return [];
        }
    }

    /**
     * Retrieves a specific file by its path components.
     *
     * @param int    $contextid Context ID
     * @param string $component Component name
     * @param string $filearea  File area name
     * @param int    $itemid    Item ID
     * @param string $filepath  File path (e.g., '/')
     * @param string $filename  File name
     *
     * @return null|stored_file File object or null if not found
     */
    public static function getFileByPath(
        int $contextid,
        string $component,
        string $filearea,
        int $itemid,
        string $filepath,
        string $filename
    ): ?stored_file {
        try {
            $fs = self::getStorage();
            $file = $fs->get_file($contextid, $component, $filearea, $itemid, $filepath, $filename);

            return $file ?: null;
        } catch (Exception $exception) {
            Debug::traceException($exception);

            return null;
        }
    }

    /**
     * Retrieves a file by its database ID.
     *
     * @param int $fileid File ID
     *
     * @return null|stored_file File object or null if not found
     */
    public static function getFileById(int $fileid): ?stored_file
    {
        try {
            $fs = self::getStorage();
            $file = $fs->get_file_by_id($fileid);

            return $file ?: null;
        } catch (Exception $exception) {
            Debug::traceException($exception);

            return null;
        }
    }

    /**
     * Creates a file record from an existing stored file (copy).
     *
     * Useful for duplicating files or moving them between areas.
     *
     * @param stored_file $sourcefile Source file to copy
     * @param int         $contextid  Target context ID
     * @param string      $component  Target component
     * @param string      $filearea   Target file area
     * @param int         $itemid     Target item ID
     * @param string      $filepath   Target file path (default: '/')
     * @param null|string $filename   Target filename (null to keep original)
     *
     * @return null|stored_file Created file or null on failure
     */
    public static function createFileFromStored(
        stored_file $sourcefile,
        int $contextid,
        string $component,
        string $filearea,
        int $itemid,
        string $filepath = '/',
        ?string $filename = null
    ): ?stored_file {
        try {
            $fs = self::getStorage();

            $filerecord = new stdClass();
            $filerecord->contextid = $contextid;
            $filerecord->component = $component;
            $filerecord->filearea = $filearea;
            $filerecord->itemid = $itemid;
            $filerecord->filepath = $filepath;

            if ($filename !== null) {
                $filerecord->filename = $filename;
            }

            return $fs->create_file_from_storedfile($filerecord, $sourcefile);
        } catch (Exception $exception) {
            Debug::traceException($exception);

            return null;
        }
    }

    /**
     * Creates a file from raw string content.
     *
     * @param int    $contextid Context ID
     * @param string $component Component name
     * @param string $filearea  File area name
     * @param int    $itemid    Item ID
     * @param string $filepath  File path (default: '/')
     * @param string $filename  File name
     * @param string $content   File content
     *
     * @return null|stored_file Created file or null on failure
     */
    public static function createFileFromString(
        int $contextid,
        string $component,
        string $filearea,
        int $itemid,
        string $filepath,
        string $filename,
        string $content
    ): ?stored_file {
        try {
            $fs = self::getStorage();

            $filerecord = new stdClass();
            $filerecord->contextid = $contextid;
            $filerecord->component = $component;
            $filerecord->filearea = $filearea;
            $filerecord->itemid = $itemid;
            $filerecord->filepath = $filepath;
            $filerecord->filename = $filename;

            return $fs->create_file_from_string($filerecord, $content);
        } catch (Exception $exception) {
            Debug::traceException($exception);

            return null;
        }
    }

    /**
     * Creates a file from a local filesystem path.
     *
     * @param int    $contextid Context ID
     * @param string $component Component name
     * @param string $filearea  File area name
     * @param int    $itemid    Item ID
     * @param string $filepath  File path in storage (default: '/')
     * @param string $filename  File name in storage
     * @param string $pathname  Local file system path
     *
     * @return null|stored_file Created file or null on failure
     */
    public static function createFileFromPathname(
        int $contextid,
        string $component,
        string $filearea,
        int $itemid,
        string $filepath,
        string $filename,
        string $pathname
    ): ?stored_file {
        try {
            $fs = self::getStorage();

            $filerecord = new stdClass();
            $filerecord->contextid = $contextid;
            $filerecord->component = $component;
            $filerecord->filearea = $filearea;
            $filerecord->itemid = $itemid;
            $filerecord->filepath = $filepath;
            $filerecord->filename = $filename;

            return $fs->create_file_from_pathname($filerecord, $pathname);
        } catch (Exception $exception) {
            Debug::traceException($exception);

            return null;
        }
    }

    /**
     * Deletes a specific file.
     *
     * @param stored_file $file File to delete
     *
     * @return bool True on success, false otherwise
     */
    public static function deleteFile(stored_file $file): bool
    {
        try {
            return $file->delete();
        } catch (Exception $exception) {
            Debug::traceException($exception);

            return false;
        }
    }

    /**
     * Deletes a file by its ID.
     *
     * @param int $fileid File ID
     *
     * @return bool True on success, false otherwise
     */
    public static function deleteFileById(int $fileid): bool
    {
        try {
            $file = self::getFileById($fileid);

            if (!$file instanceof stored_file) {
                return false;
            }

            return $file->delete();
        } catch (Exception $exception) {
            Debug::traceException($exception);

            return false;
        }
    }

    /**
     * Deletes all files in a specific file area (optionally limited by itemid).
     *
     * @param int       $contextid Context ID
     * @param string    $component Component name
     * @param string    $filearea  File area name
     * @param false|int $itemid    Item ID
     *
     * @return bool True on success, false otherwise
     */
    public static function deleteAreaFiles(
        int $contextid,
        string $component,
        string $filearea,
        false|int $itemid = false
    ): bool {
        try {
            return self::getStorage()->delete_area_files($contextid, $component, $filearea, $itemid);
        } catch (Exception $exception) {
            Debug::traceException($exception);

            return false;
        }
    }

    /**
     * Retrieves the URL for a stored file.
     *
     * @param stored_file $file          File object
     * @param bool        $forcedownload Force download instead of display
     * @param null|string $preview       Preview mode ('thumb', 'tinyicon', 'bigicon')
     *
     * @return string File URL
     */
    public static function getFileUrl(
        stored_file $file,
        bool $forcedownload = false,
        ?string $preview = null
    ): string {
        try {
            $url = UrlSupport::pluginfile(
                $file->get_contextid(),
                $file->get_component(),
                $file->get_filearea(),
                $file->get_itemid(),
                $file->get_filepath(),
                $file->get_filename(),
                $forcedownload,
                $preview
            );

            return $url->out(false);
        } catch (Exception $exception) {
            Debug::traceException($exception);

            // Fallback to manual URL construction
            global $CFG;

            $filename = $file->get_filename();
            $itemid = $file->get_itemid();
            $filepath = $file->get_filepath();
            $contextid = $file->get_contextid();
            $component = $file->get_component();
            $filearea = $file->get_filearea();

            $url = sprintf('%s/pluginfile.php/%s/%s/%s/%s%s%s', $CFG->wwwroot, $contextid, $component, $filearea, $itemid, $filepath, $filename);

            if ($forcedownload) {
                $url .= '?forcedownload=1';
            }

            return $url;
        }
    }

    /**
     * Checks if a file area contains any files.
     *
     * @param int    $contextid Context ID
     * @param string $component Component name
     * @param string $filearea  File area name
     * @param int    $itemid    Item ID
     *
     * @return bool True if files exist, false otherwise
     */
    public static function hasFiles(
        int $contextid,
        string $component,
        string $filearea,
        int $itemid
    ): bool {
        try {
            $fs = self::getStorage();
            $files = $fs->get_area_files($contextid, $component, $filearea, $itemid, 'filename', false);

            foreach ($files as $file) {
                if (!$file->is_directory()) {
                    return true;
                }
            }

            return false;
        } catch (Exception $exception) {
            Debug::traceException($exception);

            return false;
        }
    }

    /**
     * Calculates the total size of files in a file area.
     *
     * @param int    $contextid Context ID
     * @param string $component Component name
     * @param string $filearea  File area name
     * @param int    $itemid    Item ID
     *
     * @return int Total size in bytes
     */
    public static function getAreaSize(
        int $contextid,
        string $component,
        string $filearea,
        int $itemid
    ): int {
        try {
            $totalsize = 0;
            $files = self::getAreaFiles($contextid, $component, $filearea, $itemid);

            foreach ($files as $file) {
                $totalsize += $file->get_filesize();
            }

            return $totalsize;
        } catch (Exception $exception) {
            Debug::traceException($exception);

            return 0;
        }
    }

    /**
     * Counts the number of files in a file area.
     *
     * @param int    $contextid Context ID
     * @param string $component Component name
     * @param string $filearea  File area name
     * @param int    $itemid    Item ID
     *
     * @return int Number of files
     */
    public static function countAreaFiles(
        int $contextid,
        string $component,
        string $filearea,
        int $itemid
    ): int {
        return count(self::getAreaFiles($contextid, $component, $filearea, $itemid));
    }

    /**
     * Validates if a file is a valid image.
     *
     * @param stored_file $file File to validate
     *
     * @return bool True if valid image, false otherwise
     */
    public static function isValidImage(stored_file $file): bool
    {
        try {
            return $file->is_valid_image();
        } catch (Exception $exception) {
            Debug::traceException($exception);

            return false;
        }
    }

    /**
     * Retrieves file content as a string.
     *
     * @param stored_file $file File object
     *
     * @return null|string File content or null on failure
     */
    public static function getContent(stored_file $file): ?string
    {
        try {
            return $file->get_content();
        } catch (Exception $exception) {
            Debug::traceException($exception);

            return null;
        }
    }

    /**
     * Retrieves file content as a resource handle.
     *
     * @param stored_file $file File object
     *
     * @return null|resource File handle or null on failure
     */
    public static function getContentFileHandle(stored_file $file)
    {
        try {
            return $file->get_content_file_handle();
        } catch (Exception $exception) {
            Debug::traceException($exception);

            return null;
        }
    }

    /**
     * Retrieves a file as a typed entity.
     *
     * @return null|StoredFile the entity or null if not found
     */
    public static function getFileEntity(int $contextid, string $component, string $filearea, int $itemid, string $filepath, string $filename): ?StoredFile
    {
        try {
            $fs = get_file_storage();
            $file = $fs->get_file($contextid, $component, $filearea, $itemid, $filepath, $filename);

            if (!$file || $file->is_directory()) {
                return null;
            }

            // Build a record from the stored_file object.
            $record = new stdClass();
            $record->id = $file->get_id();
            $record->contenthash = $file->get_contenthash();
            $record->pathnamehash = $file->get_pathnamehash();
            $record->contextid = $file->get_contextid();
            $record->component = $file->get_component();
            $record->filearea = $file->get_filearea();
            $record->itemid = $file->get_itemid();
            $record->filepath = $file->get_filepath();
            $record->filename = $file->get_filename();
            $record->userid = $file->get_userid();
            $record->filesize = $file->get_filesize();
            $record->mimetype = $file->get_mimetype();
            $record->status = $file->get_status();
            $record->source = $file->get_source();
            $record->author = $file->get_author();
            $record->license = $file->get_license();
            $record->timecreated = $file->get_timecreated();
            $record->timemodified = $file->get_timemodified();
            $record->sortorder = $file->get_sortorder();

            return StoredFile::fromRecord($record);
        } catch (Throwable $throwable) {
            // Trace like every other method in this class: a mapping/version-drift
            // failure must be observable, not indistinguishable from 'not found'.
            Debug::traceException($throwable);

            return null;
        }
    }

    /**
     * Retrieves all non-directory files for a file area as typed entities.
     *
     * @return array<int, StoredFile>
     */
    public static function getAreaFilesTyped(int $contextid, string $component, string $filearea, int $itemid = 0): array
    {
        try {
            $fs = get_file_storage();
            $files = $fs->get_area_files($contextid, $component, $filearea, $itemid, 'sortorder, filename', false);
            $result = [];

            foreach ($files as $file) {
                $record = new stdClass();
                $record->id = $file->get_id();
                $record->contenthash = $file->get_contenthash();
                $record->pathnamehash = $file->get_pathnamehash();
                $record->contextid = $file->get_contextid();
                $record->component = $file->get_component();
                $record->filearea = $file->get_filearea();
                $record->itemid = $file->get_itemid();
                $record->filepath = $file->get_filepath();
                $record->filename = $file->get_filename();
                $record->userid = $file->get_userid();
                $record->filesize = $file->get_filesize();
                $record->mimetype = $file->get_mimetype();
                $record->status = $file->get_status();
                $record->source = $file->get_source();
                $record->author = $file->get_author();
                $record->license = $file->get_license();
                $record->timecreated = $file->get_timecreated();
                $record->timemodified = $file->get_timemodified();
                $record->sortorder = $file->get_sortorder();

                $result[$file->get_id()] = StoredFile::fromRecord($record);
            }

            return $result;
        } catch (Throwable $throwable) {
            // See getFileEntity(): trace instead of silently returning [].
            Debug::traceException($throwable);

            return [];
        }
    }
}
