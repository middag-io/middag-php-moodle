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

use Middag\Moodle\Config\ComponentContext;
use Middag\Moodle\Support\Moodle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Moodle::class)]
final class MoodleCoverageTest extends TestCase
{
    private mixed $prevCfg;

    protected function setUp(): void
    {
        $this->prevCfg = $GLOBALS['CFG'] ?? null;

        // Calendar/Cohort/Group/UserField wrappers require_once host lib.php at
        // file scope; point dirroot at the stub moodle tree provided by msg-file.php.
        $GLOBALS['CFG'] = (object) [
            'dirroot' => $GLOBALS['__middag_test_moodleroot'],
            'wwwroot' => 'https://moodle.test',
        ];

        ComponentContext::configure('local_example', 'local_example_autoload');
    }

    protected function tearDown(): void
    {
        $GLOBALS['CFG'] = $this->prevCfg;
    }

    #[Test]
    #[DataProvider('factoryProvider')]
    public function testFactoryReturnsFreshSupportInstance(string $method, string $expectedClass): void
    {
        $first = Moodle::{$method}();
        $second = Moodle::{$method}();

        self::assertInstanceOf($expectedClass, $first);
        // The aggregator returns a fresh instance on each call, never a shared singleton.
        self::assertNotSame($first, $second);
    }

    /**
     * @return array<string, array{string, class-string}>
     */
    public static function factoryProvider(): array
    {
        $base = 'Middag\Moodle\Support\\';

        return [
            'auth' => ['auth', $base . 'AuthSupport'],
            'cache' => ['cache', $base . 'CacheSupport'],
            'calendar' => ['calendar', $base . 'CalendarSupport'],
            'capability' => ['capability', $base . 'CapabilitySupport'],
            'category' => ['category', $base . 'CategorySupport'],
            'check' => ['check', $base . 'CheckSupport'],
            'cohort' => ['cohort', $base . 'CohortSupport'],
            'competency' => ['competency', $base . 'CompetencySupport'],
            'completion' => ['completion', $base . 'CompletionSupport'],
            'config' => ['config', $base . 'ConfigSupport'],
            'context' => ['context', $base . 'ContextSupport'],
            'course' => ['course', $base . 'CourseSupport'],
            'customField' => ['customField', $base . 'CustomFieldSupport'],
            'db' => ['db', $base . 'DbSupport'],
            'diBridge' => ['diBridge', $base . 'DiBridgeSupport'],
            'enrol' => ['enrol', $base . 'EnrolSupport'],
            'event' => ['event', $base . 'EventSupport'],
            'file' => ['file', $base . 'FileSupport'],
            'grade' => ['grade', $base . 'GradeSupport'],
            'group' => ['group', $base . 'GroupSupport'],
            'htmlWriter' => ['htmlWriter', $base . 'HtmlWriterSupport'],
            'lang' => ['lang', $base . 'LangSupport'],
            'lock' => ['lock', $base . 'LockSupport'],
            'message' => ['message', $base . 'MessageSupport'],
            'notification' => ['notification', $base . 'NotificationSupport'],
            'output' => ['output', $base . 'OutputSupport'],
            'page' => ['page', $base . 'PageSupport'],
            'plugin' => ['plugin', $base . 'PluginSupport'],
            'preference' => ['preference', $base . 'PreferenceSupport'],
            'request' => ['request', $base . 'RequestSupport'],
            'role' => ['role', $base . 'RoleSupport'],
            'routerBridge' => ['routerBridge', $base . 'RouterBridgeSupport'],
            'session' => ['session', $base . 'SessionSupport'],
            'settings' => ['settings', $base . 'SettingsSupport'],
            'task' => ['task', $base . 'TaskSupport'],
            'theme' => ['theme', $base . 'ThemeSupport'],
            'time' => ['time', $base . 'TimeSupport'],
            'url' => ['url', $base . 'UrlSupport'],
            'user' => ['user', $base . 'UserSupport'],
            'userField' => ['userField', $base . 'UserFieldSupport'],
            'version' => ['version', $base . 'VersionSupport'],
        ];
    }
}
