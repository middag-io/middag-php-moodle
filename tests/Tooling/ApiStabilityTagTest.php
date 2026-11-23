<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Tooling;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Proves the claim in API-STABILITY.md that every type in src/ carries exactly
 * one of the two stability tags, `@api` or `@internal`: a type with neither, or
 * with both, fails here.
 *
 * @internal
 */
#[CoversNothing]
final class ApiStabilityTagTest extends TestCase
{
    #[Test]
    public function everyTypeInSrcCarriesExactlyOneOfApiOrInternal(): void
    {
        $scanned = 0;
        $violations = [];

        foreach ($this->declarations() as $declaration) {
            ++$scanned;
            $tags = (int) (1 === preg_match('/@api\b/', $declaration['docblock']))
                + (int) (1 === preg_match('/@internal\b/', $declaration['docblock']));

            if (1 !== $tags) {
                $violations[] = $declaration['type'] . (0 === $tags ? ' has neither tag' : ' has both tags');
            }
        }

        self::assertGreaterThan(0, $scanned, 'no type found in src/: the scan is broken');
        self::assertSame([], $violations, 'types that do not carry exactly one of @api / @internal');
    }

    /**
     * Named class-like declarations with the docblock that directly precedes
     * them (attributes and modifiers in between are allowed). Uses the PHP
     * tokenizer, so `::class`, anonymous classes and prose in comments never match.
     *
     * @return list<array{type: string, docblock: string}>
     */
    private function declarations(): array
    {
        $declarations = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2) . '/src'));

        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            if ('php' !== $file->getExtension()) {
                continue;
            }

            $namespace = '';
            $docblock = '';
            $previous = null;
            $tokens = token_get_all((string) file_get_contents($file->getPathname()));

            foreach ($tokens as $index => $token) {
                if (!is_array($token)) {
                    if (';' === $token || '{' === $token || '}' === $token) {
                        $docblock = '';
                    }

                    continue;
                }

                if (T_WHITESPACE === $token[0] || T_ATTRIBUTE === $token[0]) {
                    continue;
                }

                if (T_DOC_COMMENT === $token[0]) {
                    $docblock = $token[1];
                } elseif (T_NAMESPACE === $token[0]) {
                    $namespace = $this->readName($tokens, $index + 1);
                } elseif (in_array($token[0], [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true) && T_DOUBLE_COLON !== $previous && T_NEW !== $previous) {
                    $name = $this->readName($tokens, $index + 1);
                    $declarations[] = ['type' => ('' === $namespace ? '' : $namespace . '\\') . $name, 'docblock' => $docblock];
                    $docblock = '';
                }

                $previous = $token[0];
            }
        }

        return $declarations;
    }

    /**
     * First identifier or qualified name after $from.
     *
     * @param list<array{int, string, int}|string> $tokens
     */
    private function readName(array $tokens, int $from): string
    {
        $name = '';
        $nameTokens = [T_STRING, T_NAME_QUALIFIED];

        for ($i = $from, $count = count($tokens); $i < $count; ++$i) {
            $token = $tokens[$i];
            if (is_array($token) && in_array($token[0], $nameTokens, true)) {
                $name .= $token[1];

                continue;
            }

            if ('' !== $name || !is_array($token) || T_WHITESPACE !== $token[0]) {
                break;
            }
        }

        return $name;
    }
}
