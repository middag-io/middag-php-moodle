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

use dml_exception;
use Middag\Moodle\Domain\Course\Category;
use Middag\Moodle\Support\CategorySupport;
use moodle_database;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * CategorySupport wraps $DB reads and the core_course_category recursion. $DB is
 * a mocked moodle_database; the category tree is driven by
 * $GLOBALS['__middag_test_categories'] (see tests/stubs/support/course.php).
 *
 * @internal
 */
#[CoversClass(CategorySupport::class)]
final class CategorySupportCoverageTest extends TestCase
{
    private mixed $prevDb;

    protected function setUp(): void
    {
        $this->prevDb = $GLOBALS['DB'] ?? null;
        unset(
            $GLOBALS['__middag_test_categories'],
            $GLOBALS['__middag_test_throw_core_course_category'],
            $GLOBALS['__middag_test_context_course_throw_ids'],
        );
    }

    protected function tearDown(): void
    {
        $GLOBALS['DB'] = $this->prevDb;
        unset(
            $GLOBALS['__middag_test_categories'],
            $GLOBALS['__middag_test_throw_core_course_category'],
            $GLOBALS['__middag_test_context_course_throw_ids'],
        );
    }

    #[Test]
    public function testGetCategoryMapsARecordToACategoryEntity(): void
    {
        $db = $this->createMock(moodle_database::class);
        $db->method('get_record')->willReturn((object) ['id' => 7, 'name' => 'Mathematics']);
        $GLOBALS['DB'] = $db;

        $category = CategorySupport::getCategory(7);

        self::assertInstanceOf(Category::class, $category);
        self::assertSame('Mathematics', $category->name);
    }

    #[Test]
    public function testGetCategoryReturnsNullWhenTheRecordIsMissing(): void
    {
        $db = $this->createMock(moodle_database::class);
        $db->method('get_record')->willReturn(false);
        $GLOBALS['DB'] = $db;

        self::assertNull(CategorySupport::getCategory(99));
    }

    #[Test]
    public function testGetCategoryReturnsNullWhenTheReadThrows(): void
    {
        $db = $this->createMock(moodle_database::class);
        $db->method('get_record')->willThrowException(new dml_exception('readfailed'));
        $GLOBALS['DB'] = $db;

        self::assertNull(CategorySupport::getCategory(7));
    }

    #[Test]
    public function testGetCategoryContextOptionsBuildsLabelsIndexedByContextId(): void
    {
        $db = $this->createMock(moodle_database::class);
        $db->method('get_records')->willReturn([
            (object) ['id' => 5, 'name' => 'Math'],
            (object) ['id' => 6, 'name' => 'Science'],
        ]);
        $GLOBALS['DB'] = $db;

        $options = CategorySupport::getCategoryContextOptions();

        self::assertSame('ID 5 - Math', $options[5]);
        self::assertSame('ID 6 - Science', $options[6]);
    }

    #[Test]
    public function testGetCategoryContextOptionsSkipsACategoryWhoseContextIsMissing(): void
    {
        $db = $this->createMock(moodle_database::class);
        $db->method('get_records')->willReturn([
            (object) ['id' => 5, 'name' => 'Math'],
            (object) ['id' => 6, 'name' => 'Science'],
        ]);
        $GLOBALS['DB'] = $db;

        // context::instance() defaults to MUST_EXIST; a category whose
        // context row vanished must degrade to a partial list, not abort.
        $GLOBALS['__middag_test_context_course_throw_ids'] = [6];

        self::assertSame([5 => 'ID 5 - Math'], CategorySupport::getCategoryContextOptions());
    }

    #[Test]
    public function testGetCategoryContextOptionsReturnsEmptyWhenTheReadThrows(): void
    {
        $db = $this->createMock(moodle_database::class);
        $db->method('get_records')->willThrowException(new dml_exception('readfailed'));
        $GLOBALS['DB'] = $db;

        self::assertSame([], CategorySupport::getCategoryContextOptions());
    }

    #[Test]
    public function testGetSubcategoriesRecursiveCollectsNestedChildIds(): void
    {
        $grandchild = new class {
            public int $id = 12;

            public function get_children(): array
            {
                return [];
            }
        };
        $child = new class($grandchild) {
            public int $id = 11;

            public function __construct(private readonly object $grandchild) {}

            public function get_children(): array
            {
                return [$this->grandchild];
            }
        };
        $parent = new class($child) {
            public int $id = 10;

            public function __construct(private readonly object $child) {}

            public function get_children(): array
            {
                return [$this->child];
            }
        };

        $GLOBALS['__middag_test_categories'] = [10 => $parent, 11 => $child, 12 => $grandchild];

        $subcategories = [];
        CategorySupport::getSubcategoriesRecursive(10, $subcategories);

        self::assertSame([11, 12], $subcategories);
    }

    #[Test]
    public function testGetSubcategoriesRecursiveLeavesTheListUntouchedWhenCategoryIsMissing(): void
    {
        $GLOBALS['__middag_test_categories'] = [];

        $subcategories = [];
        CategorySupport::getSubcategoriesRecursive(404, $subcategories);

        self::assertSame([], $subcategories);
    }

    #[Test]
    public function testGetSubcategoriesRecursiveSwallowsAMoodleException(): void
    {
        $GLOBALS['__middag_test_throw_core_course_category'] = true;

        $subcategories = [];
        CategorySupport::getSubcategoriesRecursive(10, $subcategories);

        self::assertSame([], $subcategories);
    }
}
