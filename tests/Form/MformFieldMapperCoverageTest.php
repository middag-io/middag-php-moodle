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
use Middag\Ui\Form\FieldConstraints;
use Middag\Ui\Form\FieldDefinition;
use Middag\Ui\Shared\Enum\FieldType;
use Middag\Ui\Shared\ValueObject\Translatable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Test MformFieldMapper.
 *
 * Maps every UI FieldType onto a MformElementSpec (element + PARAM_* type). The
 * PARAM_* constants are defined in tests/bootstrap.php.
 *
 * @internal
 */
#[CoversClass(MformFieldMapper::class)]
final class MformFieldMapperCoverageTest extends TestCase
{
    private MformFieldMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new MformFieldMapper();
    }

    #[Test]
    #[DataProvider('fieldTypeProvider')]
    public function mapsFieldTypeToElementAndParamType(FieldType $type, string $element, ?string $paramType): void
    {
        $spec = $this->mapper->map($this->field($type));

        $this->assertSame($element, $spec->element);
        $this->assertSame($paramType, $spec->param_type);
    }

    /**
     * @return array<string, array{0: FieldType, 1: string, 2: null|string}>
     */
    public static function fieldTypeProvider(): array
    {
        return [
            'text' => [FieldType::Text, 'text', PARAM_TEXT],
            'textarea' => [FieldType::Textarea, 'textarea', PARAM_TEXT],
            'password' => [FieldType::Password, 'passwordunmask', PARAM_RAW],
            'email' => [FieldType::Email, 'text', PARAM_EMAIL],
            'url' => [FieldType::Url, 'text', PARAM_URL],
            'int' => [FieldType::Int, 'text', PARAM_INT],
            'float' => [FieldType::Float, 'text', PARAM_FLOAT],
            'select' => [FieldType::Select, 'select', PARAM_RAW],
            'multiselect' => [FieldType::Multiselect, 'autocomplete', PARAM_RAW],
            'radio' => [FieldType::Radio, 'radio', PARAM_RAW],
            'checkbox' => [FieldType::Checkbox, 'advcheckbox', PARAM_INT],
            'switch' => [FieldType::Switch, 'advcheckbox', PARAM_INT],
            'date' => [FieldType::Date, 'date_selector', null],
            'datetime' => [FieldType::Datetime, 'date_time_selector', null],
            'duration' => [FieldType::Duration, 'duration', null],
            'file' => [FieldType::File, 'filepicker', null],
            'entity_picker' => [FieldType::EntityPicker, 'autocomplete', PARAM_RAW],
            'hidden' => [FieldType::Hidden, 'hidden', PARAM_RAW],
            'static' => [FieldType::Static, 'static', null],
            'header' => [FieldType::Header, 'header', null],
            // default arm (no dedicated mform element):
            'richtext_default' => [FieldType::Richtext, 'text', PARAM_TEXT],
            'slider_default' => [FieldType::Slider, 'text', PARAM_TEXT],
            'tags_default' => [FieldType::Tags, 'text', PARAM_TEXT],
        ];
    }

    #[Test]
    public function requiredFieldGetsRequiredRule(): void
    {
        $spec = $this->mapper->map($this->field(FieldType::Text, required: true));

        $this->assertSame(['required'], $spec->rule);
    }

    #[Test]
    public function optionalFieldHasNoRule(): void
    {
        $spec = $this->mapper->map($this->field(FieldType::Text, required: false));

        $this->assertNull($spec->rule);
    }

    #[Test]
    public function checkboxNeverGetsARuleEvenWhenRequired(): void
    {
        // CHECKBOX/SWITCH intentionally omit the rule branch.
        $spec = $this->mapper->map($this->field(FieldType::Checkbox, required: true));

        $this->assertNull($spec->rule);
    }

    #[Test]
    public function selectCarriesItsOptions(): void
    {
        $spec = $this->mapper->map($this->field(FieldType::Select, options: ['a' => 'A', 'b' => 'B']));

        $this->assertSame(['a' => 'A', 'b' => 'B'], $spec->options);
    }

    #[Test]
    public function multiselectMarksMultipleElementArg(): void
    {
        $spec = $this->mapper->map($this->field(FieldType::Multiselect));

        $this->assertSame(['multiple' => true], $spec->element_args);
    }

    #[Test]
    public function entityPickerForwardsAttributesAsElementArgs(): void
    {
        $spec = $this->mapper->map($this->field(FieldType::EntityPicker, attributes: ['ajax' => 'core/search']));

        $this->assertSame(['ajax' => 'core/search'], $spec->element_args);
    }

    #[Test]
    public function labelUsesTranslatableKey(): void
    {
        $spec = $this->mapper->map($this->field(FieldType::Text, label: Translatable::of('field.title', 'local_example')));

        $this->assertSame('field.title', $spec->label_html);
    }

    #[Test]
    public function labelUsesRawStringWhenProvided(): void
    {
        $spec = $this->mapper->map($this->field(FieldType::Text, label: 'Explicit Label'));

        $this->assertSame('Explicit Label', $spec->label_html);
    }

    #[Test]
    public function labelFallsBackToHumanisedNameWhenEmpty(): void
    {
        $spec = $this->mapper->map($this->field(FieldType::Text, name: 'user_full_name', label: ''));

        $this->assertSame('User full name', $spec->label_html);
    }

    private function field(
        FieldType $type,
        string $name = 'fld',
        string|Translatable|null $label = 'My Label',
        mixed $default = null,
        bool $required = false,
        array $options = [],
        array $attributes = [],
    ): FieldDefinition {
        return new FieldDefinition(
            $name,
            $type,
            $label,
            null,
            $default,
            new FieldConstraints(required: $required),
            $attributes,
            [],
            $options,
        );
    }
}
