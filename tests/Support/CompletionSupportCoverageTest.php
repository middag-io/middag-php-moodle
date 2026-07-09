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

use core\exception\moodle_exception;
use dml_exception;
use Middag\Moodle\Domain\Completion\Completion;
use Middag\Moodle\Domain\Completion\CompletionProgressDto;
use Middag\Moodle\Domain\Completion\CompletionState;
use Middag\Moodle\Domain\Completion\CompletionTracking;
use Middag\Moodle\Domain\Completion\CourseCompletion;
use Middag\Moodle\Support\CompletionSupport;
use moodle_database;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * CompletionSupport wraps completion_info / completion_completion / get_fast_modinfo
 * (data-driven stand-ins in tests/stubs/support/course.php) plus $DB reads. $CFG,
 * $DB and every collaborator return value are driven via $GLOBALS['__middag_test_*'].
 *
 * @internal
 */
#[CoversClass(CompletionSupport::class)]
final class CompletionSupportCoverageTest extends TestCase
{
    /** @var list<string> */
    private const GLOBAL_KEYS = [
        '__middag_test_completion_enabled',
        '__middag_test_course_complete',
        '__middag_test_completion_data',
        '__middag_test_completion_data_map',
        '__middag_test_completion_activities',
        '__middag_test_tracked_users',
        '__middag_test_updated_state',
        '__middag_test_marked_enrolled',
        '__middag_test_modinfo',
        '__middag_test_completion_info_throw',
        '__middag_test_course_record',
        '__middag_test_course_completion_record',
        '__middag_test_criteria_records',
        '__middag_test_throw_is_enabled',
        '__middag_test_throw_is_course_complete',
        '__middag_test_throw_get_data',
        '__middag_test_throw_get_activities',
        '__middag_test_throw_update_state',
        '__middag_test_throw_get_num_tracked_users',
        '__middag_test_throw_mark_enrolled',
        '__middag_test_throw_get_fast_modinfo',
        '__middag_test_throw_get_record',
        '__middag_test_throw_get_records',
    ];

    private mixed $prevDb;

    private mixed $prevCfg;

    protected function setUp(): void
    {
        $this->prevDb = $GLOBALS['DB'] ?? null;
        $this->prevCfg = $GLOBALS['CFG'] ?? null;

        $this->clearGlobals();
        $GLOBALS['CFG'] = (object) ['enablecompletion' => 1];
        $this->installDb();
    }

    protected function tearDown(): void
    {
        $this->clearGlobals();
        $GLOBALS['DB'] = $this->prevDb;
        $GLOBALS['CFG'] = $this->prevCfg;
    }

    #[Test]
    public function testIsEnabledSiteReflectsTheCfgFlag(): void
    {
        self::assertTrue(CompletionSupport::isEnabledSite());

        $GLOBALS['CFG'] = (object) ['enablecompletion' => 0];
        self::assertFalse(CompletionSupport::isEnabledSite());
    }

    #[Test]
    public function testIsEnabledSiteReturnsFalseWhenReadingCfgThrows(): void
    {
        $GLOBALS['CFG'] = new class {
            public function __isset(string $name): bool
            {
                throw new RuntimeException('cfg read failed');
            }

            public function __get(string $name): mixed
            {
                return null;
            }
        };

        self::assertFalse(CompletionSupport::isEnabledSite());
    }

    #[Test]
    public function testGuardsRejectNonPositiveIds(): void
    {
        self::assertNull(CompletionSupport::getCmTracking(0, 5));
        self::assertNull(CompletionSupport::getCmTracking(5, 0));
        self::assertFalse(CompletionSupport::isEnabledCm(0, 5));
        self::assertFalse(CompletionSupport::isCourseComplete(0, 5));
        self::assertFalse(CompletionSupport::isCourseComplete(5, 0));
        self::assertNull(CompletionSupport::getCourseCompletion(0, 5));
        self::assertNull(CompletionSupport::getCourseCompletion(5, 0));
        self::assertNull(CompletionSupport::getCmCompletion(0, 5, 7));
        self::assertNull(CompletionSupport::getCmCompletion(5, 0, 7));
        self::assertNull(CompletionSupport::getCmCompletion(5, 6, 0));
        self::assertNull(CompletionSupport::getCourseProgress(0, 5));
        self::assertNull(CompletionSupport::getCourseProgress(5, 0));
        self::assertSame([], CompletionSupport::getCourseCmCompletions(0, 5));
        self::assertSame([], CompletionSupport::getCourseCmCompletions(5, 0));
        self::assertFalse(CompletionSupport::updateCmState(0, 5, 7, CompletionState::Complete));
        self::assertSame(0, CompletionSupport::getTrackedUsersCount(0));
        self::assertSame([], CompletionSupport::getCourseCriteria(0));
        self::assertFalse(CompletionSupport::markCourseEnrolled(0, 5));
        self::assertFalse(CompletionSupport::markCourseEnrolled(5, 0));
    }

