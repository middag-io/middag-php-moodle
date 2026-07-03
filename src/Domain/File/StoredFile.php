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

use Middag\Moodle\Domain\AbstractMoodleEntity;

/**
 * Stored file entity (Moodle File API).
 *
 * Wraps a row of `mdl_files`.
 *
 * @method string      get_contenthash()
 * @method self        with_contenthash(string $contenthash)
 * @method string      get_pathnamehash()
 * @method self        with_pathnamehash(string $pathnamehash)
 * @method int         get_contextid()
 * @method self        with_contextid(int $contextid)
 * @method string      get_component()
 * @method self        with_component(string $component)
 * @method string      get_filearea()
 * @method self        with_filearea(string $filearea)
 * @method int         get_itemid()
 * @method self        with_itemid(int $itemid)
 * @method string      get_filepath()
 * @method self        with_filepath(string $filepath)
 * @method string      get_filename()
 * @method self        with_filename(string $filename)
 * @method int         get_userid()
 * @method self        with_userid(int $userid)
 * @method int         get_filesize()
 * @method self        with_filesize(int $filesize)
 * @method string      get_mimetype()
 * @method self        with_mimetype(string $mimetype)
 * @method int         get_status()
 * @method self        with_status(int $status)
 * @method null|string get_source()
 * @method self        with_source(?string $source)
 * @method null|string get_author()
 * @method self        with_author(?string $author)
 * @method null|string get_license()
 * @method self        with_license(?string $license)
 * @method int         get_sortorder()
 * @method self        with_sortorder(int $sortorder)
 *
 * @api
 */
class StoredFile extends AbstractMoodleEntity
{
    protected string $contenthash = '';

    protected string $pathnamehash = '';

    protected int $contextid = 0;

    protected string $component = '';

    protected string $filearea = '';

    protected int $itemid = 0;

    protected string $filepath = '/';

    protected string $filename = '';

    protected int $userid = 0;

    protected int $filesize = 0;

    protected string $mimetype = '';

    protected int $status = 0;

    protected ?string $source = null;

    protected ?string $author = null;

    protected ?string $license = null;

    protected int $sortorder = 0;

    /**
     * {@inheritDoc}
     */
    public static function getTable(): string
    {
        return 'files';
    }

    /**
     * Whether this record represents a directory entry.
     */
    public function isDirectory(): bool
    {
        return $this->filename === '.';
    }
}
