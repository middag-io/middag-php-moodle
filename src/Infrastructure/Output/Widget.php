<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Infrastructure\Output;

use core\output\html_writer;
use core\output\renderable;
use core\output\renderer_base;
use core\output\templatable;
use Middag\Moodle\Kernel\Config\ComponentContext;

/**
 * Generic MIDDAG widget renderable.
 *
 * Mounts a front-end (Vue) component into a Moodle page via the
 * {@code {component}/launcher} AMD module. Product-agnostic port of the former
 * plugin {@code output\widget}: the owning frankenstyle component defaults to
 * the composition-root {@see ComponentContext} when not supplied, so the adapter
 * carries no hard-coded plugin reference.
 *
 * @internal
 */
class Widget implements renderable, templatable
{
    /** @var string Unique DOM id for this widget mount point. */
    public string $module_id;

    /** @var string Frankenstyle owning component for the launcher AMD module. */
    public string $component;

    /**
     * @param string               $vuecomponent front-end component name to mount
     * @param array<string, mixed> $params       props passed to the component
     * @param null|string          $component    frankenstyle owning component;
     *                                           defaults to {@see ComponentContext::name()}
     */
    public function __construct(
        public string $vuecomponent,
        public array $params = [],
        ?string $component = null,
    ) {
        $this->component = $component ?? ComponentContext::name();
        $this->module_id = html_writer::random_id('middag-module-');
    }

    /**
     * @param null|renderer_base $output
     *
     * @return array<string, mixed>
     */
    public function export_for_template(?renderer_base $output): array
    {
        global $PAGE;

        $PAGE->requires->js_call_amd(
            $this->component . '/launcher',
            'init',
            [$this->vuecomponent, $this->module_id, $this->params],
        );

        return [
            'modules' => [
                [
                    'module_id' => $this->module_id,
                ],
            ],
        ];
    }
}
