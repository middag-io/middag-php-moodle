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

use Middag\Moodle\Support\CompetencySupport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * CompetencySupport wraps the static core_competency\api facade (stubbed in
 * tests/stubs/support/course.php). Enablement, returned persistents and per-method
 * failures are all driven via $GLOBALS['__middag_test_*'].
 *
 * @internal
 */
#[CoversClass(CompetencySupport::class)]
final class CompetencySupportCoverageTest extends TestCase
{
    /** @var list<string> */
    private const GLOBAL_KEYS = [
        '__middag_test_competency_enabled',
        '__middag_test_throw_competency_is_enabled',
        '__middag_test_frameworks',
        '__middag_test_framework',
        '__middag_test_competencies',
        '__middag_test_competency',
        '__middag_test_user_competency',
        '__middag_test_user_competencies',
        '__middag_test_evidence',
        '__middag_test_evidence_list',
        '__middag_test_throw_list_frameworks',
        '__middag_test_throw_read_framework',
        '__middag_test_throw_list_competencies',
        '__middag_test_throw_read_competency',
        '__middag_test_throw_get_user_competency',
        '__middag_test_throw_list_user_competencies_in_course',
        '__middag_test_throw_add_evidence',
        '__middag_test_throw_list_evidence',
    ];

    protected function setUp(): void
    {
        $this->clearGlobals();
    }

    protected function tearDown(): void
    {
        $this->clearGlobals();
    }

    #[Test]
    public function testIsEnabledReturnsTrueByDefault(): void
    {
        self::assertTrue(CompetencySupport::isEnabled());
    }

    #[Test]
    public function testIsEnabledReturnsFalseWhenTheApiReportsDisabled(): void
    {
        $GLOBALS['__middag_test_competency_enabled'] = false;

        self::assertFalse(CompetencySupport::isEnabled());
    }

    #[Test]
    public function testIsEnabledReturnsFalseWhenTheApiThrows(): void
    {
        $GLOBALS['__middag_test_throw_competency_is_enabled'] = true;

        self::assertFalse(CompetencySupport::isEnabled());
    }

    #[Test]
    public function testPublicReadersShortCircuitWhenSubsystemDisabled(): void
    {
        $GLOBALS['__middag_test_competency_enabled'] = false;

        self::assertSame([], CompetencySupport::listFrameworks());
        self::assertNull(CompetencySupport::getFramework(1));
        self::assertSame([], CompetencySupport::listCompetencies(1));
        self::assertNull(CompetencySupport::getCompetency(1));
        self::assertNull(CompetencySupport::getUserCompetency(1, 2));
        self::assertSame([], CompetencySupport::listUserCompetenciesInCourse(1, 2));
        self::assertNull(CompetencySupport::addEvidence(1, 2, CompetencySupport::ACTION_LOG, 'k', 'c'));
        self::assertSame([], CompetencySupport::listEvidence(1, 2));
    }

    #[Test]
    public function testPublicReadersSwallowApiErrors(): void
    {
        $GLOBALS['__middag_test_throw_list_frameworks'] = true;
        $GLOBALS['__middag_test_throw_read_framework'] = true;
        $GLOBALS['__middag_test_throw_list_competencies'] = true;
        $GLOBALS['__middag_test_throw_read_competency'] = true;
        $GLOBALS['__middag_test_throw_get_user_competency'] = true;
        $GLOBALS['__middag_test_throw_list_user_competencies_in_course'] = true;
        $GLOBALS['__middag_test_throw_add_evidence'] = true;
        $GLOBALS['__middag_test_throw_list_evidence'] = true;

        self::assertSame([], CompetencySupport::listFrameworks());
        self::assertNull(CompetencySupport::getFramework(1));
        self::assertSame([], CompetencySupport::listCompetencies(1));
        self::assertNull(CompetencySupport::getCompetency(1));
        self::assertNull(CompetencySupport::getUserCompetency(1, 2));
        self::assertSame([], CompetencySupport::listUserCompetenciesInCourse(1, 2));
        self::assertNull(CompetencySupport::addEvidence(1, 2, CompetencySupport::ACTION_OVERRIDE, 'k', 'c', 'note', 5));
        self::assertSame([], CompetencySupport::listEvidence(1, 2));
    }

    #[Test]
    public function testListFrameworksMapsPersistents(): void
    {
        $GLOBALS['__middag_test_frameworks'] = [
            $this->persistent([
                'id' => 3, 'shortname' => 'FW', 'idnumber' => 'F1', 'description' => 'desc',
                'visible' => 1, 'scaleid' => 2, 'timecreated' => 100, 'timemodified' => 200,
            ]),
        ];

        $result = CompetencySupport::listFrameworks();

        self::assertCount(1, $result);
        self::assertSame(3, $result[0]['id']);
        self::assertSame('FW', $result[0]['shortname']);
        self::assertTrue($result[0]['visible']);
    }

