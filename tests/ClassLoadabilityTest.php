<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests;

use FilesystemIterator;
use Middag\Moodle\Form\MformRenderer;
use Middag\Moodle\Hook\AbstractExtendExtensions;
use Middag\Moodle\Output\MoodleRenderer;
use Middag\Moodle\Output\Widget;
use Middag\Moodle\Privacy\PrivacyProvider;
use Middag\Moodle\Support\CalendarSupport;
use Middag\Moodle\Support\CohortSupport;
use Middag\Moodle\Support\GroupSupport;
use Middag\Moodle\Support\UserFieldSupport;
use Middag\Moodle\Table\UsersFilterset;
use Middag\Moodle\Table\UsersTable;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Smoke test: verifies every adapter class/interface/enum/trait in src/ can be
 * loaded without a Moodle runtime. Classes that reference Moodle functions in
 * method bodies (not at file/constructor scope) must still load.
 *
 * The provider scans src/ so new classes are guarded automatically; the only
 * exceptions are the documented host-only symbols below.
 *
 * @internal
 */
#[CoversNothing]
final class ClassLoadabilityTest extends TestCase
{
    /**
     * Symbols that CANNOT load without a booted Moodle host, keyed by FQCN with
     * the reason. They require Moodle files at file scope ($CFG->libdir/...)
     * or extend/implement core classes that only exist inside Moodle.
     *
     * @var array<class-string|string, string>
     */
    private const REQUIRES_MOODLE_HOST = [
        MformRenderer::class => 'require_once $CFG->libdir/formslib.php at file scope',
        AbstractExtendExtensions::class => 'implements core\hook\described_hook',
        MoodleRenderer::class => 'extends core\output\plugin_renderer_base',
        Widget::class => 'implements core\output\renderable',
        PrivacyProvider::class => 'implements core_privacy\local\metadata\provider',
        CalendarSupport::class => 'require_once $CFG->dirroot/calendar/lib.php at file scope',
        CohortSupport::class => 'require_once $CFG->dirroot/cohort/lib.php at file scope',
        GroupSupport::class => 'require_once $CFG->dirroot/group/lib.php at file scope',
        UserFieldSupport::class => 'require_once $CFG->dirroot/user/profile/lib.php at file scope',
        UsersFilterset::class => 'extends core_table\local\filter\filterset',
        UsersTable::class => 'extends core_table\sql_table',
    ];

    #[Test]
    #[DataProvider('classProvider')]
    public function classIsLoadable(string $className): void
    {
        $this->assertTrue(
            class_exists($className) || interface_exists($className) || trait_exists($className),
            sprintf('Class %s should be loadable via autoloader', $className)
        );
    }

    /**
     * Every PSR-4 symbol under src/, minus the documented host-only exclusions.
     *
     * @return array<string, array{string}>
     */
    public static function classProvider(): array
    {
        $src = dirname(__DIR__) . '/src';
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS),
        );

        $classes = [];

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $relative = substr($file->getPathname(), \strlen($src) + 1, -\strlen('.php'));
            $fqcn = 'Middag\Moodle\\' . str_replace('/', '\\', $relative);

            if (isset(self::REQUIRES_MOODLE_HOST[$fqcn])) {
                continue;
            }

            $classes[$fqcn] = [$fqcn];
        }

        ksort($classes);

        return $classes;
    }

    #[Test]
    public function hostOnlyExclusionsStillExistInSrc(): void
    {
        $src = dirname(__DIR__) . '/src';

        foreach (array_keys(self::REQUIRES_MOODLE_HOST) as $fqcn) {
            $relative = str_replace('\\', '/', substr((string) $fqcn, \strlen('Middag\Moodle\\')));

            $this->assertFileExists(
                $src . '/' . $relative . '.php',
                sprintf('Stale host-only exclusion: %s no longer maps to a src/ file — remove it from the list.', $fqcn)
            );
        }
    }
}
