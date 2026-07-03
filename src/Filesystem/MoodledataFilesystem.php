<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Filesystem;

use Middag\Framework\Exception\MiddagInfrastructureException;
use Middag\Framework\Filesystem\Contract\FilesystemInterface;
use Middag\Framework\Filesystem\LocalFilesystem;

/**
 * Moodle implementation of the framework filesystem port, rooted at the
 * site's dataroot (`$CFG->dataroot`) — the one location a plugin may write
 * to on every Moodle host. Delegates to the framework's
 * {@see LocalFilesystem} for the actual path-jailed IO.
 *
 * This is the plain-file seam (logs, exports, caches). Files that belong to
 * users/courses go through the Moodle File API (`Domain\File\FileService`),
 * not through this port.
 *
 * @api
 */
final readonly class MoodledataFilesystem implements FilesystemInterface
{
    private FilesystemInterface $inner;

    /**
     * @param string $subdirectory optional namespace under dataroot (e.g. "middag")
     *
     * @throws MiddagInfrastructureException when the dataroot cannot be resolved
     */
    public function __construct(string $subdirectory = '', ?string $baseDir = null)
    {
        global $CFG;

        $root = $baseDir ?? (isset($CFG->dataroot) ? (string) $CFG->dataroot : '');

        if ($root === '') {
            throw new MiddagInfrastructureException('Cannot resolve the Moodle dataroot outside a Moodle runtime.');
        }

        if ($subdirectory !== '') {
            $root .= '/' . trim($subdirectory, '/');
        }

        if (!is_dir($root) && !mkdir($root, 0o755, true) && !is_dir($root)) {
            throw new MiddagInfrastructureException(sprintf('Cannot create dataroot directory "%s".', $root));
        }

        $this->inner = new LocalFilesystem($root);
    }

    public function exists(string $path): bool
    {
        return $this->inner->exists($path);
    }

    public function read(string $path): string
    {
        return $this->inner->read($path);
    }

    public function write(string $path, string $contents): void
    {
        $this->inner->write($path, $contents);
    }

    public function delete(string $path): void
    {
        $this->inner->delete($path);
    }
}
