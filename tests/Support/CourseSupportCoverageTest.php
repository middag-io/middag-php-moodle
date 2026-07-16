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

use core\context\course as context_course;
use core\exception\moodle_exception;
use core\url as moodle_url;
use dml_exception;
use Middag\Moodle\Domain\Course\Category;
use Middag\Moodle\Domain\Course\Course;
use Middag\Moodle\Support\CourseSupport;
use moodle_database;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * CourseSupport wraps $DB reads plus get_course / get_courses / course_get_format /
 * is_enrolled and the course context/url helpers (stubbed in
 * tests/stubs/support/course.php). $DB is a mocked moodle_database whose per-table
 * returns are driven via $GLOBALS['__middag_test_records']. The count_records_sql /
 * get_records_select / core\url::set_anchor paths depend on central-stub symbols
 * tracked in centralStubNeeds, so those tests auto-activate once the symbols land.
 *
 * @internal
 */
#[CoversClass(CourseSupport::class)]
final class CourseSupportCoverageTest extends TestCase
{
    /** @var list<string> */
    private const GLOBAL_KEYS = [
        '__middag_test_records',
        '__middag_test_records_sql',
        '__middag_test_throw_get_record',
        '__middag_test_throw_get_records_sql',
        '__middag_test_course',
        '__middag_test_courses',
        '__middag_test_is_enrolled',
        '__middag_test_throw_is_enrolled',
        '__middag_test_course_format',
        '__middag_test_categories',
        '__middag_test_get_course_throw',
        '__middag_test_throw_context_instance',
        '__middag_test_context_course_throw_ids',
    ];

    private mixed $prevDb;

    private mixed $prevCfg;

    protected function setUp(): void
    {
        $this->prevDb = $GLOBALS['DB'] ?? null;
        $this->prevCfg = $GLOBALS['CFG'] ?? null;

        $this->clearGlobals();

        $dir = sys_get_temp_dir() . '/middag_course_support_test';
        if (!is_dir($dir . '/course/format')) {
            mkdir($dir . '/course/format', 0o777, true);
        }
        file_put_contents($dir . '/course/format/lib.php', "<?php\n");
        $GLOBALS['CFG'] = (object) ['dirroot' => $dir];

        $this->installDb();
    }

    protected function tearDown(): void
    {
        $this->clearGlobals();
        $GLOBALS['DB'] = $this->prevDb;
        $GLOBALS['CFG'] = $this->prevCfg;
    }

    #[Test]
    public function testGetCourseReturnsNullForZeroOrNullId(): void
    {
        self::assertNull(CourseSupport::getCourse(0));
        self::assertNull(CourseSupport::getCourse(null));
    }

    #[Test]
    public function testGetCourseContextReturnsNullWhenContextInstantiationThrows(): void
    {
        $GLOBALS['__middag_test_throw_context_instance'] = true;

        self::assertNull(CourseSupport::getCourseContext(10));
    }

    #[Test]
    public function testGetCourseReadsViaDbForTheDefaultStrictness(): void
    {
        $GLOBALS['__middag_test_records'] = ['course' => (object) ['id' => 10, 'fullname' => 'Algebra']];

        $course = CourseSupport::getCourse(10);

        self::assertInstanceOf(Course::class, $course);
        self::assertSame('Algebra', $course->fullname);
    }

    #[Test]
    public function testGetCourseUsesGetCourseWhenStrictnessIsNotIgnoreMissing(): void
    {
        $GLOBALS['__middag_test_course'] = (object) ['id' => 10, 'fullname' => 'Geometry'];

        $course = CourseSupport::getCourse(10, MUST_EXIST);

        self::assertInstanceOf(Course::class, $course);
        self::assertSame('Geometry', $course->fullname);
    }

    #[Test]
    public function testGetCourseReturnsNullWhenTheRecordIsMissing(): void
    {
        $GLOBALS['__middag_test_records'] = ['course' => false];

        self::assertNull(CourseSupport::getCourse(10));
    }

    #[Test]
    public function testGetCourseReturnsNullWhenTheReadThrows(): void
    {
        $GLOBALS['__middag_test_throw_get_record'] = 'course';

        self::assertNull(CourseSupport::getCourse(10));
    }

    #[Test]
    public function testGetCoursesOptionsBuildsLabelledOptions(): void
    {
        $GLOBALS['__middag_test_records_sql'] = [
            (object) ['id' => 5, 'fullname' => 'Algebra', 'categoryname' => 'Math'],
        ];

        $options = CourseSupport::getCoursesOptions();

        self::assertArrayHasKey(5, $options);
        self::assertStringStartsWith('ID: 5 - Algebra - ', $options[5]);
        self::assertStringContainsString('Math', $options[5]);
    }

