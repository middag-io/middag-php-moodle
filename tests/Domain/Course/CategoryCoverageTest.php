<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Domain\Course;

use Middag\Moodle\Domain\Course\Category;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Category's only concrete member over the entity base is the table binding;
 * every accessor is inherited from AbstractMoodleEntity.
 *
 * @internal
 */
#[CoversClass(Category::class)]
final class CategoryCoverageTest extends TestCase
{
    #[Test]
    public function tableIsCourseCategories(): void
    {
        $this->assertSame('course_categories', Category::getTable());
    }
}
