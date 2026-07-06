<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

use core\task\scheduled_task;

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

/*
 * Domain/Task coverage fixtures.
 *
 * ScheduledService::runNow() instantiates the task class named in the DTO
 * (`new ('\\' . ltrim($dto->classname, '\\'))`) and hands it to
 * TaskSupport::runScheduledFromCli(scheduled_task $task). The instance must
 * therefore be a real, instantiable core\task\scheduled_task subclass so the
 * (mocked) support method's parameter type is satisfied without a Moodle
 * runtime. The core\task\scheduled_task stand-in is defined in
 * tests/stubs/support/msg-file.php, loaded before this areas/ file, so the
 * subclass below can extend it. Guarded so the definition is additive.
 */
if (!class_exists('middag_test_runnow_scheduled_task', false)) {
    class middag_test_runnow_scheduled_task extends scheduled_task {}
}
