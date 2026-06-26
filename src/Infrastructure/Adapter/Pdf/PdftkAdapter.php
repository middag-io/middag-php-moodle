<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Infrastructure\Adapter\Pdf;

use Exception;
use Middag\Moodle\Shared\Util\Debug as debug;
use Middag\Moodle\Support\ConfigSupport as config_support;
use mikehaertl\pdftk\Pdf;
use RuntimeException;

/**
 * Infrastructure service to handle PDF manipulations using PDFTk.
 * Wrapper around mikehaertl/php-pdftk.
 *
 * @internal
 */
class PdftkAdapter
{
    /** @var null|string Cache for the resolved binary path */
    private static ?string $binaryPath = null;

    /**
     * Check if PDFTk is available and executable.
     */
    public static function isAvailable(): bool
    {
        return self::resolveBinaryPath() !== null;
    }

    /**
     * Get a configured instance of the PDFTk wrapper.
     *
     * @param null|array|string $pdf_path path to PDF file or array of paths
     *
     * @return Pdf
     *
     * @throws RuntimeException if PDFTk is not available
     */
    public static function getInstance(array|string|null $pdf_path = null): Pdf
    {
        $binary = self::resolveBinaryPath();

        if ($binary === null) {
            throw new RuntimeException('PDFTk binary not found or not executable.');
        }

        $options = [
            'command' => $binary,
            'useExec' => true, // Force execution mode for better compatibility
        ];

        return new Pdf($pdf_path, $options);
    }

    /**
     * Uncompress a PDF file stream.
     * Useful for parsing PDF content.
     *
     * @param string $content Raw PDF content
     *
     * @return string Uncompressed PDF content
     *
     * @throws Exception
     */
    public static function uncompressContent(string $content): string
    {
        $temp_dir = make_request_directory();
        $temp_file = $temp_dir . '/' . uniqid('pdftk_', true) . '.pdf';
        $output_file = $temp_dir . '/' . uniqid('pdftk_out_', true) . '.pdf';

        try {
            // Write raw content to temp file
            file_put_contents($temp_file, $content);

            $pdf = self::getInstance($temp_file);

            // The library handles the command construction: `pdftk input.pdf output output.pdf uncompress`
            $pdf->compress(false); // false = uncompress

            if (!$pdf->saveAs($output_file)) {
                $error = $pdf->getError();

                throw new RuntimeException('PDFTk Error: ' . ($error ?: 'Unknown error during decompression'));
            }

            if (!file_exists($output_file)) {
                throw new RuntimeException('PDFTk failed to generate output file.');
            }

            return file_get_contents($output_file);
        } catch (Exception $exception) {
            debug::traceException($exception);

            throw $exception;
        } finally {
            // Cleanup
            if (file_exists($temp_file)) {
                unlink($temp_file);
            }
            if (file_exists($output_file)) {
                unlink($output_file);
            }
        }
    }

    /**
     * Resolve the path to the PDFTk binary.
     * Priority:
     * 1. Moodle Config (local_example | pdftk_path)
     * 2. Local bin folder (legacy support)
     * 3. System PATH (where/which).
     */
    private static function resolveBinaryPath(): ?string
    {
        if (self::$binaryPath !== null) {
            return self::$binaryPath;
        }

        // 1. Check explicit configuration in Moodle
        $config_path = config_support::get('pdftk_path');
        if ($config_path && self::isExecutablePath($config_path)) {
            self::$binaryPath = $config_path;

            return $config_path;
        }

        // 2. Check legacy local bin path (local/middag/bin/pdftk)
        $local_path = config_support::getGlobal('dirroot') . '/local/middag/bin/pdftk';
        if (self::isExecutablePath($local_path)) {
            self::$binaryPath = $local_path;

            return $local_path;
        }

        // 3. Auto-discovery via shell
        $command_search = (PHP_OS_FAMILY === 'Windows') ? 'where' : 'which';
        $system_path = shell_exec(sprintf('%s pdftk', $command_search));

        if ($system_path) {
            $system_path = trim($system_path);
            // Handle cases where `where` returns multiple lines on Windows
            $lines = preg_split('/\r\n|\r|\n/', $system_path);
            if (!empty($lines) && self::isExecutablePath($lines[0])) {
                self::$binaryPath = $lines[0];

                return $lines[0];
            }
        }

        return null;
    }

    /**
     * Verify if a path exists and is executable.
     */
    private static function isExecutablePath(string $path): bool
    {
        return file_exists($path) && is_executable($path);
    }
}