    #[Test]
    public function testGetCoursesOptionsReturnsEmptyWhenTheReadThrows(): void
    {
        $GLOBALS['__middag_test_throw_get_records_sql'] = true;

        self::assertSame([], CourseSupport::getCoursesOptions());
    }

    #[Test]
    public function testGetCourseWithContextidOptionsSkipsTheSiteCourse(): void
    {
        $GLOBALS['__middag_test_courses'] = [
            1 => (object) ['id' => 1, 'fullname' => 'Site'],
            2 => (object) ['id' => 2, 'fullname' => 'Course Two'],
            3 => (object) ['id' => 3, 'fullname' => 'Course Three'],
        ];

        $options = CourseSupport::getCourseWithContextidOptions();

        self::assertArrayNotHasKey(1, $options);
        self::assertSame('ID 2 - Course Two', $options[2]);
        self::assertSame('ID 3 - Course Three', $options[3]);
    }

    #[Test]
    public function testGetCourseWithContextidOptionsSkipsACourseWhoseContextThrows(): void
    {
        // A course mid-deletion can make context_course::instance() throw; that
        // course must be skipped, not crash the whole options list.
        $GLOBALS['__middag_test_courses'] = [
            2 => (object) ['id' => 2, 'fullname' => 'Course Two'],
            3 => (object) ['id' => 3, 'fullname' => 'Broken'],
            4 => (object) ['id' => 4, 'fullname' => 'Course Four'],
        ];
        $GLOBALS['__middag_test_context_course_throw_ids'] = [3];

        $options = CourseSupport::getCourseWithContextidOptions();

        self::assertSame('ID 2 - Course Two', $options[2]);
        self::assertSame('ID 4 - Course Four', $options[4]);
        self::assertArrayNotHasKey(3, $options);
    }

    #[Test]
    public function testGetCmsBySectionReturnsVisibleModules(): void
    {
        $this->installFormat(
            5,
            (object) ['sectionnum' => 1, 'uservisible' => true],
            [1 => [100, 101]],
            [100 => $this->cm(true), 101 => $this->cm(false)],
        );

        $cms = CourseSupport::getCmsBySection(10, 1);

        self::assertCount(1, $cms);
    }

    #[Test]
    public function testGetCmsBySectionReturnsEmptyWhenSectionInfoIsNull(): void
    {
        $this->installFormat(5, null, [], []);

        self::assertSame([], CourseSupport::getCmsBySection(10, 1));
    }

    #[Test]
    public function testGetCmsBySectionSkipsSectionsBeyondTheLastNumber(): void
    {
        $this->installFormat(
            0,
            (object) ['sectionnum' => 1, 'uservisible' => true],
            [1 => [100]],
            [100 => $this->cm(true)],
        );

        self::assertSame([], CourseSupport::getCmsBySection(10, 1));
    }

    #[Test]
    public function testGetCmsBySectionSkipsInvisibleSections(): void
    {
        $this->installFormat(
            5,
            (object) ['sectionnum' => 1, 'uservisible' => true],
            [1 => [100]],
            [100 => $this->cm(true)],
            false,
        );

        self::assertSame([], CourseSupport::getCmsBySection(10, 1));
    }

    #[Test]
    public function testGetCmsBySectionSkipsModulesNotUserVisible(): void
    {
        $this->installFormat(
            5,
            (object) ['sectionnum' => 1, 'uservisible' => false],
            [1 => [100]],
            [100 => $this->cm(true)],
        );

        self::assertSame([], CourseSupport::getCmsBySection(10, 1));
    }

    #[Test]
    public function testGetCmsBySectionReturnsEmptyWhenSectionHasNoModules(): void
    {
        $this->installFormat(
            5,
            (object) ['sectionnum' => 9, 'uservisible' => true],
            [],
            [],
        );

        self::assertSame([], CourseSupport::getCmsBySection(10, 1));
    }

    #[Test]
    public function testGetCmsBySectionSwallowsAMoodleException(): void
    {
        $GLOBALS['__middag_test_get_course_throw'] = new moodle_exception('coursefail');

        self::assertSame([], CourseSupport::getCmsBySection(10, 1));
    }

    #[Test]
    public function testGetCoursesFromCategoryidIncludesSubcategories(): void
    {
        $child = new class {
            public int $id = 6;

            public function get_children(): array
            {
                return [];
            }
        };
        $parent = new class($child) {
            public int $id = 5;

            public function __construct(private readonly object $child) {}

            public function get_children(): array
            {
                return [$this->child];
            }
        };
        $GLOBALS['__middag_test_categories'] = [5 => $parent, 6 => $child];
        $GLOBALS['__middag_test_records_sql'] = [(object) ['id' => 1], (object) ['id' => 2]];

        $records = CourseSupport::getCoursesFromCategoryid(5);

        self::assertCount(2, $records);
    }

