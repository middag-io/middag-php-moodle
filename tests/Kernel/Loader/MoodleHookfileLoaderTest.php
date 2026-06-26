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

use FilesystemIterator;
use Middag\Moodle\Kernel\Loader\MoodleHookfileLoader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use stdClass;

/**
 * @internal
 */
#[CoversClass(MoodleHookfileLoader::class)]
final class MoodleHookfileLoaderTest extends TestCase
{
    private string $tmpRoot;

    private string $dataroot;

    private string $dirroot;

    private string $themeName = 'middag';

    protected function setUp(): void
    {
        $this->tmpRoot = sys_get_temp_dir() . '/middag-hookfile-' . uniqid('', true);
        $this->dataroot = $this->tmpRoot . '/dataroot';
        $this->dirroot = $this->tmpRoot . '/dirroot';
        mkdir($this->dataroot, 0o777, true);
        mkdir($this->dirroot . '/local', 0o777, true);
        mkdir($this->dirroot . '/theme/' . $this->themeName, 0o777, true);

        $GLOBALS['CFG'] = new stdClass();
        $GLOBALS['CFG']->dataroot = $this->dataroot;
        $GLOBALS['CFG']->dirroot = $this->dirroot;
        $GLOBALS['CFG']->theme = $this->themeName;
        $GLOBALS['__middag_test_plugin_functions'] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['CFG'], $GLOBALS['__middag_test_plugin_functions']);
        $this->deleteDir($this->tmpRoot);
    }

    public function testDiscoverReturnsEmptyWhenNoFilesExist(): void
    {
        $loader = new MoodleHookfileLoader();
        self::assertSame([], $loader->discover());
    }

    public function testDiscoverFindsAllFourSourceKindsInOrder(): void
    {
        $dataroot_hook = $this->writeHook($this->dataroot . '/middag_hooks.php');
        $local_hook = $this->writeHook($this->dirroot . '/local/middag_hooks.php');
        $theme_hook = $this->writeHook($this->dirroot . '/theme/' . $this->themeName . '/middag_hooks.php');

        $plugin_hook = $this->writeHook($this->tmpRoot . '/plugin/middag_hooks.php');
        $GLOBALS['__middag_test_plugin_functions']['extend_local_example_hookfiles'] = [
            'local' => [
                'partner_plugin' => static fn (): array => [$plugin_hook],
            ],
        ];

        $loader = new MoodleHookfileLoader();
        $discovered = $loader->discover();

        self::assertSame(
            [$dataroot_hook, $local_hook, $theme_hook, $plugin_hook],
            $discovered,
            'Sources must be returned in dataroot → local → theme → plugin order.',
        );
    }

    public function testDiscoverSkipsMissingOrInvalidPluginPaths(): void
    {
        $real_hook = $this->writeHook($this->tmpRoot . '/plugin/middag_hooks.php');

        $GLOBALS['__middag_test_plugin_functions']['extend_local_example_hookfiles'] = [
            'mod' => [
                'broken' => static fn (): array => [
                    '/nonexistent/path.php',
                    $real_hook,
                    123,
                ],
            ],
        ];

        $loader = new MoodleHookfileLoader();
        self::assertSame([$real_hook], $loader->discover());
    }

    public function testDiscoverSwallowsThrowingPluginCallbacks(): void
    {
        $GLOBALS['__middag_test_plugin_functions']['extend_local_example_hookfiles'] = [
            'mod' => [
                'broken' => static function (): array {
                    throw new RuntimeException('plugin contributor exploded');
                },
            ],
        ];

        $loader = new MoodleHookfileLoader();
        self::assertSame([], $loader->discover(), 'Failing plugin callbacks must not abort discovery.');
    }

    public function testDiscoverDeduplicatesRepeatedPaths(): void
    {
        $shared_hook = $this->writeHook($this->dataroot . '/middag_hooks.php');

        $GLOBALS['__middag_test_plugin_functions']['extend_local_example_hookfiles'] = [
            'local' => [
                'echo' => static fn (): array => [$shared_hook, $shared_hook],
            ],
        ];

        $loader = new MoodleHookfileLoader();
        self::assertSame([$shared_hook], $loader->discover());
    }

    public function testCustomHookPrefixChangesCallbackFunctionName(): void
    {
        $contributed = $this->writeHook($this->tmpRoot . '/plugin/middag_hooks.php');

        $GLOBALS['__middag_test_plugin_functions']['extend_custom_brand_hookfiles'] = [
            'local' => [
                'custom' => static fn (): array => [$contributed],
            ],
        ];

        $loader = new MoodleHookfileLoader(hookPrefix: 'custom_brand');
        self::assertSame([$contributed], $loader->discover());
    }

    public function testDiscoverOmitsThemeSourceWhenThemeUnset(): void
    {
        $local_hook = $this->writeHook($this->dirroot . '/local/middag_hooks.php');
        $this->writeHook($this->dirroot . '/theme/' . $this->themeName . '/middag_hooks.php');
        unset($GLOBALS['CFG']->theme);

        $loader = new MoodleHookfileLoader();
        self::assertSame([$local_hook], $loader->discover());
    }

    private function writeHook(string $path): string
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0o777, true);
        }
        file_put_contents($path, "<?php\n// test hookfile\n");

        return $path;
    }

    private function deleteDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iter as $entry) {
            if ($entry->isDir()) {
                rmdir($entry->getPathname());
            } else {
                unlink($entry->getPathname());
            }
        }
        rmdir($dir);
    }
}
