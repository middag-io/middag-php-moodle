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

use core\context;
use stored_file;

/**
 * File area handler contract.
 *
 * Extensions implement this interface to handle file serving
 * for their declared file areas. The pluginfile delegation
 * resolves the handler from the file area definition and
 * delegates the request.
 *
 * @api
 */
interface FileAreaHandlerInterface
{
    /**
     * Determine if the current user can access the requested file.
     *
     * @param context $context  Moodle context for the file
     * @param string  $filearea the file area name
     * @param int     $itemid   the item ID
     * @param string  $filepath the file path within the area
     * @param string  $filename the file name
     */
    public function canAccess(context $context, string $filearea, int $itemid, string $filepath, string $filename): bool;

    /**
     * Serve the requested file.
     *
     * @param stored_file $file          the stored file to serve
     * @param bool        $forcedownload whether to force download
     */
    public function serve(stored_file $file, bool $forcedownload = false): void;
}