    #[Test]
    public function testGetCoursesFromCategoryidWithoutSubcategories(): void
    {
        $GLOBALS['__middag_test_records_sql'] = [(object) ['id' => 1]];

        $records = CourseSupport::getCoursesFromCategoryid(5, false);

        self::assertCount(1, $records);
    }

    #[Test]
    public function testGetCoursesFromCategoryidReturnsEmptyWhenTheReadThrows(): void
    {
        $GLOBALS['__middag_test_throw_get_records_sql'] = true;

        self::assertSame([], CourseSupport::getCoursesFromCategoryid(5, false));
    }

    #[Test]
    public function testGetCourseByContextidReturnsNullForZeroContext(): void
    {
        self::assertNull(CourseSupport::getCourseByContextid(0));
    }

    #[Test]
    public function testGetCourseByContextidReturnsNullWhenContextRecordMissing(): void
    {
        $GLOBALS['__middag_test_records'] = ['context' => false];

        self::assertNull(CourseSupport::getCourseByContextid(50));
    }

    #[Test]
    public function testGetCourseByContextidReturnsNullForNonCourseContext(): void
    {
        $GLOBALS['__middag_test_records'] = [
            'context' => (object) ['id' => 50, 'contextlevel' => 70, 'instanceid' => 10],
        ];

        self::assertNull(CourseSupport::getCourseByContextid(50));
    }

    #[Test]
    public function testGetCourseByContextidResolvesTheCourse(): void
    {
        $GLOBALS['__middag_test_records'] = [
            'context' => (object) ['id' => 50, 'contextlevel' => CONTEXT_COURSE, 'instanceid' => 10],
            'course' => (object) ['id' => 10, 'fullname' => 'Algebra'],
        ];

        $course = CourseSupport::getCourseByContextid(50);

        self::assertInstanceOf(Course::class, $course);
        self::assertSame('Algebra', $course->fullname);
    }

    #[Test]
    public function testGetCourseByContextidReturnsNullWhenTheReadThrows(): void
    {
        $GLOBALS['__middag_test_throw_get_record'] = 'context';

        self::assertNull(CourseSupport::getCourseByContextid(50));
    }

    #[Test]
    public function testGetCourseContextReturnsNullForZeroCourse(): void
    {
        self::assertNull(CourseSupport::getCourseContext(0));
    }

    #[Test]
    public function testGetCourseContextReturnsTheCourseContext(): void
    {
        $context = CourseSupport::getCourseContext(10);

        self::assertInstanceOf(context_course::class, $context);
        self::assertSame(10, $context->id);
    }

    #[Test]
    public function testGetCourseUrlBuildsAViewUrl(): void
    {
        $url = CourseSupport::getCourseUrl(10);

        self::assertInstanceOf(moodle_url::class, $url);
        self::assertSame('/course/view.php', (string) $url);
    }

    #[Test]
    public function testGetCourseUrlAddsTheSectionIdParam(): void
    {
        $url = CourseSupport::getCourseUrl(10, 3);

        self::assertSame(10, $url->params['id']);
        // The id must ride the `sectionid` key (course/view.php resolves it via
        // DB), not the raw section-NUMBER `section` key.
        self::assertSame(3, $url->params['sectionid']);
        self::assertArrayNotHasKey('section', $url->params);
    }

    #[Test]
    public function testGetCourseUrlAppliesTheAnchor(): void
    {
        if (!method_exists(moodle_url::class, 'set_anchor')) {
            self::markTestSkipped('central core\url stub lacks set_anchor() — see centralStubNeeds');
        }

        $url = CourseSupport::getCourseUrl(10, null, 'module-123');

        self::assertInstanceOf(moodle_url::class, $url);
    }

    #[Test]
    public function testIsCourseVisibleReflectsTheVisibleFlag(): void
    {
        $GLOBALS['__middag_test_records'] = ['course' => (object) ['id' => 10, 'visible' => 1]];
        self::assertTrue(CourseSupport::isCourseVisible(10));

        $GLOBALS['__middag_test_records'] = ['course' => (object) ['id' => 10, 'visible' => 0]];
        self::assertFalse(CourseSupport::isCourseVisible(10));
    }

    #[Test]
    public function testIsCourseVisibleReturnsFalseWhenCourseMissing(): void
    {
        $GLOBALS['__middag_test_records'] = ['course' => false];

        self::assertFalse(CourseSupport::isCourseVisible(10));
    }

