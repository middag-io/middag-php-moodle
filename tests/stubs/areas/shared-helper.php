<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

/*
 * Moodle output stubs for the Middag\Moodle\Shared\Util\Helper coverage test.
 *
 * Helper::installOrUpgradeLog() builds a core\output\notification and renders it
 * through a core\output\core_renderer (either the global $OUTPUT or the one
 * $PAGE->get_renderer('core') returns). moodle-stubs declare both classes for
 * PHPStan only — they are not autoloadable at runtime — so behavioural
 * stand-ins live here. Guarded with !class_exists so the file is order-
 * independent, purely additive, and collision-free with parallel writers; the
 * render_from_template hook records its call and can be told to throw (via
 * $GLOBALS['__middag_test_helper_render_throw']) so the wrapper's catch branch
 * is reachable.
 */

namespace core\output;

use Exception;

if (!class_exists('core\output\notification', false)) {
    class notification
    {
        public const NOTIFY_SUCCESS = 'success';

        public const NOTIFY_WARNING = 'warning';

        public const NOTIFY_INFO = 'info';

        public const NOTIFY_ERROR = 'error';

        public function __construct(
            public string $message = '',
            public string $messagetype = self::NOTIFY_WARNING,
            public bool $closebutton = true
        ) {}

        public function get_template_name(): string
        {
            return 'core/notification_' . $this->messagetype;
        }

        /**
         * @param mixed $output
         *
         * @return array<string, mixed>
         */
        public function export_for_template($output): array
        {
            return [
                'message' => $this->message,
                'type' => $this->messagetype,
                'closebutton' => $this->closebutton,
            ];
        }
    }
}

if (!class_exists('core\output\core_renderer', false)) {
    class core_renderer extends renderer_base
    {
        /**
         * @param array<string, mixed>|object $context
         */
        public function render_from_template(string $templatename, $context): string
        {
            $GLOBALS['__middag_test_helper_rendered'][] = [
                'template' => $templatename,
                'context' => $context,
            ];

            if (!empty($GLOBALS['__middag_test_helper_render_throw'])) {
                throw new Exception('render_from_template failure');
            }

            return '<tmpl:' . $templatename . '>' . json_encode($context, JSON_THROW_ON_ERROR) . '</tmpl>';
        }
    }
}
