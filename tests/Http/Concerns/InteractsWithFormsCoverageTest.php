<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Http\Concerns;

use core\exception\coding_exception;
use Middag\Moodle\Http\Concerns\InteractsWithForms;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Exercises the InteractsWithForms trait end to end via concrete host fixtures
 * that `use` it. The bound "form" is a fake mform-shaped double exposing the
 * moodleform methods the trait calls (is_submitted/is_validated/get_data/
 * is_cancelled/display). No Moodle runtime is required: coding_exception comes
 * from tests/bootstrap.php and output is captured through the trait's own
 * output buffer, so nothing leaks into the test runner.
 *
 * @internal
 */
#[CoversClass(InteractsWithForms::class)]
final class InteractsWithFormsCoverageTest extends TestCase
{
    #[Test]
    public function testSetFormWithInstanceStoresTheFormAndParams(): void
    {
        $host = new InteractsWithFormsHost();
        $form = new InteractsWithFormsFakeForm();

        $host->callSetForm($form, ['scope' => 'unit']);

        self::assertSame($form, $host->exposeForm());
        self::assertSame(['scope' => 'unit'], $host->exposeFormParams());
    }

    #[Test]
    public function testSetFormWithClassStringInstantiatesTheFormWithNullActionAndParams(): void
    {
        $host = new InteractsWithFormsHost();

        $host->callSetForm(InteractsWithFormsFakeForm::class, 'PARAMS');

        $form = $host->exposeForm();
        self::assertInstanceOf(InteractsWithFormsFakeForm::class, $form);
        // Trait constructs `new $form(null, $formparams)`.
        self::assertSame([null, 'PARAMS'], $form->ctorArgs);
        self::assertSame('PARAMS', $host->exposeFormParams());
    }

    #[Test]
    public function testSetFormWithMissingClassStringThrowsCodingException(): void
    {
        $host = new InteractsWithFormsHost();

        $this->expectException(coding_exception::class);
        $this->expectExceptionMessage("The form class 'Middag\\Moodle\\Tests\\Http\\Concerns\\NoSuchForm' does not exist.");

        $host->callSetForm('Middag\Moodle\Tests\Http\Concerns\NoSuchForm');
    }

    #[Test]
    public function testSetFormInvokesPreHandleWhenTheHostDefinesIt(): void
    {
        $host = new InteractsWithFormsHostWithPreHandle();
        $form = new InteractsWithFormsFakeForm();

        $host->callSetForm($form);

        self::assertTrue($host->preHandleCalled);
        self::assertSame($form, $host->exposeForm());
    }

    #[Test]
    public function testHandleFormSubmissionIsTrueWhenSubmittedAndValidated(): void
    {
        $host = new InteractsWithFormsHost();
        $form = new InteractsWithFormsFakeForm();
        $form->submitted = true;
        $form->validated = true;

        $host->callSetForm($form);

        self::assertTrue($host->callHandleFormSubmission());
    }

    #[Test]
    public function testHandleFormSubmissionIsFalseWhenSubmittedButNotValidated(): void
    {
        $host = new InteractsWithFormsHost();
        $form = new InteractsWithFormsFakeForm();
        $form->submitted = true;
        $form->validated = false;

        $host->callSetForm($form);

        self::assertFalse($host->callHandleFormSubmission());
    }

    #[Test]
    public function testHandleFormSubmissionIsFalseWhenNotSubmitted(): void
    {
        $host = new InteractsWithFormsHost();
        $form = new InteractsWithFormsFakeForm();
        $form->submitted = false;

        $host->callSetForm($form);

        self::assertFalse($host->callHandleFormSubmission());
    }

    #[Test]
    public function testHandleFormSubmissionIsFalseWhenNoFormBound(): void
    {
        $host = new InteractsWithFormsHost();

        self::assertFalse($host->callHandleFormSubmission());
    }

    #[Test]
    public function testProcessFormSubmissionReturnsDataWhenValid(): void
    {
        $data = (object) ['name' => 'ok'];
        $host = new InteractsWithFormsHost();
        $form = new InteractsWithFormsFakeForm();
        $form->submitted = true;
        $form->validated = true;
        $form->data = $data;

        $host->callSetForm($form);

        self::assertSame($data, $host->callProcessFormSubmission());
    }

    #[Test]
    public function testProcessFormSubmissionReturnsFalseWhenNotValid(): void
    {
        $host = new InteractsWithFormsHost();
        $form = new InteractsWithFormsFakeForm();
        $form->submitted = false;

        $host->callSetForm($form);

        self::assertFalse($host->callProcessFormSubmission());
    }

