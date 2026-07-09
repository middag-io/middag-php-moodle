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

use Middag\Moodle\Form\MformFieldMapper;
use Middag\Moodle\Form\MformRenderer;
use Middag\Ui\Block\Contract\LayoutElementInterface;
use Middag\Ui\Form\Contract\FieldInterface;
use Middag\Ui\Form\Contract\FormInterface;
use Middag\Ui\Form\Contract\FormRendererInterface;
use Middag\Ui\Form\FieldConstraints;
use Middag\Ui\Form\FieldDefinition;
use Middag\Ui\Form\FormState;
use Middag\Ui\Shared\Enum\FieldType;
use Middag\Ui\Shared\Enum\RenderTarget;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Test MformRenderer.
 *
 * Iterates the form schema (recursing into layout children), maps each field to
 * an mform spec, builds an anonymous moodleform and captures its rendered HTML.
 * moodleform/MoodleQuickForm come from tests/stubs/formslib.php, reached by
 * pointing $CFG->libdir at tests/stubs before the class autoloads.
 *
 * @internal
 */
#[CoversClass(MformRenderer::class)]
final class MformRendererCoverageTest extends TestCase
{
    protected function setUp(): void
    {
        // MformRenderer require_once's $CFG->libdir . '/formslib.php' at file
        // scope; point it at the stub before the class is referenced below.
        $GLOBALS['CFG'] ??= new stdClass();
        $GLOBALS['CFG']->libdir = \dirname(__DIR__) . '/stubs';
    }

    #[Test]
    public function targetIsHtml(): void
    {
        $this->assertSame(RenderTarget::Html, MformRenderer::target());
    }

    #[Test]
    public function rendersFormWalkingLayoutChildrenAndCapturesHtml(): void
    {
        $renderer = new MformRenderer(new MformFieldMapper());

        // A SELECT nested inside a layout element, plus a top-level MULTISELECT —
        // together they exercise options, element_args, setType, setDefault,
        // addRule in the anonymous moodleform definition().
        $nestedField = $this->fieldNode(
            $this->fieldDefinition(FieldType::Select, 'category', required: true, options: ['a' => 'A'])
        );
        $topField = $this->fieldNode(
            $this->fieldDefinition(FieldType::Multiselect, 'tags', options: ['x' => 'X'])
        );

        $layout = $this->createMock(LayoutElementInterface::class);
        $layout->method('children')->willReturn([$nestedField]);

        // Values populate set_data(); errors exercise both the array (imploded)
        // and string branches of setElementError().
        $state = new FormState(
            ['category' => 'a'],
            ['category' => ['too short', 'invalid'], 'tags' => 'required'],
        );

        $form = $this->createMock(FormInterface::class);
        $form->method('schema')->willReturn([$layout, $topField]);
        $form->method('state')->willReturn($state);

        $output = $renderer->render($form);

        $this->assertSame(RenderTarget::Html, $output->target);
        $this->assertStringContainsString('rendered', $output->body);
    }

    #[Test]
    public function rendersEmptyFormWithoutValuesOrErrors(): void
    {
        $renderer = new MformRenderer(new MformFieldMapper());

        $form = $this->createMock(FormInterface::class);
        $form->method('schema')->willReturn([]);
        $form->method('state')->willReturn(new FormState());

        $output = $renderer->render($form);

        $this->assertStringContainsString('rendered', $output->body);
    }

    #[Test]
    public function implementsFormRendererInterface(): void
    {
        $this->assertInstanceOf(FormRendererInterface::class, new MformRenderer(new MformFieldMapper()));
    }

    private function fieldDefinition(FieldType $type, string $name, bool $required = false, array $options = []): FieldDefinition
    {
        return new FieldDefinition(
            $name,
            $type,
            'Label ' . $name,
            null,
            'default_' . $name,
            new FieldConstraints(required: $required),
            [],
            [],
            $options,
        );
    }

    private function fieldNode(FieldDefinition $def): FieldInterface
    {
        $field = $this->createMock(FieldInterface::class);
        $field->method('toDefinition')->willReturn($def);

        return $field;
    }
}
