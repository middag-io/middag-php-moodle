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

use core\url as moodle_url;
use Middag\Moodle\Config\ComponentContext;
use Middag\Moodle\Support\UrlSupport;
use moodle_exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @internal
 */
#[CoversClass(UrlSupport::class)]
final class UrlSupportCoverageTest extends TestCase
{
    private mixed $prevOutput;

    private mixed $prevCfg;

    protected function setUp(): void
    {
        ComponentContext::configure('local_example', 'local_example_autoload');

        $this->prevOutput = $GLOBALS['OUTPUT'] ?? null;
        $this->prevCfg = $GLOBALS['CFG'] ?? null;

        $GLOBALS['CFG'] = (object) ['wwwroot' => 'https://moodle.test'];

        unset($GLOBALS['__middag_test_redirect']);
    }

    protected function tearDown(): void
    {
        $GLOBALS['OUTPUT'] = $this->prevOutput;
        $GLOBALS['CFG'] = $this->prevCfg;

        unset($GLOBALS['__middag_test_redirect']);

        ComponentContext::configure('local_example', 'local_example_autoload');
    }

    #[Test]
    public function testGetNormalizesDoubleSlashesAndForwardsParamsAndAnchor(): void
    {
        $url = UrlSupport::get('//admin///settings.php', ['a' => 1], 'section');

        self::assertSame('/admin/settings.php', $url->out());
        self::assertSame(1, $url->params['a']);
        self::assertSame('section', $url->anchor);
    }

    #[Test]
    public function testGetPreservesTheSchemeSeparatorOfAbsoluteUrls(): void
    {
        // Collapsing '://' into ':/' would strip the host, so parse_url yields
        // no host and get() silently falls back to the site home.
        $url = UrlSupport::get('https://cdn.example.com/logo.png');

        self::assertSame('https://cdn.example.com/logo.png', $url->out());
    }

    #[Test]
    public function testGetRethrowsWhenStrictnessIsMustExist(): void
    {
        $GLOBALS['__middag_test_throw_moodle_url'] = true;

        $this->expectException(moodle_exception::class);

        try {
            UrlSupport::get('/x', null, null, MUST_EXIST);
        } finally {
            unset($GLOBALS['__middag_test_throw_moodle_url']);
        }
    }

    #[Test]
    public function testGetFallsBackToHomeWhenStrictnessIsIgnoreMissing(): void
    {
        // One-shot: the requested URL throws, the home() fallback URL does not.
        $GLOBALS['__middag_test_throw_moodle_url'] = 1;

        try {
            $result = UrlSupport::get('/x', null, null, IGNORE_MISSING);
        } finally {
            unset($GLOBALS['__middag_test_throw_moodle_url']);
        }

        // The failed URL was suppressed and get() degraded to the site home URL.
        self::assertInstanceOf(moodle_url::class, $result);
    }

    #[Test]
    public function testCourseBuildsTheCourseViewUrl(): void
    {
        $url = UrlSupport::course(5);

        self::assertSame('/course/view.php', $url->out());
        self::assertSame(5, $url->params['id']);
    }

    #[Test]
    public function testModuleBuildsTheModuleViewUrl(): void
    {
        $url = UrlSupport::module(9);

        self::assertSame('/mod/view.php', $url->out());
        self::assertSame(9, $url->params['id']);
    }

    #[Test]
    public function testUserProfileWithoutCourseOmitsTheCourseParam(): void
    {
        $url = UrlSupport::userProfile(3);

        self::assertSame('/user/profile.php', $url->out());
        self::assertSame(3, $url->params['id']);
        self::assertArrayNotHasKey('course', $url->params);
    }

    #[Test]
    public function testUserProfileWithCourseAddsTheCourseParam(): void
    {
        $url = UrlSupport::userProfile(3, 10);

        self::assertSame(10, $url->params['course']);
    }

