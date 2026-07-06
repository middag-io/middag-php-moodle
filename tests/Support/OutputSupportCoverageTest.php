<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Support;

use core\output\renderable;
use Middag\Moodle\Support\OutputSupport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * OutputSupport delegates to the global $OUTPUT. It is replaced with a recording
 * double so each wrapper's effect is observable without a Moodle runtime.
 *
 * @internal
 */
#[CoversClass(OutputSupport::class)]
final class OutputSupportCoverageTest extends TestCase
{
    private mixed $prevOutput;

    protected function setUp(): void
    {
        $this->prevOutput = $GLOBALS['OUTPUT'] ?? null;
        $GLOBALS['OUTPUT'] = $this->makeOutput();
    }

    protected function tearDown(): void
    {
        $GLOBALS['OUTPUT'] = $this->prevOutput;
    }

    #[Test]
    public function testBoxForwardsArgumentsAndReturnsTheRenderedBox(): void
    {
        self::assertSame(
            'box:contents|classes:mybox|id:box1',
            OutputSupport::box('contents', 'mybox', 'box1'),
        );
    }

    #[Test]
    public function testHeaderReturnsTheMarkupWhenReturnIsTrue(): void
    {
        self::assertSame('[header]', OutputSupport::header(true));
    }

    #[Test]
    public function testHeaderEchoesTheMarkupAndReturnsNullWhenReturnIsFalse(): void
    {
        ob_start();
        $result = OutputSupport::header();
        $echoed = ob_get_clean();

        self::assertNull($result);
        self::assertSame('[header]', $echoed);
    }

    #[Test]
    public function testFooterReturnsTheMarkupWhenReturnIsTrue(): void
    {
        self::assertSame('[footer]', OutputSupport::footer(true));
    }

    #[Test]
    public function testFooterEchoesTheMarkupAndReturnsNullWhenReturnIsFalse(): void
    {
        ob_start();
        $result = OutputSupport::footer();
        $echoed = ob_get_clean();

        self::assertNull($result);
        self::assertSame('[footer]', $echoed);
    }

    #[Test]
    public function testRenderDelegatesToTheOutputRenderer(): void
    {
        $renderable = new class implements renderable {};

        self::assertSame('rendered', OutputSupport::render($renderable));
    }

    #[Test]
    public function testRenderFromTemplateForwardsNameAndContext(): void
    {
        self::assertSame(
            'tpl:comp/name|ctx:{"a":1}',
            OutputSupport::renderFromTemplate('comp/name', ['a' => 1]),
        );
    }

    #[Test]
    public function testNotificationForwardsMessageAndType(): void
    {
        self::assertSame('note:Saved|error', OutputSupport::notification('Saved', 'error'));
    }

    #[Test]
    public function testNotificationUsesTheDefaultTypeWhenOmitted(): void
    {
        self::assertSame('note:Hi|notifyinfo', OutputSupport::notification('Hi'));
    }

    private function makeOutput(): object
    {
        return new class {
            public function box(string $contents, $classes = 'generalbox', $id = null, $attributes = []): string
            {
                return sprintf('box:%s|classes:%s|id:%s', $contents, $classes, (string) $id);
            }

            public function header(): string
            {
                return '[header]';
            }

            public function footer(): string
            {
                return '[footer]';
            }

            public function render($renderable): string
            {
                return 'rendered';
            }

            public function render_from_template(string $templatename, $context): string
            {
                return sprintf('tpl:%s|ctx:%s', $templatename, json_encode($context));
            }

            public function notification(string $message, $type = 'notifyinfo'): string
            {
                return sprintf('note:%s|%s', $message, $type);
            }
        };
    }
}
