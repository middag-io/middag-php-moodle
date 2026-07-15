<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\NoHost;

use Middag\Moodle\Runtime\Loader\MoodleHookfileLoader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(MoodleHookfileLoader::class)]
final class MoodleHookfileLoaderNoHostTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['CFG']);
    }

    public function testDiscoverIsEmptyWithoutMoodleRuntime(): void
    {
        unset($GLOBALS['CFG']);

        // No $CFG roots and get_plugins_with_function() undefined: the plugin
        // contribution guard returns [] instead of fataling.
        self::assertSame([], (new MoodleHookfileLoader('local_example'))->discover());
    }

    public function testCfgRootsStillContributeWithoutPluginFunction(): void
    {
        $dataroot = sys_get_temp_dir() . '/middag-nohost-' . uniqid('', false);
        mkdir($dataroot, 0o777, true);
        $hookfile = $dataroot . '/middag_hooks.php';
        file_put_contents($hookfile, "<?php\nreturn [];\n");

        $GLOBALS['CFG'] = (object) ['dataroot' => $dataroot];

        try {
            self::assertSame([$hookfile], (new MoodleHookfileLoader('local_example'))->discover());
        } finally {
            unlink($hookfile);
            rmdir($dataroot);
        }
    }
}
