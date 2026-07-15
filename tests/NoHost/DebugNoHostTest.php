<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\NoHost;

use Middag\Moodle\Shared\Util\Debug;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use RuntimeException;
use Stringable;

/**
 * @internal
 */
#[CoversClass(Debug::class)]
final class DebugNoHostTest extends TestCase
{
    /** @var list<string> */
    private array $lines = [];

    protected function setUp(): void
    {
        $this->lines = [];
        $spy = new class($this->lines) extends AbstractLogger {
            /** @param list<string> $lines */
            public function __construct(private array &$lines) {}

            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->lines[] = (string) $message;
            }
        };

        // Debug mode fully enabled so trace()/traceException() reach emit().
        Debug::setRuntime($spy, static fn (): int => 2);
    }

    protected function tearDown(): void
    {
        Debug::resetRuntime();
    }

    public function testTraceFallsBackToPsrSinkWithoutMtrace(): void
    {
        Debug::trace('no-host trace line');

        self::assertSame(['no-host trace line'], $this->lines);
    }

    public function testTraceExceptionUsesNativeTraceWithoutFormatBacktrace(): void
    {
        Debug::traceException(new RuntimeException('boom'));

        $output = implode("\n", $this->lines);
        self::assertStringContainsString('@@@@@@ EXCEPTION @@@@@@', $output);
        self::assertStringContainsString('Message: boom', $output);
        // format_backtrace() is undefined here: the trace block is PHP's native
        // getTraceAsString() frame list.
        self::assertStringContainsString('#0 ', $output);
    }
}