    #[Test]
    public function testIsEnabledCourseFalseWhenSiteDisabled(): void
    {
        $GLOBALS['CFG'] = (object) ['enablecompletion' => 0];

        self::assertFalse(CompletionSupport::isEnabledCourse(10));
    }

    #[Test]
    public function testIsEnabledCourseFalseWhenCourseInfoUnavailable(): void
    {
        $GLOBALS['__middag_test_course_record'] = false;

        self::assertFalse(CompletionSupport::isEnabledCourse(10));
    }

    #[Test]
    public function testIsEnabledCourseTrueWhenCompletionInfoEnabled(): void
    {
        $GLOBALS['__middag_test_completion_enabled'] = true;

        self::assertTrue(CompletionSupport::isEnabledCourse(10));
    }

    #[Test]
    public function testIsEnabledCourseFalseWhenIsEnabledThrows(): void
    {
        $GLOBALS['__middag_test_throw_is_enabled'] = true;

        self::assertFalse(CompletionSupport::isEnabledCourse(10));
    }

    #[Test]
    public function testIsEnabledCmReflectsTrackingMode(): void
    {
        $GLOBALS['__middag_test_modinfo'] = (object) ['cms' => [6 => (object) ['completion' => 1]]];
        self::assertTrue(CompletionSupport::isEnabledCm(10, 6));

        $GLOBALS['__middag_test_modinfo'] = (object) ['cms' => [6 => (object) ['completion' => 0]]];
        self::assertFalse(CompletionSupport::isEnabledCm(10, 6));
    }

    #[Test]
    public function testGetCmTrackingResolvesTheConfiguredMode(): void
    {
        $GLOBALS['__middag_test_modinfo'] = (object) ['cms' => [6 => (object) ['completion' => 2]]];

        self::assertSame(CompletionTracking::Automatic, CompletionSupport::getCmTracking(10, 6));
    }

    #[Test]
    public function testGetCmTrackingResolvesNoneForAnUntrackedModule(): void
    {
        // A real cm_info always carries a completion field; 0 is
        // COMPLETION_TRACKING_NONE (an untracked module).
        $GLOBALS['__middag_test_modinfo'] = (object) ['cms' => [6 => (object) ['completion' => 0]]];

        self::assertSame(CompletionTracking::None, CompletionSupport::getCmTracking(10, 6));
    }

    #[Test]
    public function testGetCmTrackingReturnsNullWhenModuleAbsent(): void
    {
        $GLOBALS['__middag_test_modinfo'] = (object) ['cms' => []];

        self::assertNull(CompletionSupport::getCmTracking(10, 6));
    }

    #[Test]
    public function testGetCmTrackingReturnsNullWhenModinfoThrows(): void
    {
        $GLOBALS['__middag_test_throw_get_fast_modinfo'] = true;

        self::assertNull(CompletionSupport::getCmTracking(10, 6));
    }

    #[Test]
    public function testIsCourseCompleteReflectsTheCompletionInfo(): void
    {
        $GLOBALS['__middag_test_course_complete'] = true;
        self::assertTrue(CompletionSupport::isCourseComplete(10, 7));

        $GLOBALS['__middag_test_course_complete'] = false;
        self::assertFalse(CompletionSupport::isCourseComplete(10, 7));
    }

    #[Test]
    public function testIsCourseCompleteFalseWhenInfoUnavailable(): void
    {
        $GLOBALS['__middag_test_course_record'] = false;

        self::assertFalse(CompletionSupport::isCourseComplete(10, 7));
    }

    #[Test]
    public function testIsCourseCompleteFalseWhenTheCheckThrows(): void
    {
        $GLOBALS['__middag_test_throw_is_course_complete'] = true;

        self::assertFalse(CompletionSupport::isCourseComplete(10, 7));
    }