    #[Test]
    public function testGetCourseModulesReturnsRecords(): void
    {
        $GLOBALS['__middag_test_records_sql'] = [(object) ['id' => 1, 'modname' => 'forum']];

        self::assertCount(1, CourseSupport::getCourseModules(10));
    }

    #[Test]
    public function testGetCourseModulesFiltersByModuleName(): void
    {
        $GLOBALS['__middag_test_records_sql'] = [(object) ['id' => 1, 'modname' => 'quiz']];

        self::assertCount(1, CourseSupport::getCourseModules(10, 'quiz'));
    }

    #[Test]
    public function testGetCourseModulesReturnsEmptyWhenTheReadThrows(): void
    {
        $GLOBALS['__middag_test_throw_get_records_sql'] = true;

        self::assertSame([], CourseSupport::getCourseModules(10));
    }

    #[Test]
    public function testGetEnrolledUsersCountReturnsZeroWhenContextUnavailable(): void
    {
        self::assertSame(0, CourseSupport::getEnrolledUsersCount(0));
    }

    #[Test]
    public function testGetEnrolledUsersCountCountsEnrolments(): void
    {
        if (!method_exists(moodle_database::class, 'count_records_sql')) {
            self::markTestSkipped('central moodle_database stub lacks count_records_sql() — see centralStubNeeds');
        }

        $db = $this->createMock(moodle_database::class);
        $db->method('count_records_sql')->willReturn(7);
        $GLOBALS['DB'] = $db;

        self::assertSame(7, CourseSupport::getEnrolledUsersCount(10, true, 3));
    }

    #[Test]
    public function testGetEnrolledUsersCountReturnsZeroWhenTheCountThrows(): void
    {
        if (!method_exists(moodle_database::class, 'count_records_sql')) {
            self::markTestSkipped('central moodle_database stub lacks count_records_sql() — see centralStubNeeds');
        }

        $db = $this->createMock(moodle_database::class);
        $db->method('count_records_sql')->willThrowException(new dml_exception('countfailed'));
        $GLOBALS['DB'] = $db;

        self::assertSame(0, CourseSupport::getEnrolledUsersCount(10));
    }

    #[Test]
    public function testGetCourseCategoryMapsTheCategoryRecord(): void
    {
        $GLOBALS['__middag_test_records'] = [
            'course' => (object) ['id' => 10, 'category' => 5],
            'course_categories' => (object) ['id' => 5, 'name' => 'Math'],
        ];

        $category = CourseSupport::getCourseCategory(10);

        self::assertInstanceOf(Category::class, $category);
        self::assertSame('Math', $category->name);
    }

    #[Test]
    public function testGetCourseCategoryReturnsNullWhenCourseMissing(): void
    {
        $GLOBALS['__middag_test_records'] = ['course' => false];

        self::assertNull(CourseSupport::getCourseCategory(10));
    }

    #[Test]
    public function testGetCourseCategoryReturnsNullWhenCategoryRecordMissing(): void
    {
        $GLOBALS['__middag_test_records'] = [
            'course' => (object) ['id' => 10, 'category' => 5],
            'course_categories' => false,
        ];

        self::assertNull(CourseSupport::getCourseCategory(10));
    }

    #[Test]
    public function testGetCourseCategoryReturnsNullWhenTheCategoryReadThrows(): void
    {
        $GLOBALS['__middag_test_records'] = ['course' => (object) ['id' => 10, 'category' => 5]];
        $GLOBALS['__middag_test_throw_get_record'] = 'course_categories';

        self::assertNull(CourseSupport::getCourseCategory(10));
    }

    #[Test]
    public function testIsUserEnrolledReflectsEnrolmentState(): void
    {
        $GLOBALS['__middag_test_is_enrolled'] = true;
        self::assertTrue(CourseSupport::isUserEnrolled(10, 7));

        $GLOBALS['__middag_test_is_enrolled'] = false;
        self::assertFalse(CourseSupport::isUserEnrolled(10, 7));
    }

    #[Test]
    public function testIsUserEnrolledReturnsFalseWhenContextUnavailable(): void
    {
        self::assertFalse(CourseSupport::isUserEnrolled(0, 7));
    }

    #[Test]
    public function testIsUserEnrolledReturnsFalseWhenEnrolmentCheckThrows(): void
    {
        $GLOBALS['__middag_test_throw_is_enrolled'] = true;

        self::assertFalse(CourseSupport::isUserEnrolled(10, 7));
    }

