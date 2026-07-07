<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Logging;

use Middag\Moodle\Logging\MoodleLogger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Stringable;

/**
 * Test MoodleLogger.
 *
 * The PSR-3 adapter delegates to Moodle's debugging() (stubbed in
 * tests/bootstrap.php, recording into $GLOBALS['__middag_test_debugging']) and,
 * for critical levels, to PHP error_log() — captured here via a temp file.
 *
 * @internal
 */
#[CoversClass(MoodleLogger::class)]
final class MoodleLoggerCoverageTest extends TestCase
{
    private MoodleLogger $logger;

    private string $errorLogFile;

    private false|string $previousErrorLog;

    protected function setUp(): void
    {
        $this->logger = new MoodleLogger();
        $GLOBALS['__middag_test_debugging'] = [];

        $this->errorLogFile = (string) tempnam(sys_get_temp_dir(), 'mdl-log-');
        $this->previousErrorLog = ini_set('error_log', $this->errorLogFile);
    }

    protected function tearDown(): void
    {
        if ($this->previousErrorLog !== false) {
            ini_set('error_log', $this->previousErrorLog);
        }
        @unlink($this->errorLogFile);
        unset($GLOBALS['__middag_test_debugging']);
    }

    #[Test]
    public function interpolatesScalarPlaceholderFromContext(): void
    {
        $this->logger->log('info', 'hello {name}', ['name' => 'world']);

        $this->assertSame('[middag.info] hello world', $this->lastDebugging()['message']);
    }

    #[Test]
    public function mapsInfoLevelToDeveloperDebuggingLevel(): void
    {
        $this->logger->log('info', 'x');

        $this->assertSame(DEBUG_DEVELOPER, $this->lastDebugging()['level']);
    }

    #[Test]
    public function mapsWarningLevelToNormalDebuggingLevel(): void
    {
        $this->logger->log('warning', 'x');

        $this->assertSame(DEBUG_NORMAL, $this->lastDebugging()['level']);
    }

    #[Test]
    public function unknownLevelFallsBackToDeveloperAndSkipsErrorLog(): void
    {
        $this->logger->log('trace', 'x');

        $this->assertSame(DEBUG_DEVELOPER, $this->lastDebugging()['level']);
        $this->assertSame('', $this->errorLogContents());
    }

    #[Test]
    public function criticalLevelAlsoWritesToErrorLog(): void
    {
        $this->logger->log('error', 'kaput');

        $this->assertStringContainsString('[middag.error] kaput', $this->errorLogContents());
    }

    #[Test]
    public function exceptionInPlaceholderIsInterpolatedToItsMessage(): void
    {
        $this->logger->log('error', 'failed: {exception}', ['exception' => new RuntimeException('boom')]);

        $this->assertSame('[middag.error] failed: boom', $this->lastDebugging()['message']);
    }

    #[Test]
    public function stringablePlaceholderIsInterpolated(): void
    {
        $value = new class implements Stringable {
            public function __toString(): string
            {
                return 'stringy';
            }
        };

        $this->logger->log('debug', 'v={val}', ['val' => $value]);

        $this->assertSame('[middag.debug] v=stringy', $this->lastDebugging()['message']);
    }

    #[Test]
    public function stringableLevelAndMessageAreCastToString(): void
    {
        $level = new class implements Stringable {
            public function __toString(): string
            {
                return 'notice';
            }
        };
        $message = new class implements Stringable {
            public function __toString(): string
            {
                return 'msg';
            }
        };

        $this->logger->log($level, $message);

        $this->assertSame('[middag.notice] msg', $this->lastDebugging()['message']);
    }

    #[Test]
    public function nonScalarPlaceholderIsLeftLiteralAndAppendedAsExtra(): void
    {
        $this->logger->log('debug', 'data={obj}', ['obj' => ['a' => 1]]);

        // {obj} stays literal (array is not scalar/Stringable/Throwable), and the
        // array is rendered as a JSON pair in the trailing extras bracket.
        $this->assertSame('[middag.debug] data={obj} [obj={"a":1}]', $this->lastDebugging()['message']);
    }

    #[Test]
    public function extraScalarAndThrowableContextRenderedAsPairs(): void
    {
        $this->logger->log('warning', 'msg', ['count' => 3, 'exception' => new RuntimeException('bad')]);

        $this->assertSame('[middag.warning] msg [count=3, exception=bad]', $this->lastDebugging()['message']);
    }

    #[Test]
    public function extraWithNoRenderablePairsAppendsNothing(): void
    {
        // A bare object (not scalar/array/Throwable) contributes no pair.
        $this->logger->log('info', 'plain', ['obj' => new stdClass()]);

        $this->assertSame('[middag.info] plain', $this->lastDebugging()['message']);
    }

    /**
     * @return array{message: string, level: null|int}
     */
    private function lastDebugging(): array
    {
        $entries = $GLOBALS['__middag_test_debugging'];

        return end($entries);
    }

    private function errorLogContents(): string
    {
        return (string) file_get_contents($this->errorLogFile);
    }
}