    #[Test]
    public function testGetCourseCompletionMapsTheRecord(): void
    {
        $GLOBALS['__middag_test_course_completion_record'] = (object) [
            'id' => 1, 'userid' => 7, 'course' => 10, 'timecompleted' => 999,
        ];

        $completion = CompletionSupport::getCourseCompletion(10, 7);

        self::assertInstanceOf(CourseCompletion::class, $completion);
        self::assertSame(999, $completion->get_timecompleted());
    }

    #[Test]
    public function testGetCourseCompletionReturnsNullWhenRecordMissing(): void
    {
        self::assertNull(CompletionSupport::getCourseCompletion(10, 7));
    }

    #[Test]
    public function testGetCourseCompletionReturnsNullWhenReadThrows(): void
    {
        $GLOBALS['__middag_test_throw_get_record'] = true;

        self::assertNull(CompletionSupport::getCourseCompletion(10, 7));
    }

    #[Test]
    public function testGetCmCompletionMapsAStdClassWithExistingIdentifiers(): void
    {
        $GLOBALS['__middag_test_modinfo'] = (object) ['cms' => [6 => (object) ['id' => 6]]];
        $GLOBALS['__middag_test_completion_data'] = (object) [
            'completionstate' => 1, 'coursemoduleid' => 99, 'userid' => 7,
        ];

        $completion = CompletionSupport::getCmCompletion(10, 6, 7);

        self::assertInstanceOf(Completion::class, $completion);
        self::assertSame(99, $completion->get_coursemoduleid());
    }

    #[Test]
    public function testGetCmCompletionCoercesArrayDataAndBackfillsIdentifiers(): void
    {
        $GLOBALS['__middag_test_modinfo'] = (object) ['cms' => [6 => (object) ['id' => 6]]];
        $GLOBALS['__middag_test_completion_data'] = ['completionstate' => 1];

        $completion = CompletionSupport::getCmCompletion(10, 6, 7);

        self::assertInstanceOf(Completion::class, $completion);
        self::assertSame(6, $completion->get_coursemoduleid());
        self::assertSame(7, $completion->get_userid());
    }

    #[Test]
    public function testGetCmCompletionReturnsNullWhenInfoUnavailable(): void
    {
        $GLOBALS['__middag_test_course_record'] = false;

        self::assertNull(CompletionSupport::getCmCompletion(10, 6, 7));
    }

    #[Test]
    public function testGetCmCompletionReturnsNullWhenModuleAbsent(): void
    {
        $GLOBALS['__middag_test_modinfo'] = (object) ['cms' => []];

        self::assertNull(CompletionSupport::getCmCompletion(10, 6, 7));
    }

    #[Test]
    public function testGetCmCompletionReturnsNullWhenNoDataRecorded(): void
    {
        $GLOBALS['__middag_test_modinfo'] = (object) ['cms' => [6 => (object) ['id' => 6]]];

        self::assertNull(CompletionSupport::getCmCompletion(10, 6, 7));
    }

    #[Test]
    public function testGetCmCompletionReturnsNullWhenModinfoThrows(): void
    {
        $GLOBALS['__middag_test_throw_get_fast_modinfo'] = true;

        self::assertNull(CompletionSupport::getCmCompletion(10, 6, 7));
    }

    #[Test]
    public function testGetCourseProgressReturnsDisabledDtoWhenCompletionOff(): void
    {
        $GLOBALS['CFG'] = (object) ['enablecompletion' => 0];

        $dto = CompletionSupport::getCourseProgress(10, 7);

        self::assertInstanceOf(CompletionProgressDto::class, $dto);
        self::assertFalse($dto->enabled);
        self::assertSame(0, $dto->totalActivities);
    }

    #[Test]
    public function testGetCourseProgressAggregatesActivityCompletion(): void
    {
        $GLOBALS['__middag_test_completion_activities'] = [
            (object) ['id' => 1],
            (object) ['id' => 2],
            (object) ['id' => 3],
        ];
        $GLOBALS['__middag_test_completion_data_map'] = [
            1 => (object) ['completionstate' => 1],
            2 => (object) ['completionstate' => 0],
        ];
        $GLOBALS['__middag_test_course_completion_record'] = (object) [
            'id' => 1, 'userid' => 7, 'course' => 10, 'timecompleted' => 999,
        ];

        $dto = CompletionSupport::getCourseProgress(10, 7);

        self::assertInstanceOf(CompletionProgressDto::class, $dto);
        self::assertSame(3, $dto->totalActivities);
        self::assertSame(1, $dto->completedActivities);
        self::assertSame(33.33, $dto->percentage);
        self::assertSame(999, $dto->timecompleted);
        self::assertTrue($dto->enabled);
    }

