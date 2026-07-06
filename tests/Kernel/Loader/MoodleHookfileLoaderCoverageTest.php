<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Kernel\Loader;

use Middag\Moodle\Kernel\Loader\MoodleHookfileLoader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Branch-coverage companion to {@see MoodleHookfileLoaderTest}. It drives the
 * plugin-contribution error-handling arms of pluginContributions() that the
 * primary suite does not reach: a throwing get_plugins_with_function() lookup
 * and the per-bucket / per-callback / per-result type guards. Each guard is
 * proved to `continue` (not abort) by following the malformed entry with a
 * well-formed one that still contributes its hookfile.
 *
 * The site/local/theme sources are neutralised by leaving $CFG unset, so every
 * assertion isolates the plugin-contribution path.
 *
 * @internal
 */
#[CoversClass(MoodleHookfileLoader::class)]
final class MoodleHookfileLoaderCoverageTest extends TestCase
{
    /** Callback name derived from the configured component (ComponentContext = local_example). */
    private const CALLBACK = 'extend_local_example_hookfiles';

    private mixed $prevCfg;

    private mixed $prevPluginFunctions;

    private string $hookFile = '';

    protected function setUp(): void
    {
        $this->prevCfg = $GLOBALS['CFG'] ?? null;
        $this->prevPluginFunctions = $GLOBALS['__middag_test_plugin_functions'] ?? null;

        // No $CFG => the dataroot/dirroot/theme discovery sources are skipped,
        // isolating the plugin-contribution branches under test.
        unset($GLOBALS['CFG']);
        $GLOBALS['__middag_test_plugin_functions'] = [];

        $this->hookFile = (string) tempnam(sys_get_temp_dir(), 'middag-hook-');
        file_put_contents($this->hookFile, "<?php\n// coverage hookfile\n");
    }

    protected function tearDown(): void
    {
        if ($this->prevCfg === null) {
            unset($GLOBALS['CFG']);
        } else {
            $GLOBALS['CFG'] = $this->prevCfg;
        }

        if ($this->prevPluginFunctions === null) {
            unset($GLOBALS['__middag_test_plugin_functions']);
        } else {
            $GLOBALS['__middag_test_plugin_functions'] = $this->prevPluginFunctions;
        }

        if ($this->hookFile !== '' && is_file($this->hookFile)) {
            unlink($this->hookFile);
        }
    }

    #[Test]
    public function testDiscoverSwallowsWhenPluginLookupThrows(): void
    {
        // The bootstrap get_plugins_with_function() stub is typed `: array`;
        // handing it a non-array registry entry makes the return coercion raise
        // a TypeError. That is a Throwable — exactly the failure mode Moodle's
        // real lookup can raise (broken plugin lib.php) — so discovery must
        // degrade gracefully to an empty result rather than abort the boot.
        $GLOBALS['__middag_test_plugin_functions'][self::CALLBACK] = 'not-an-array';

        $loader = new MoodleHookfileLoader();

        self::assertSame([], $loader->discover());
    }

    #[Test]
    public function testDiscoverSkipsNonArrayPluginBucketButKeepsValidOnes(): void
    {
        $hook = $this->hookFile;
        $GLOBALS['__middag_test_plugin_functions'][self::CALLBACK] = [
            'malformed' => 'not-a-plugin-map',
            'local' => ['partner' => static fn (): array => [$hook]],
        ];

        $loader = new MoodleHookfileLoader();

        self::assertSame([$hook], $loader->discover());
    }

    #[Test]
    public function testDiscoverSkipsNonCallableEntryButKeepsValidOnes(): void
    {
        $hook = $this->hookFile;
        $GLOBALS['__middag_test_plugin_functions'][self::CALLBACK] = [
            'local' => [
                'notcallable' => 123,
                'partner' => static fn (): array => [$hook],
            ],
        ];

        $loader = new MoodleHookfileLoader();

        self::assertSame([$hook], $loader->discover());
    }

    #[Test]
    public function testDiscoverSkipsNonArrayCallbackResultButKeepsValidOnes(): void
    {
        $hook = $this->hookFile;
        $GLOBALS['__middag_test_plugin_functions'][self::CALLBACK] = [
            'local' => [
                // No return type on this closure: it returns a scalar so the
                // is_array($result) guard fires (a typed closure would instead
                // throw and hit the callback catch, a different branch).
                'scalarresult' => static fn (): string => 'not-an-array',
                'partner' => static fn (): array => [$hook],
            ],
        ];

        $loader = new MoodleHookfileLoader();

        self::assertSame([$hook], $loader->discover());
    }
}
