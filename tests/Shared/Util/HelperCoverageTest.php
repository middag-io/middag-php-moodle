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

use core\output\core_renderer;
use core\output\notification;
use Middag\Moodle\Shared\Util\Debug;
use Middag\Moodle\Shared\Util\Helper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Helper carries a single static utility: installOrUpgradeLog() renders a Moodle
 * notification through the active renderer. It is exercised for both
 * renderer-resolution paths (global $OUTPUT already a core_renderer vs. the
 * $PAGE->get_renderer('core') fallback), for the default success type, and for
 * the catch branch when rendering throws. The former customArrayMerge() moved
 * to the framework Arr::mergePreferNonNull() and is covered there.
 *
 * Debug::resetRuntime() runs in setUp so the trace sink is quiescent: with no
 * configured debug mode, Helper's catch branch delegate (debug::traceException)
 * short-circuits before emitting, so a swallowed render failure produces no
 * output. Resetting also loads the Debug class under its canonical name.
 *
 * @internal
 */
#[CoversClass(Helper::class)]
final class HelperCoverageTest extends TestCase
{
    private mixed $prevOutput;

    private mixed $prevPage;

    protected function setUp(): void
    {
        $this->prevOutput = $GLOBALS['OUTPUT'] ?? null;
        $this->prevPage = $GLOBALS['PAGE'] ?? null;

        unset(
            $GLOBALS['__middag_test_helper_rendered'],
            $GLOBALS['__middag_test_helper_render_throw'],
            $GLOBALS['__middag_test_helper_get_renderer'],
        );

        // Quiescent trace sink: no configured debug mode => traceException emits
        // nothing, so the catch branch stays output-free. Also loads Debug under
        // its canonical class name.
        Debug::resetRuntime();

        // Default: the site already exposes a core_renderer as $OUTPUT, so the
        // $PAGE fallback is only taken by the test that overrides $OUTPUT.
        $GLOBALS['OUTPUT'] = new core_renderer();
        $GLOBALS['PAGE'] = $this->makePage();
    }

    protected function tearDown(): void
    {
        $GLOBALS['OUTPUT'] = $this->prevOutput;
        $GLOBALS['PAGE'] = $this->prevPage;

        unset(
            $GLOBALS['__middag_test_helper_rendered'],
            $GLOBALS['__middag_test_helper_render_throw'],
            $GLOBALS['__middag_test_helper_get_renderer'],
        );

        Debug::resetRuntime();
    }

    // --- installOrUpgradeLog() ------------------------------------------------

    #[Test]
    public function installOrUpgradeLogRendersThroughTheGlobalOutputWhenItIsACoreRenderer(): void
    {
        $output = $this->captureLog(static function (): void {
            Helper::installOrUpgradeLog('Saved successfully', notification::NOTIFY_SUCCESS);
        });

        // The global core_renderer was used directly — the $PAGE fallback was not.
        self::assertArrayNotHasKey('__middag_test_helper_get_renderer', $GLOBALS);

        $rendered = $GLOBALS['__middag_test_helper_rendered'][0] ?? null;
        self::assertNotNull($rendered);
        self::assertSame('core/notification_success', $rendered['template']);
        self::assertSame('Saved successfully', $rendered['context']['message']);
        self::assertSame('success', $rendered['context']['type']);
        // The notification is built with the close button suppressed.
        self::assertFalse($rendered['context']['closebutton']);

        // The echoed output is exactly the renderer's return value.
        self::assertStringContainsString('core/notification_success', $output);
        self::assertStringContainsString('Saved successfully', $output);
    }

    #[Test]
    public function installOrUpgradeLogDefaultsToTheSuccessNotificationType(): void
    {
        $this->captureLog(static function (): void {
            Helper::installOrUpgradeLog('Done');
        });

        $rendered = $GLOBALS['__middag_test_helper_rendered'][0] ?? null;
        self::assertNotNull($rendered);
        // Omitting $type resolves the notification::NOTIFY_SUCCESS default.
        self::assertSame('success', $rendered['context']['type']);
        self::assertSame('core/notification_success', $rendered['template']);
    }

    #[Test]
    public function installOrUpgradeLogFallsBackToThePageRendererWhenOutputIsNotACoreRenderer(): void
    {
        // $OUTPUT is present but not a core_renderer => the instanceof guard fails
        // and the renderer is resolved via $PAGE->get_renderer('core').
        $GLOBALS['OUTPUT'] = new stdClass();

        $output = $this->captureLog(static function (): void {
            Helper::installOrUpgradeLog('Upgraded', notification::NOTIFY_INFO);
        });

        self::assertSame('core', $GLOBALS['__middag_test_helper_get_renderer'] ?? null);

        $rendered = $GLOBALS['__middag_test_helper_rendered'][0] ?? null;
        self::assertNotNull($rendered);
        self::assertSame('core/notification_info', $rendered['template']);
        self::assertSame('Upgraded', $rendered['context']['message']);
        self::assertStringContainsString('core/notification_info', $output);
    }

    #[Test]
    public function installOrUpgradeLogSwallowsARenderFailureAndEmitsNoOutput(): void
    {
        // Force render_from_template to throw so the try/catch is exercised. The
        // exception is caught and routed to debug::traceException, which with a
        // quiescent debug runtime emits nothing; no exception escapes the wrapper.
        $GLOBALS['__middag_test_helper_render_throw'] = true;

        $output = $this->captureLog(static function (): void {
            Helper::installOrUpgradeLog('This will fail', notification::NOTIFY_ERROR);
        });

        // Rendering was attempted (proving the catch is reached from the try body)
        // yet nothing was echoed because the failure was swallowed.
        self::assertArrayHasKey('__middag_test_helper_rendered', $GLOBALS);
        self::assertSame('', $output);
    }

    /**
     * Runs $callable with output buffering, returning whatever it echoed and
     * always tearing the buffer down even if the callable throws.
     */
    private function captureLog(callable $callable): string
    {
        ob_start();

        try {
            $callable();
        } finally {
            $output = ob_get_clean();
        }

        return (string) $output;
    }

    /**
     * A stand-in $PAGE whose get_renderer('core') records the requested component
     * and returns a fresh core_renderer, mirroring Moodle's renderer factory.
     */
    private function makePage(): object
    {
        return new class {
            public function get_renderer(string $component): core_renderer
            {
                $GLOBALS['__middag_test_helper_get_renderer'] = $component;

                return new core_renderer();
            }
        };
    }
}
