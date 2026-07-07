<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Form;

use Middag\Moodle\Form\MformElementSpec;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Test MformElementSpec.
 *
 * Immutable value object describing a MoodleQuickForm::addElement() call.
 *
 * @internal
 */
#[CoversClass(MformElementSpec::class)]
final class MformElementSpecCoverageTest extends TestCase
{
    #[Test]
    public function exposesAllConstructorValues(): void
    {
        $spec = new MformElementSpec(
            element: 'select',
            name: 'status',
            label_html: 'Status',
            options: [1 => 'On', 0 => 'Off'],
            element_args: ['multiple' => true],
            param_type: 'raw',
            default: 1,
            rule: ['required'],
        );

        $this->assertSame('select', $spec->element);
        $this->assertSame('status', $spec->name);
        $this->assertSame('Status', $spec->label_html);
        $this->assertSame([1 => 'On', 0 => 'Off'], $spec->options);
        $this->assertSame(['multiple' => true], $spec->element_args);
        $this->assertSame('raw', $spec->param_type);
        $this->assertSame(1, $spec->default);
        $this->assertSame(['required'], $spec->rule);
    }

    #[Test]
    public function appliesDefaultsForOptionalArguments(): void
    {
        $spec = new MformElementSpec(element: 'text', name: 'title', label_html: 'Title');

        $this->assertSame([], $spec->options);
        $this->assertSame([], $spec->element_args);
        $this->assertNull($spec->param_type);
        $this->assertNull($spec->default);
        $this->assertNull($spec->rule);
    }
}