    #[Test]
    public function testGetCourseProgressReturnsNullWhenActivitiesThrow(): void
    {
        $GLOBALS['__middag_test_throw_get_activities'] = true;

        self::assertNull(CompletionSupport::getCourseProgress(10, 7));
    }

    #[Test]
    public function testGetCourseProgressReturnsNullWhenInfoUnavailableAfterEnablement(): void
    {
        // isEnabledCourse() resolves completion_info once (first get_record yields a
        // course → completion enabled), then getCourseProgress() re-resolves
        // infoForCourse(): a second get_record that returns no course makes the
        // re-resolution null, exercising the `!$info instanceof completion_info`
        // guard (return null) *after* the enablement check has already passed.
        $db = $this->createMock(moodle_database::class);
        $calls = 0;
        $db->method('get_record')->willReturnCallback(
            static function (string $table) use (&$calls): mixed {
                if ($table !== 'course') {
                    return false;
                }
                ++$calls;

                return $calls === 1 ? (object) ['id' => 10] : false;
            },
        );
        $GLOBALS['DB'] = $db;

        self::assertNull(CompletionSupport::getCourseProgress(10, 7));
    }

    #[Test]
    public function testGetCourseCmCompletionsBuildsEntriesIndexedByCmid(): void
    {
        $GLOBALS['__middag_test_completion_activities'] = [
            (object) ['id' => 1],
            (object) ['id' => 2],
        ];
        $GLOBALS['__middag_test_completion_data_map'] = [
            1 => (object) ['completionstate' => 1],
        ];

        $completions = CompletionSupport::getCourseCmCompletions(10, 7);

        self::assertArrayHasKey(1, $completions);
        self::assertInstanceOf(Completion::class, $completions[1]);
        self::assertArrayNotHasKey(2, $completions);
    }

    #[Test]
    public function testGetCourseCmCompletionsReturnsEmptyWhenInfoUnavailable(): void
    {
        $GLOBALS['__middag_test_course_record'] = false;

        self::assertSame([], CompletionSupport::getCourseCmCompletions(10, 7));
    }

    #[Test]
    public function testGetCourseCmCompletionsSwallowsErrors(): void
    {
        $GLOBALS['__middag_test_throw_get_activities'] = true;

        self::assertSame([], CompletionSupport::getCourseCmCompletions(10, 7));
    }

    #[Test]
    public function testUpdateCmStateReturnsTrueOnSuccess(): void
    {
        $GLOBALS['__middag_test_modinfo'] = (object) ['cms' => [6 => (object) ['id' => 6]]];

        self::assertTrue(CompletionSupport::updateCmState(10, 6, 7, CompletionState::Complete));
        self::assertSame([CompletionState::Complete->value, 7], $GLOBALS['__middag_test_updated_state']);
    }

    #[Test]
    public function testUpdateCmStateReturnsFalseWhenInfoUnavailable(): void
    {
        $GLOBALS['__middag_test_course_record'] = false;

        self::assertFalse(CompletionSupport::updateCmState(10, 6, 7, CompletionState::Complete));
    }

    #[Test]
    public function testUpdateCmStateReturnsFalseWhenModuleAbsent(): void
    {
        $GLOBALS['__middag_test_modinfo'] = (object) ['cms' => []];

        self::assertFalse(CompletionSupport::updateCmState(10, 6, 7, CompletionState::Complete));
    }

    #[Test]
    public function testUpdateCmStateReturnsFalseWhenUpdateThrows(): void
    {
        $GLOBALS['__middag_test_modinfo'] = (object) ['cms' => [6 => (object) ['id' => 6]]];
        $GLOBALS['__middag_test_throw_update_state'] = true;

        self::assertFalse(CompletionSupport::updateCmState(10, 6, 7, CompletionState::Complete));
    }

