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

use core\output\html_writer as CoreHtmlWriter;
use core\url as moodle_url;
use core_table\output\html_table;
use Middag\Moodle\Support\HtmlWriterSupport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * HtmlWriterSupport forwards to core\output\html_writer's static helpers (plus
 * the html_to_text() function). The central bootstrap html_writer stub only
 * exposes random_id/attributes/link today, so the remaining helpers are covered
 * by probes that assert delegation once the central stub gains those methods
 * (see the batch report's centralStubNeeds) and skip cleanly until then.
 *
 * @internal
 */
#[CoversClass(HtmlWriterSupport::class)]
final class HtmlWriterSupportCoverageTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['__middag_test_html_to_text']);
    }

    #[Test]
    public function testAttributesRendersTheAttributeMap(): void
    {
        self::assertSame(' id="x"', HtmlWriterSupport::attributes(['id' => 'x']));
    }

    #[Test]
    public function testRandomIdUsesTheProvidedBasePrefix(): void
    {
        $id = HtmlWriterSupport::randomId('field');

        self::assertStringStartsWith('field', $id);
        self::assertStringContainsString('auto', $id);
    }

    #[Test]
    public function testLinkRendersAnAnchor(): void
    {
        self::assertSame(
            '<a href="https://moodle.test">Go</a>',
            HtmlWriterSupport::link('https://moodle.test', 'Go'),
        );
    }

    #[Test]
    public function testHtmlToTextDelegatesToTheMoodleHelper(): void
    {
        self::assertSame('text:<b>hi</b>', HtmlWriterSupport::htmlToText('<b>hi</b>'));
    }

    #[Test]
    public function testHtmlToTextReturnsTheDrivenValue(): void
    {
        $GLOBALS['__middag_test_html_to_text'] = 'plain';

        self::assertSame('plain', HtmlWriterSupport::htmlToText('<p>rich</p>'));
    }

    #[Test]
    public function testTagDelegatesToHtmlWriter(): void
    {
        $this->requireHtmlWriterMethod('tag');

        self::assertIsString(HtmlWriterSupport::tag('span', 'body', ['class' => 'c']));
    }

    #[Test]
    public function testStartTagDelegatesToHtmlWriter(): void
    {
        $this->requireHtmlWriterMethod('start_tag');

        self::assertIsString(HtmlWriterSupport::startTag('div', ['class' => 'c']));
    }

    #[Test]
    public function testEndTagDelegatesToHtmlWriter(): void
    {
        $this->requireHtmlWriterMethod('end_tag');

        self::assertIsString(HtmlWriterSupport::endTag('div'));
    }

    #[Test]
    public function testEmptyTagDelegatesToHtmlWriter(): void
    {
        $this->requireHtmlWriterMethod('empty_tag');

        self::assertIsString(HtmlWriterSupport::emptyTag('br'));
    }

    #[Test]
    public function testNonemptyTagDelegatesToHtmlWriter(): void
    {
        $this->requireHtmlWriterMethod('nonempty_tag');

        self::assertIsString(HtmlWriterSupport::nonemptyTag('span', 'x'));
    }

    #[Test]
    public function testAttributeDelegatesToHtmlWriter(): void
    {
        $this->requireHtmlWriterMethod('attribute');

        self::assertIsString(HtmlWriterSupport::attribute('id', 'x'));
    }

    #[Test]
    public function testImgDelegatesToHtmlWriter(): void
    {
        $this->requireHtmlWriterMethod('img');

        self::assertIsString(HtmlWriterSupport::img('https://moodle.test/i.png', 'alt'));
    }

    #[Test]
    public function testCheckboxDelegatesToHtmlWriter(): void
    {
        $this->requireHtmlWriterMethod('checkbox');

        self::assertIsString(HtmlWriterSupport::checkbox('agree', '1', true, 'Agree'));
    }

    #[Test]
    public function testSelectYesNoDelegatesToHtmlWriter(): void
    {
        $this->requireHtmlWriterMethod('select_yes_no');

        self::assertIsString(HtmlWriterSupport::selectYesNo('flag', true));
    }

    #[Test]
    public function testSelectDelegatesToHtmlWriter(): void
    {
        $this->requireHtmlWriterMethod('select');

        self::assertIsString(HtmlWriterSupport::select(['a' => 'A'], 'choice', 'a'));
    }

    #[Test]
    public function testSelectTimeDelegatesToHtmlWriter(): void
    {
        $this->requireHtmlWriterMethod('select_time');

        self::assertIsString(HtmlWriterSupport::selectTime('days', 'day'));
    }

    #[Test]
    public function testAlistDelegatesToHtmlWriter(): void
    {
        $this->requireHtmlWriterMethod('alist');

        self::assertIsString(HtmlWriterSupport::alist(['one', 'two']));
    }

    #[Test]
    public function testInputHiddenParamsDelegatesToHtmlWriter(): void
    {
        $this->requireHtmlWriterMethod('input_hidden_params');

        self::assertIsString(HtmlWriterSupport::inputHiddenParams(new moodle_url('https://moodle.test')));
    }

    #[Test]
    public function testScriptDelegatesToHtmlWriter(): void
    {
        $this->requireHtmlWriterMethod('script');

        self::assertIsString(HtmlWriterSupport::script('console.log(1);'));
    }

    #[Test]
    public function testTableDelegatesToHtmlWriter(): void
    {
        $this->requireHtmlWriterMethod('table');

        self::assertIsString(HtmlWriterSupport::table(new html_table()));
    }

    #[Test]
    public function testLabelDelegatesToHtmlWriter(): void
    {
        $this->requireHtmlWriterMethod('label');

        self::assertIsString(HtmlWriterSupport::label('Name', 'name'));
    }

    #[Test]
    public function testDivDelegatesToHtmlWriter(): void
    {
        $this->requireHtmlWriterMethod('div');

        self::assertIsString(HtmlWriterSupport::div('body', 'c'));
    }

    #[Test]
    public function testStartDivDelegatesToHtmlWriter(): void
    {
        $this->requireHtmlWriterMethod('start_div');

        self::assertIsString(HtmlWriterSupport::startDiv('c'));
    }

    #[Test]
    public function testEndDivDelegatesToHtmlWriter(): void
    {
        $this->requireHtmlWriterMethod('end_div');

        self::assertIsString(HtmlWriterSupport::endDiv());
    }

    #[Test]
    public function testSpanDelegatesToHtmlWriter(): void
    {
        $this->requireHtmlWriterMethod('span');

        self::assertIsString(HtmlWriterSupport::span('body', 'c'));
    }

    #[Test]
    public function testStartSpanDelegatesToHtmlWriter(): void
    {
        $this->requireHtmlWriterMethod('start_span');

        self::assertIsString(HtmlWriterSupport::startSpan('c'));
    }

    #[Test]
    public function testEndSpanDelegatesToHtmlWriter(): void
    {
        $this->requireHtmlWriterMethod('end_span');

        self::assertIsString(HtmlWriterSupport::endSpan());
    }

    /**
     * Skip a delegation test until the central html_writer stub exposes the
     * wrapped static method; the assertion then runs unchanged (auto-activation).
     */
    private function requireHtmlWriterMethod(string $method): void
    {
        if (!method_exists(CoreHtmlWriter::class, $method)) {
            self::markTestSkipped(sprintf('central core\output\html_writer stub lacks %s()', $method));
        }
    }
}
