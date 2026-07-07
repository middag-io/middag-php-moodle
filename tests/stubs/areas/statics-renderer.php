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
 * Moodle stand-ins for the Middag\Moodle\Statics\StaticsRenderer coverage test.
 *
 * StaticsRenderer::renderMessagePermission() maps message-permission ints back
 * onto Moodle's MESSAGE_* constant names. Those constants live in
 * lib/messagelib.php (real values DISALLOWED=0x4, PERMITTED=0x8, FORCED=0xc);
 * moodle-stubs declare them for PHPStan only, so they are absent at runtime.
 * Defined here (guarded, real values) so the renderer's constant-name branches
 * are reachable.
 *
 * renderServices()/renderHooks()/renderEvents() resolve callback/hook/observer
 * FQCNs via class_exists() + ReflectionClass to emit `Short::class` references
 * plus a `use` block. The concrete classes below give those branches something
 * to resolve. Guarded with !class_exists(..., false) so the file stays purely
 * additive, order-independent, and collision-free with parallel writers. Names
 * are distinct from the string-only fixtures used by the sibling Definition
 * tests (they never register `do_thing`/`hook_callbacks` as real classes).
 */

namespace {
    if (!defined('MESSAGE_DISALLOWED')) {
        define('MESSAGE_DISALLOWED', 0x4);
    }
    if (!defined('MESSAGE_PERMITTED')) {
        define('MESSAGE_PERMITTED', 0x8);
    }
    if (!defined('MESSAGE_FORCED')) {
        define('MESSAGE_FORCED', 0xC);
    }
}

namespace local_example\external {
    if (!class_exists('local_example\external\get_widget', false)) {
        class get_widget {}
    }
}

namespace local_example\hook {
    if (!class_exists('local_example\hook\widget_created', false)) {
        class widget_created {}
    }

    if (!class_exists('local_example\hook\WidgetObserver', false)) {
        class WidgetObserver
        {
            public static function on_created(): void {}
        }
    }
}

namespace local_example\event {
    if (!class_exists('local_example\event\observer', false)) {
        class observer {}
    }
}
