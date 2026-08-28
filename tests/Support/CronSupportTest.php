<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Support;

use Middag\Moodle\Support\CronSupport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(CronSupport::class)]
final class CronSupportTest extends TestCase
{
    public function testDelegatesToMoodleCron(): void
    {
        if (!class_exists('core\cron')) {
            self::markTestSkipped('Moodle cron is unavailable in the no-host test suite.');
        }

        CronSupport::setupUser((object) ['id' => 42]);
        CronSupport::prepareCoreRenderer();
        CronSupport::prepareCoreRenderer(true);
        CronSupport::resetUserCache();

        self::assertSame(42, $GLOBALS['__middag_test_cron_user']->id);
        self::assertSame([false, true], $GLOBALS['__middag_test_cron_renderer']);
        self::assertTrue($GLOBALS['__middag_test_cron_reset']);
    }
}