    #[Test]
    public function testGetFrameworkMapsPersistent(): void
    {
        $GLOBALS['__middag_test_framework'] = $this->persistent([
            'id' => 9, 'shortname' => 'Core', 'idnumber' => 'C', 'description' => '',
            'visible' => 0, 'scaleid' => 1, 'timecreated' => 1, 'timemodified' => 2,
        ]);

        $framework = CompetencySupport::getFramework(9);

        self::assertSame(9, $framework['id']);
        self::assertFalse($framework['visible']);
    }

    #[Test]
    public function testListCompetenciesMapsPersistents(): void
    {
        $GLOBALS['__middag_test_competencies'] = [
            $this->persistent([
                'id' => 4, 'shortname' => 'C1', 'idnumber' => 'I1', 'description' => 'd',
                'parentid' => 0, 'path' => '/4', 'sortorder' => 1, 'competencyframeworkid' => 3,
            ]),
        ];

        $result = CompetencySupport::listCompetencies(3);

        self::assertCount(1, $result);
        self::assertSame('/4', $result[0]['path']);
        self::assertSame(3, $result[0]['competencyframeworkid']);
    }

    #[Test]
    public function testGetCompetencyMapsPersistent(): void
    {
        $GLOBALS['__middag_test_competency'] = $this->persistent([
            'id' => 4, 'shortname' => 'C1', 'idnumber' => 'I1', 'description' => 'd',
            'parentid' => 1, 'path' => '/1/4', 'sortorder' => 2, 'competencyframeworkid' => 3,
        ]);

        $competency = CompetencySupport::getCompetency(4);

        self::assertSame(4, $competency['id']);
        self::assertSame(1, $competency['parentid']);
    }

    #[Test]
    public function testGetUserCompetencyMapsPersistent(): void
    {
        $GLOBALS['__middag_test_user_competency'] = $this->persistent([
            'id' => 10, 'userid' => 7, 'competencyid' => 4, 'proficiency' => true,
            'grade' => 3, 'status' => 1, 'reviewerid' => 2, 'timecreated' => 5, 'timemodified' => 6,
        ]);

        $uc = CompetencySupport::getUserCompetency(7, 4);

        self::assertSame(10, $uc['id']);
        self::assertSame(1, $uc['status']);
        self::assertTrue($uc['proficiency']);
    }

    #[Test]
    public function testListUserCompetenciesInCourseMapsPersistents(): void
    {
        $GLOBALS['__middag_test_user_competencies'] = [
            $this->persistent([
                'id' => 11, 'userid' => 7, 'competencyid' => 4, 'courseid' => 20,
                'proficiency' => false, 'grade' => null, 'timecreated' => 5, 'timemodified' => 6,
            ]),
        ];

        $result = CompetencySupport::listUserCompetenciesInCourse(20, 7);

        self::assertCount(1, $result);
        self::assertSame(20, $result[0]['courseid']);
        self::assertNull($result[0]['grade']);
    }

    #[Test]
    public function testActionConstantsMirrorTheMoodleEvidenceEnum(): void
    {
        // \core_competency\evidence defines ACTION_LOG=0, ACTION_COMPLETE=2,
        // ACTION_OVERRIDE=3 (there is deliberately no value 1). Passing any
        // other integer to api::add_evidence() lands on the switch default and
        // throws coding_exception, which addEvidence() would swallow into a
        // silent null. Pin the mapping so it can never regress into a 0/1/2
        // sequence again.
        self::assertSame(0, CompetencySupport::ACTION_LOG);
        self::assertSame(2, CompetencySupport::ACTION_COMPLETE);
        self::assertSame(3, CompetencySupport::ACTION_OVERRIDE);
    }

    #[Test]
    public function testAddEvidenceReturnsTheEvidenceId(): void
    {
        $GLOBALS['__middag_test_evidence'] = $this->persistent(['id' => 555]);

        self::assertSame(555, CompetencySupport::addEvidence(7, 4, CompetencySupport::ACTION_COMPLETE, 'key', 'component'));
    }

    #[Test]
    public function testAddEvidenceReturnsNullWhenNoEvidenceCreated(): void
    {
        self::assertNull(CompetencySupport::addEvidence(7, 4, CompetencySupport::ACTION_LOG, 'key', 'component'));
    }

    #[Test]
    public function testListEvidenceMapsPersistents(): void
    {
        $GLOBALS['__middag_test_evidence_list'] = [
            $this->persistent([
                'id' => 12, 'userid' => 7, 'competencyid' => 4, 'action' => 1, 'actionuserid' => 2,
                'description' => 'evi', 'grade' => 3, 'note' => 'n', 'url' => 'http://x',
                'timecreated' => 5, 'timemodified' => 6,
            ]),
        ];

        $result = CompetencySupport::listEvidence(7, 4);

        self::assertCount(1, $result);
        self::assertSame(12, $result[0]['id']);
        self::assertSame('evi', $result[0]['description']);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function persistent(array $data): object
    {
        return new class($data) {
            /**
             * @param array<string, mixed> $data
             */
            public function __construct(private array $data) {}

            public function get(string $key): mixed
            {
                return $this->data[$key] ?? null;
            }
        };
    }

    private function clearGlobals(): void
    {
        foreach (self::GLOBAL_KEYS as $key) {
            unset($GLOBALS[$key]);
        }
    }
}
