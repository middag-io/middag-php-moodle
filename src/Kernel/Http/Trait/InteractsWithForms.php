<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Kernel\Http\Trait;

use core\exception\coding_exception;
use core\exception\moodle_exception;

/**
 * Trait handling Moodle Form interactions within controllers.
 *
 * @internal
 */
trait InteractsWithForms
{
    protected object|string|null $form = null;

    protected mixed $formparams = null;

    /**
     * Set the form for the controller to handle.
     *
     * @param object|string $form       Class string or instance
     * @param mixed         $formparams Parameters for the form constructor
     *
     * @throws coding_exception
     * @throws moodle_exception
     */
    public function setForm(object|string $form, mixed $formparams = null): void
    {
        if (is_string($form)) {
            if (!class_exists($form)) {
                throw new coding_exception(sprintf("The form class '%s' does not exist.", $form));
            }
        } elseif (!is_object($form)) {
            throw new coding_exception('Invalid form provided. Must be an object or a class string.');
        }

        // Trigger any pre-processing if method exists in controller
        if (method_exists($this, 'pre_handle')) {
            $this->pre_handle();
        }

        $this->form = is_object($form) ? $form : new $form(null, $formparams);
        $this->formparams = $formparams;
    }

    /**
     * Check if the bound form was submitted and validated.
     */
    protected function handleFormSubmission(): bool
    {
        return is_object($this->form) && $this->form->is_submitted() && $this->form->is_validated();
    }

    /**
     * Return submitted form data when valid, otherwise false.
     */
    protected function processFormSubmission(): mixed
    {
        if ($this->handleFormSubmission()) {
            return $this->form->get_data();
        }

        return false;
    }

    /**
     * Determine if the form has been cancelled.
     */
    protected function processFormCancel(): bool
    {
        if (is_object($this->form)) {
            return $this->form->is_cancelled();
        }

        return false;
    }

    /**
     * Check if the form was submitted (regardless of validation).
     */
    protected function isFormSubmitted(): bool
    {
        return is_object($this->form) && $this->form->is_submitted();
    }

    /**
     * Internal helper to render the form and return HTML.
     */
    protected function renderFormHtml(): string
    {
        if (empty($this->form)) {
            return '';
        }

        ob_start();
        if (is_object($this->form)) {
            $this->form->display();
        }

        return ob_get_clean();
    }
}
