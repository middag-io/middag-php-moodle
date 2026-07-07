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
            'text' => [FieldType::TEXT, 'text', PARAM_TEXT],
            'textarea' => [FieldType::TEXTAREA, 'textarea', PARAM_TEXT],
            'password' => [FieldType::PASSWORD, 'passwordunmask', PARAM_RAW],
            'email' => [FieldType::EMAIL, 'text', PARAM_EMAIL],
            'url' => [FieldType::URL, 'text', PARAM_URL],
            'int' => [FieldType::INT, 'text', PARAM_INT],
            'float' => [FieldType::FLOAT, 'text', PARAM_FLOAT],
            'select' => [FieldType::SELECT, 'select', PARAM_RAW],
            'multiselect' => [FieldType::MULTISELECT, 'autocomplete', PARAM_RAW],
            'radio' => [FieldType::RADIO, 'radio', PARAM_RAW],
            'checkbox' => [FieldType::CHECKBOX, 'advcheckbox', PARAM_INT],
            'switch' => [FieldType::SWITCH, 'advcheckbox', PARAM_INT],
            'date' => [FieldType::DATE, 'date_selector', null],
            'datetime' => [FieldType::DATETIME, 'date_time_selector', null],
            'duration' => [FieldType::DURATION, 'duration', null],
            'file' => [FieldType::FILE, 'filepicker', null],
            'entity_picker' => [FieldType::ENTITY_PICKER, 'autocomplete', PARAM_RAW],
            'hidden' => [FieldType::HIDDEN, 'hidden', PARAM_RAW],
            'static' => [FieldType::STATIC, 'static', null],
            'header' => [FieldType::HEADER, 'header', null],
            // default arm (no dedicated mform element):
            'richtext_default' => [FieldType::RICHTEXT, 'text', PARAM_TEXT],
            'slider_default' => [FieldType::SLIDER, 'text', PARAM_TEXT],
            'tags_default' => [FieldType::TAGS, 'text', PARAM_TEXT],
        ];
    }

    #[Test]
    public function requiredFieldGetsRequiredRule(): void
    {
        $spec = $this->mapper->map($this->field(FieldType::TEXT, required: true));

        $this->assertSame(['required'], $spec->rule);
    }

    #[Test]
    public function optionalFieldHasNoRule(): void
    {
        $spec = $this->mapper->map($this->field(FieldType::TEXT, required: false));

        $this->assertNull($spec->rule);
    }

    #[Test]
    public function checkboxNeverGetsARuleEvenWhenRequired(): void
    {
        // CHECKBOX/SWITCH intentionally omit the rule branch.
        $spec = $this->mapper->map($this->field(FieldType::CHECKBOX, required: true));

        $this->assertNull($spec->rule);
    }

    #[Test]
    public function selectCarriesItsOptions(): void
    {
        $spec = $this->mapper->map($this->field(FieldType::SELECT, options: ['a' => 'A', 'b' => 'B']));

        $this->assertSame(['a' => 'A', 'b' => 'B'], $spec->options);
    }

    #[Test]
    public function multiselectMarksMultipleElementArg(): void
    {
        $spec = $this->mapper->map($this->field(FieldType::MULTISELECT));

        $this->assertSame(['multiple' => true], $spec->element_args);
    }

    #[Test]
    public function entityPickerForwardsAttributesAsElementArgs(): void
    {
        $spec = $this->mapper->map($this->field(FieldType::ENTITY_PICKER, attributes: ['ajax' => 'core/search']));

        $this->assertSame(['ajax' => 'core/search'], $spec->element_args);
    }

    #[Test]
    public function labelUsesTranslatableKey(): void
    {
        $spec = $this->mapper->map($this->field(FieldType::TEXT, label: Translatable::of('field.title', 'local_example')));

        $this->assertSame('field.title', $spec->label_html);
    }

    #[Test]
    public function labelUsesRawStringWhenProvided(): void
    {
        $spec = $this->mapper->map($this->field(FieldType::TEXT, label: 'Explicit Label'));

        $this->assertSame('Explicit Label', $spec->label_html);
    }

    #[Test]
    public function labelFallsBackToHumanisedNameWhenEmpty(): void
    {
        $spec = $this->mapper->map($this->field(FieldType::TEXT, name: 'user_full_name', label: ''));

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