    #[Test]
    public function testProcessFormCancelReturnsTheFormCancelState(): void
    {
        $host = new InteractsWithFormsHost();
        $form = new InteractsWithFormsFakeForm();
        $form->cancelled = true;

        $host->callSetForm($form);

        self::assertTrue($host->callProcessFormCancel());
    }

    #[Test]
    public function testProcessFormCancelReturnsFalseWhenNoFormBound(): void
    {
        $host = new InteractsWithFormsHost();

        self::assertFalse($host->callProcessFormCancel());
    }

    #[Test]
    public function testIsFormSubmittedReflectsTheFormState(): void
    {
        $host = new InteractsWithFormsHost();
        $form = new InteractsWithFormsFakeForm();
        $form->submitted = true;

        $host->callSetForm($form);

        self::assertTrue($host->callIsFormSubmitted());
    }

    #[Test]
    public function testIsFormSubmittedIsFalseWhenNoFormBound(): void
    {
        $host = new InteractsWithFormsHost();

        self::assertFalse($host->callIsFormSubmitted());
    }

    #[Test]
    public function testRenderFormHtmlReturnsEmptyStringWhenNoFormBound(): void
    {
        $host = new InteractsWithFormsHost();

        self::assertSame('', $host->callRenderFormHtml());
    }

    #[Test]
    public function testRenderFormHtmlCapturesTheFormOutput(): void
    {
        $host = new InteractsWithFormsHost();
        $form = new InteractsWithFormsFakeForm();
        $host->callSetForm($form);

        $level = ob_get_level();
        $html = $host->callRenderFormHtml();

        self::assertSame('<form>rendered</form>', $html);
        self::assertTrue($form->displayed);
        // The trait must balance its own ob_start()/ob_get_clean().
        self::assertSame($level, ob_get_level());
    }

    #[Test]
    public function testRenderFormHtmlReturnsEmptyStringWhenFormIsNonObjectString(): void
    {
        // The property is typed object|string|null; a non-empty string is a
        // declared-valid state that passes empty() but not is_object(), so the
        // inner is_object() guard falls straight through to an empty buffer.
        $host = new InteractsWithFormsHost();
        $host->forceForm('deferred-form-class');

        self::assertSame('', $host->callRenderFormHtml());
    }

    #[Test]
    public function testSetFormWithStdClassInstanceIsAcceptedAsAForm(): void
    {
        // Any object satisfies the object branch; setForm stores it verbatim.
        $host = new InteractsWithFormsHost();
        $form = new stdClass();

        $host->callSetForm($form);

        self::assertSame($form, $host->exposeForm());
    }
}

/**
 * Concrete host that composes the trait and exposes its protected surface.
 *
 * @internal
 */
class InteractsWithFormsHost
{
    use InteractsWithForms;

    public function callSetForm(object|string $form, mixed $formparams = null): void
    {
        $this->setForm($form, $formparams);
    }

    public function callHandleFormSubmission(): bool
    {
        return $this->handleFormSubmission();
    }

    public function callProcessFormSubmission(): mixed
    {
        return $this->processFormSubmission();
    }

    public function callProcessFormCancel(): bool
    {
        return $this->processFormCancel();
    }

    public function callIsFormSubmitted(): bool
    {
        return $this->isFormSubmitted();
    }

    public function callRenderFormHtml(): string
    {
        return $this->renderFormHtml();
    }

    public function exposeForm(): object|string|null
    {
        return $this->form;
    }

    public function exposeFormParams(): mixed
    {
        return $this->formparams;
    }

    public function forceForm(object|string|null $form): void
    {
        $this->form = $form;
    }
}

/**
 * Host variant that defines pre_handle(), so setForm()'s method_exists() gate
 * fires the pre-processing branch.
 *
 * @internal
 */
class InteractsWithFormsHostWithPreHandle extends InteractsWithFormsHost
{
    public bool $preHandleCalled = false;

    public function pre_handle(): void
    {
        $this->preHandleCalled = true;
    }
}

/**
 * Minimal mform-shaped double exposing the moodleform methods the trait calls.
 *
 * @internal
 */
class InteractsWithFormsFakeForm
{
    public bool $submitted = false;

    public bool $validated = false;

    public bool $cancelled = false;

    public mixed $data = null;

    public bool $displayed = false;

    /** @var array<int, mixed> */
    public array $ctorArgs = [];

    public function __construct(mixed $action = null, mixed $customdata = null)
    {
        $this->ctorArgs = [$action, $customdata];
    }

    public function is_submitted(): bool
    {
        return $this->submitted;
    }

    public function is_validated(): bool
    {
        return $this->validated;
    }

    public function is_cancelled(): bool
    {
        return $this->cancelled;
    }

    public function get_data(): mixed
    {
        return $this->data;
    }

    public function display(): void
    {
        $this->displayed = true;
        echo '<form>rendered</form>';
    }
}