    #[Test]
    public function testPluginfileWithDefaultsHasNoExtraParams(): void
    {
        $url = UrlSupport::pluginfile(1, 'local_example', 'area', 2, '/', 'file.png');

        self::assertSame('/pluginfile.php/1/local_example/area/2/file.png', $url->out());
        self::assertSame([], $url->params);
    }

    #[Test]
    public function testPluginfileWithForceDownloadAndPreviewAddsBothParams(): void
    {
        $url = UrlSupport::pluginfile(1, 'local_example', 'area', 2, '/', 'file.png', true, 'thumb');

        self::assertSame(1, $url->params['forcedownload']);
        self::assertSame('thumb', $url->params['preview']);
    }

    #[Test]
    public function testImageUrlReturnsTheOutputUrl(): void
    {
        $GLOBALS['OUTPUT'] = new class {
            public function image_url($imagename, $component): moodle_url
            {
                return new moodle_url('/theme/image/' . $imagename);
            }
        };

        $url = UrlSupport::imageUrl('logo');

        self::assertSame('/theme/image/logo', $url->out());
    }

    #[Test]
    public function testImageUrlRethrowsWhenOutputThrows(): void
    {
        $GLOBALS['OUTPUT'] = new class {
            public function image_url($imagename, $component): moodle_url
            {
                throw new RuntimeException('image missing');
            }
        };

        $this->expectException(RuntimeException::class);

        UrlSupport::imageUrl('logo', 'local_example');
    }

    #[Test]
    public function testAdminSettingsBuildsTheSettingsUrl(): void
    {
        $url = UrlSupport::adminSettings('local_example');

        self::assertSame('/admin/settings.php', $url->out());
        self::assertSame('local_example', $url->params['section']);
    }

    #[Test]
    public function testHomeReturnsTheSiteRootUrl(): void
    {
        self::assertSame('/', UrlSupport::home()->out());
    }

    #[Test]
    public function testDashboardReturnsTheDashboardUrl(): void
    {
        self::assertSame('/my/', UrlSupport::dashboard()->out());
    }

    #[Test]
    public function testGradeReportBuildsTheReportUrl(): void
    {
        $url = UrlSupport::gradeReport(5, 'grader');

        self::assertSame('/grade/report/grader/index.php', $url->out());
        self::assertSame(5, $url->params['id']);
    }

    #[Test]
    public function testToAbsoluteReturnsAnAlreadyAbsoluteHttpsUrlUnchanged(): void
    {
        self::assertSame('https://other.test/a', UrlSupport::toAbsolute('https://other.test/a'));
    }

    #[Test]
    public function testToAbsoluteReturnsAnAlreadyAbsoluteHttpUrlUnchanged(): void
    {
        self::assertSame('http://other.test/a', UrlSupport::toAbsolute('http://other.test/a'));
    }

    #[Test]
    public function testToAbsolutePrependsWwwrootToARelativeUrl(): void
    {
        $GLOBALS['CFG'] = (object) ['wwwroot' => 'https://moodle.test/'];

        self::assertSame('https://moodle.test/course/view.php', UrlSupport::toAbsolute('/course/view.php'));
    }

    #[Test]
    public function testIsExternalReturnsFalseForARelativeUrl(): void
    {
        self::assertFalse(UrlSupport::isExternal('/course/view.php'));
    }

    #[Test]
    public function testIsExternalReturnsFalseForTheSameHost(): void
    {
        self::assertFalse(UrlSupport::isExternal('https://moodle.test/x'));
    }

    #[Test]
    public function testIsExternalReturnsTrueForADifferentHost(): void
    {
        self::assertTrue(UrlSupport::isExternal('https://evil.test/x'));
    }

    #[Test]
    public function testRedirectDelegatesToMoodleRedirect(): void
    {
        UrlSupport::redirect('/dashboard', 'Done', 2, 'notifysuccess');

        self::assertSame('/dashboard', $GLOBALS['__middag_test_redirect'][0]);
    }
}
