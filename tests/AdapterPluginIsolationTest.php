<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Adapter-wide product-coupling guard.
 *
 * The middag-io/moodle adapter is a generic, reusable Apache-2.0 Moodle package.
 * It must never reference the consumer plugin's namespace ({@code local_middag\...}),
 * any MIDDAG namespace outside the OSS packages it builds on, or hard-code product
 * gold tables. This test source-scans every adapter `src/` file so any regression
 * fails loudly. Uses source inspection rather than class loading to avoid pulling
 * in a full Moodle runtime.
 *
 * @internal
 */
#[CoversNothing]
final class AdapterPluginIsolationTest extends TestCase
{
    private const string SRC_DIR = __DIR__ . '/../src';

    /** Consumer-plugin namespace prefixes the OSS adapter must never reference. */
    private const array FORBIDDEN_NAMESPACES = [
        'local_middag\\',
    ];

    /**
     * The only MIDDAG namespaces the adapter may reference: itself and the OSS
     * packages it depends on. Any other `Middag\*` namespace is a non-OSS
     * MIDDAG package and fails the guard.
     */
    private const array ALLOWED_MIDDAG_NAMESPACES = [
        'Moodle',
        'Framework',
        'Ui',
    ];

    /** Gold-table string literals the adapter must not hard-code. */
    private const array FORBIDDEN_TABLE_LITERALS = [
        "'middag_items'",
        "'middag_itemmeta'",
        "'middag_item_revision'",
        "'middag_activity_feed'",
        "'middag_audit_log'",
        "'middag_job'",
    ];

    #[DataProvider('sourceFileProvider')]
    public function testFileDoesNotReferencePluginOrProprietaryNamespaces(string $path): void
    {
        $source = file_get_contents($path);
        self::assertIsString($source);

        foreach (self::FORBIDDEN_NAMESPACES as $namespace) {
            self::assertStringNotContainsString(
                $namespace,
                $source,
                sprintf('Adapter file must not reference %s — keep the adapter product-agnostic.', $namespace),
            );
        }

        preg_match_all('/\bMiddag\\\+(\w+)\\\/', $source, $matches);
        foreach (array_unique($matches[1]) as $namespace) {
            self::assertContains(
                $namespace,
                self::ALLOWED_MIDDAG_NAMESPACES,
                sprintf('Adapter file must not reference Middag\%s — only the OSS MIDDAG packages are allowed.', $namespace),
            );
        }
    }

    #[DataProvider('sourceFileProvider')]
    public function testFileDoesNotHardcodeGoldTables(string $path): void
    {
        $source = file_get_contents($path);
        self::assertIsString($source);

        foreach (self::FORBIDDEN_TABLE_LITERALS as $literal) {
            self::assertStringNotContainsString(
                $literal,
                $source,
                sprintf('Adapter file must not hard-code gold table literal %s — accept it as input.', $literal),
            );
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function sourceFileProvider(): iterable
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(self::SRC_DIR, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        $base = realpath(self::SRC_DIR) ?: self::SRC_DIR;

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $path = $file->getPathname();
            $label = substr($path, strlen($base) + 1);

            yield $label => [$path];
        }
    }
}
