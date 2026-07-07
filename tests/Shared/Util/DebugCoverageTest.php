<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Shared\Util;

use Exception;
use JsonException;
use Middag\Framework\Shared\Enum\DebugMode;
use Middag\Moodle\Shared\Util\Debug;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;

/**
 * The Moodle-flavour Debug overrides the framework base to route output through
 * mtrace() and to surface Moodle exception fields (debuginfo/module/sql/params)
 * plus a format_backtrace() trace block. Both Moodle globals are provided as
 * recording stubs (tests/stubs/areas/shared-debug.php), so the override is
 * exercised through its real public entry points — Debug::trace() and
 * Debug::traceException() — with the runtime wired to a debug level that always
 * enables emission. Each emitted line is asserted from the recorded mtrace log.
 *
 * @internal
 */
#[CoversClass(Debug::class)]
final class DebugCoverageTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['__middag_test_mtrace'] = [];
        $GLOBALS['__middag_test_format_backtrace_calls'] = [];

        // Wire the static runtime so the debug gate always permits emission
        // (FULL >= NORMAL). The logger is never touched: emit() routes to mtrace.
        Debug::setRuntime(new NullLogger(), static fn (): int => DebugMode::FULL->value);
    }

    protected function tearDown(): void
    {
        Debug::resetRuntime();
        unset($GLOBALS['__middag_test_mtrace'], $GLOBALS['__middag_test_format_backtrace_calls']);
    }

    #[Test]
    public function traceRoutesTheMessageThroughMtrace(): void
    {
        // emit(): mtrace exists → the message is emitted and the parent PSR-3
        // sink is bypassed.
        Debug::trace('cron heartbeat');

        self::assertSame(['cron heartbeat'], $GLOBALS['__middag_test_mtrace']);
    }

    #[Test]
    public function traceExceptionEmitsEveryMoodleFieldThroughMtrace(): void
    {
        $exception = new MoodleFlavorTestException(
            'kaboom',
            debuginfo: 'dbg-detail',
            module: 'mod_unidade',
            sql: 'SELECT 1 FROM x',
            params: ['a' => 1, 'b' => 2],
        );

        Debug::traceException($exception);

        $lines = $GLOBALS['__middag_test_mtrace'];

        self::assertSame([
            '@@@@@@ EXCEPTION @@@@@@',
            'Code: 0',
            'Message: kaboom',
            'Trace: ',
            'FORMATTED_BACKTRACE',
            'DEBUGINFO: dbg-detail',
            'Module: mod_unidade',
            'SQL: SELECT 1 FROM x',
            'SQL PARAMS: ' . json_encode(['a' => 1, 'b' => 2], JSON_THROW_ON_ERROR),
        ], $lines);

        // formatTrace() delegated to format_backtrace() with the exception's
        // trace and a boolean cli flag.
        self::assertCount(1, $GLOBALS['__middag_test_format_backtrace_calls']);
        $call = $GLOBALS['__middag_test_format_backtrace_calls'][0];
        self::assertIsArray($call['callers']);
        self::assertIsBool($call['plaintext']);
    }

    #[Test]
    public function traceExceptionOmitsMoodleFieldsForAPlainThrowable(): void
    {
        // A plain throwable has none of the Moodle properties, so property_exists
        // is false for each and only the platform-agnostic block is emitted.
        Debug::traceException(new RuntimeException('boom'));

        self::assertSame([
            '@@@@@@ EXCEPTION @@@@@@',
            'Code: 0',
            'Message: boom',
            'Trace: ',
            'FORMATTED_BACKTRACE',
        ], $GLOBALS['__middag_test_mtrace']);
    }

    #[Test]
    public function traceExceptionSkipsMoodleFieldsThatAreNull(): void
    {
        // The properties exist (property_exists true) but are null, so the
        // `!== null` guard drops each of debuginfo/module/sql.
        $exception = new MoodleFlavorTestException('nullfields');

        Debug::traceException($exception);

        self::assertSame([
            '@@@@@@ EXCEPTION @@@@@@',
            'Code: 0',
            'Message: nullfields',
            'Trace: ',
            'FORMATTED_BACKTRACE',
        ], $GLOBALS['__middag_test_mtrace']);
    }

    #[Test]
    public function traceExceptionEmitsSqlButOmitsParamsWhenParamsIsNotAnArray(): void
    {
        // sql present, but params is present-and-null: is_array() is false, so
        // the SQL PARAMS line is skipped while the SQL line is still emitted.
        $exception = new MoodleFlavorTestException('withsql', sql: 'DELETE FROM x');

        Debug::traceException($exception);

        self::assertSame([
            '@@@@@@ EXCEPTION @@@@@@',
            'Code: 0',
            'Message: withsql',
            'Trace: ',
            'FORMATTED_BACKTRACE',
            'SQL: DELETE FROM x',
        ], $GLOBALS['__middag_test_mtrace']);
    }

    #[Test]
    public function traceExceptionEmitsTheJsonExceptionMessageWhenParamsCannotBeEncoded(): void
    {
        // Malformed UTF-8 makes json_encode(JSON_THROW_ON_ERROR) throw; the
        // catch branch surfaces the JsonException message instead of the JSON.
        $badParams = ["\xB1\x31"];

        $expectedMessage = '';

        try {
            json_encode($badParams, JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            $expectedMessage = $jsonException->getMessage();
        }
        self::assertNotSame('', $expectedMessage, 'fixture guard: params must be non-encodable');

        $exception = new MoodleFlavorTestException('jsonfail', sql: 'SELECT 2', params: $badParams);

        Debug::traceException($exception);

        self::assertSame([
            '@@@@@@ EXCEPTION @@@@@@',
            'Code: 0',
            'Message: jsonfail',
            'Trace: ',
            'FORMATTED_BACKTRACE',
            'SQL: SELECT 2',
            'SQL PARAMS: ' . $expectedMessage,
        ], $GLOBALS['__middag_test_mtrace']);
    }
}

/**
 * Test double emulating a moodle_exception/dml_exception carrying the optional
 * debuginfo/module/sql/params fields that Debug::formatExceptionLines() surfaces.
 * The properties are always declared (so property_exists is true) and default to
 * null, letting each test drive the present/absent branches by value.
 *
 * @internal
 */
final class MoodleFlavorTestException extends Exception
{
    public function __construct(
        string $message = '',
        public ?string $debuginfo = null,
        public ?string $module = null,
        public ?string $sql = null,
        public mixed $params = null,
    ) {
        parent::__construct($message);
    }
}
