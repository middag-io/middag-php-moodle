<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Statics;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;

/**
 * Writes rendered statics PHP files to disk.
 *
 * Supports a dry-run mode that logs the write but does not touch the
 * filesystem. Idempotent: if the destination file already contains the
 * exact content being written, no write occurs and the method returns
 * false (so callers can skip noop git diffs).
 *
 * @api
 */
final readonly class StaticsWriter
{
    public function __construct(
        private LoggerInterface $logger = new NullLogger(),
        private bool $dryRun = false,
    ) {}

    /**
     * Returns true if a write actually occurred (or would occur in dry-run).
     * Returns false if the file already contained the exact target content.
     */
    public function write(string $path, string $content): bool
    {
        if (is_file($path) && file_get_contents($path) === $content) {
            $this->logger->debug('UNCHANGED: ' . $path);

            return false;
        }

        if ($this->dryRun) {
            $this->logger->info('WOULD WRITE: ' . $path);

            return true;
        }

        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0o777, true) && !is_dir($dir)) {
            throw new RuntimeException(sprintf('Cannot create directory: %s', $dir));
        }

        if (file_put_contents($path, $content) === false) {
            throw new RuntimeException(sprintf('Cannot write file: %s', $path));
        }

        $this->logger->info('WROTE: ' . $path);

        return true;
    }
}