    #[Test]
    public function testGetCourseFormatReturnsTheFormat(): void
    {
        $GLOBALS['__middag_test_records'] = ['course' => (object) ['id' => 10, 'format' => 'weeks']];

        self::assertSame('weeks', CourseSupport::getCourseFormat(10));
    }

    #[Test]
    public function testGetCourseFormatReturnsNullWhenCourseMissing(): void
    {
        $GLOBALS['__middag_test_records'] = ['course' => false];

        self::assertNull(CourseSupport::getCourseFormat(10));
    }

    #[Test]
    public function testGetCourseModulesTypedReturnsEmptyWhenModuleNameUnknown(): void
    {
        $GLOBALS['__middag_test_records'] = ['modules' => false];

        self::assertSame([], CourseSupport::getCourseModulesTyped(10, 'forum'));
    }

    #[Test]
    public function testGetCourseModulesTypedReturnsEmptyWhenTheModuleReadThrows(): void
    {
        $GLOBALS['__middag_test_throw_get_record'] = 'modules';

        self::assertSame([], CourseSupport::getCourseModulesTyped(10, 'forum'));
    }

    #[Test]
    public function testGetCourseModulesTypedMapsRecordsToEntities(): void
    {
        if (!method_exists(moodle_database::class, 'get_records_select')) {
            self::markTestSkipped('central moodle_database stub lacks get_records_select() — see centralStubNeeds');
        }

        $db = $this->createMock(moodle_database::class);
        $db->method('get_records_select')->willReturn([(object) ['id' => 44, 'course' => 10]]);
        $GLOBALS['DB'] = $db;

        $modules = CourseSupport::getCourseModulesTyped(10);

        self::assertArrayHasKey(44, $modules);
    }

    #[Test]
    public function testGetCourseModulesTypedFiltersByResolvedModule(): void
    {
        if (!method_exists(moodle_database::class, 'get_records_select')) {
            self::markTestSkipped('central moodle_database stub lacks get_records_select() — see centralStubNeeds');
        }

        $db = $this->createMock(moodle_database::class);
        $db->method('get_record')->willReturn((object) ['id' => 5]);
        $db->method('get_records_select')->willReturn([(object) ['id' => 88, 'course' => 10]]);
        $GLOBALS['DB'] = $db;

        $modules = CourseSupport::getCourseModulesTyped(10, 'forum');

        self::assertArrayHasKey(88, $modules);
    }

    private function installFormat(
        int $lastSection,
        ?object $section,
        array $sections,
        array $cms,
        bool $sectionVisible = true,
    ): void {
        $GLOBALS['__middag_test_course'] = (object) ['id' => 10];

        $modinfo = new class($section, $sections, $cms) {
            /**
             * @param array<int, array<int, int>> $sections
             * @param array<int, object>          $cms
             */
            public function __construct(public ?object $section, public array $sections, public array $cms) {}

            public function get_section_info(int $sectionnumber): ?object
            {
                return $this->section;
            }
        };

        $GLOBALS['__middag_test_course_format'] = new class($lastSection, $modinfo, $sectionVisible) {
            public function __construct(private readonly int $last, private readonly object $modinfo, private readonly bool $visible) {}

            public function get_last_section_number(): int
            {
                return $this->last;
            }

            public function get_modinfo(): object
            {
                return $this->modinfo;
            }

            public function is_section_visible(object $section): bool
            {
                return $this->visible;
            }
        };
    }

    private function cm(bool $visibleOnPage): object
    {
        return new class($visibleOnPage) {
            public function __construct(private readonly bool $visible) {}

            public function is_visible_on_course_page(): bool
            {
                return $this->visible;
            }
        };
    }

    private function installDb(): void
    {
        $db = $this->createMock(moodle_database::class);
        $db->method('get_record')->willReturnCallback(static function (string $table): mixed {
            $throw = $GLOBALS['__middag_test_throw_get_record'] ?? null;
            if ($throw === true || $throw === $table) {
                throw new dml_exception('readfailed');
            }

            return $GLOBALS['__middag_test_records'][$table] ?? false;
        });
        $db->method('get_records_sql')->willReturnCallback(static function (): array {
            if (!empty($GLOBALS['__middag_test_throw_get_records_sql'])) {
                throw new dml_exception('readfailed');
            }

            return $GLOBALS['__middag_test_records_sql'] ?? [];
        });
        $db->method('get_in_or_equal')->willReturn(['IN (:c1)', ['c1' => 5]]);
        $GLOBALS['DB'] = $db;
    }

    private function clearGlobals(): void
    {
        foreach (self::GLOBAL_KEYS as $key) {
            unset($GLOBALS[$key]);
        }
    }
}