    #[Test]
    public function testGetTrackedUsersCountReturnsTheCount(): void
    {
        $GLOBALS['__middag_test_tracked_users'] = 5;

        self::assertSame(5, CompletionSupport::getTrackedUsersCount(10, 3));
    }

    #[Test]
    public function testGetTrackedUsersCountReturnsZeroWhenInfoUnavailable(): void
    {
        $GLOBALS['__middag_test_course_record'] = false;

        self::assertSame(0, CompletionSupport::getTrackedUsersCount(10));
    }

    #[Test]
    public function testGetTrackedUsersCountReturnsZeroWhenCountThrows(): void
    {
        $GLOBALS['__middag_test_throw_get_num_tracked_users'] = true;

        self::assertSame(0, CompletionSupport::getTrackedUsersCount(10));
    }

    #[Test]
    public function testGetCourseCriteriaBuildsDtosIndexedByCriterionId(): void
    {
        $GLOBALS['__middag_test_criteria_records'] = [
            (object) [
                'id' => 2, 'course' => 10, 'criteriatype' => 'activity', 'moduleinstance' => 5,
                'gradepass' => 60.0, 'role' => 3, 'timeend' => 100, 'enrolperiod' => 200,
                'courseinstance' => 7,
            ],
            (object) ['course' => 10, 'criteriatype' => 'self'],
        ];

        $criteria = CompletionSupport::getCourseCriteria(10);

        self::assertSame(2, $criteria[2]->id);
        self::assertSame('activity', $criteria[2]->criteriaType);
        self::assertSame(5, $criteria[2]->moduleinstance);
        self::assertNull($criteria[0]->id);
        self::assertSame('self', $criteria[0]->criteriaType);
    }

    #[Test]
    public function testGetCourseCriteriaReturnsEmptyWhenReadThrows(): void
    {
        $GLOBALS['__middag_test_throw_get_records'] = true;

        self::assertSame([], CompletionSupport::getCourseCriteria(10));
    }

    #[Test]
    public function testMarkCourseEnrolledReturnsTrueOnSuccess(): void
    {
        self::assertTrue(CompletionSupport::markCourseEnrolled(10, 7));
        self::assertSame(['userid' => 7, 'course' => 10], $GLOBALS['__middag_test_marked_enrolled']);
    }

    #[Test]
    public function testMarkCourseEnrolledReturnsFalseWhenMarkThrows(): void
    {
        $GLOBALS['__middag_test_throw_mark_enrolled'] = true;

        self::assertFalse(CompletionSupport::markCourseEnrolled(10, 7));
    }

    #[Test]
    public function testInfoForCourseSwallowsADmlException(): void
    {
        $GLOBALS['__middag_test_throw_get_record'] = true;

        // isEnabledCourse reaches infoForCourse, whose get_record('course') throws dml.
        self::assertFalse(CompletionSupport::isEnabledCourse(10));
    }

    #[Test]
    public function testInfoForCourseSwallowsAMoodleException(): void
    {
        $GLOBALS['__middag_test_completion_info_throw'] = new moodle_exception('completionfail');

        self::assertFalse(CompletionSupport::isEnabledCourse(10));
    }

    #[Test]
    public function testInfoForCourseSwallowsAGenericException(): void
    {
        $GLOBALS['__middag_test_completion_info_throw'] = new RuntimeException('completionfail');

        self::assertFalse(CompletionSupport::isEnabledCourse(10));
    }

    private function installDb(): void
    {
        $db = $this->createMock(moodle_database::class);
        $db->method('get_record')->willReturnCallback(static function (string $table): mixed {
            if (!empty($GLOBALS['__middag_test_throw_get_record'])) {
                throw new dml_exception('readfailed');
            }
            if ($table === 'course') {
                return $GLOBALS['__middag_test_course_record'] ?? (object) ['id' => 10];
            }
            if ($table === 'course_completions') {
                return $GLOBALS['__middag_test_course_completion_record'] ?? false;
            }

            return false;
        });
        $db->method('get_records')->willReturnCallback(static function (): array {
            if (!empty($GLOBALS['__middag_test_throw_get_records'])) {
                throw new dml_exception('readfailed');
            }

            return $GLOBALS['__middag_test_criteria_records'] ?? [];
        });
        $GLOBALS['DB'] = $db;
    }

    private function clearGlobals(): void
    {
        foreach (self::GLOBAL_KEYS as $key) {
            unset($GLOBALS[$key]);
        }
    }
}
