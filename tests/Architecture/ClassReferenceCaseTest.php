<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Architecture;

use FilesystemIterator;
use PhpToken;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Architecture guard: every reference to a class declared under src/ MUST use
 * the exact case of its declaration.
 *
 * PHP resolves class names case-insensitively once a class is loaded, but the
 * PSR-4 autoloader maps the name to a file path. On a case-sensitive
 * filesystem `new authentication()` looks for `authentication.php` and fails
 * when `Authentication.php` has not been loaded yet.
 *
 * @internal
 */
#[CoversNothing]
final class ClassReferenceCaseTest extends TestCase
{
    private const string ROOT_NAMESPACE = 'Middag\Moodle\\';

    /** Previous significant tokens after which a bare name is a class reference. */
    private const array CLASS_PREFIX_TOKENS = [T_NEW, T_INSTANCEOF, T_EXTENDS, T_IMPLEMENTS];

    /** Next significant tokens before which a bare name is a class reference. */
    private const array CLASS_SUFFIX_TOKENS = [T_DOUBLE_COLON, T_VARIABLE, T_ELLIPSIS];

    /** Previous significant tokens after which a bare name is never a class. */
    private const array NON_CLASS_PREFIX_TOKENS = [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_CONST];

    #[Test]
    public function classReferencesUseTheDeclaredCase(): void
    {
        $classes = $this->declaredClasses();
        $violations = [];

        foreach ($this->sourceFiles() as $relative => $path) {
            foreach ($this->referencedNames((string) file_get_contents($path)) as [$name, $line]) {
                $declared = $classes[strtolower($name)] ?? null;

                if ($declared !== null && $declared !== $name) {
                    $violations[] = sprintf('%s:%d references %s, declared as %s', $relative, $line, $name, $declared);
                }
            }
        }

        $this->assertSame([], $violations);
    }

    /**
     * Class names that PSR-4 maps from the src/ tree, keyed by lowercase name.
     *
     * @return array<string, string>
     */
    private function declaredClasses(): array
    {
        $classes = [];

        foreach (array_keys($this->sourceFiles()) as $relative) {
            $class = self::ROOT_NAMESPACE . str_replace('/', '\\', substr($relative, 0, -4));
            $classes[strtolower($class)] = $class;
        }

        return $classes;
    }

    /**
     * @return array<string, string> path relative to src/ => absolute path
     */
    private function sourceFiles(): array
    {
        $src = dirname(__DIR__, 2) . '/src';
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[substr($file->getPathname(), strlen($src) + 1)] = $file->getPathname();
            }
        }

        ksort($files);

        return $files;
    }

    /**
     * Fully qualified class names referenced by a file, with their line.
     *
     * @return list<array{string, int}>
     */
    private function referencedNames(string $code): array
    {
        $tokens = array_values(array_filter(
            PhpToken::tokenize($code),
            static fn (PhpToken $token): bool => !$token->isIgnorable(),
        ));
        $namespace = '';
        $imports = [];
        $names = [];

        foreach ($tokens as $i => $token) {
            if ($token->is(T_NAMESPACE) && isset($tokens[$i + 1])) {
                $namespace = $tokens[$i + 1]->text;

                continue;
            }

            if ($token->is([T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED])) {
                $names[] = [ltrim($token->text, '\\'), $token->line];
                $imports[strtolower(substr(strrchr('\\' . $token->text, '\\'), 1))] = true;

                continue;
            }

            if ($token->is(T_STRING) && $this->isClassReference($tokens, $i) && !isset($imports[strtolower($token->text)])) {
                $names[] = [$namespace . '\\' . $token->text, $token->line];
            }
        }

        return $names;
    }

    /**
     * @param list<PhpToken> $tokens
     */
    private function isClassReference(array $tokens, int $i): bool
    {
        $previous = $tokens[$i - 1] ?? null;
        $next = $tokens[$i + 1] ?? null;

        if ($previous?->is(self::NON_CLASS_PREFIX_TOKENS)) {
            return false;
        }

        return ($previous?->is(self::CLASS_PREFIX_TOKENS) ?? false)
            || ($next?->is(self::CLASS_SUFFIX_TOKENS) ?? false);
    }
}
