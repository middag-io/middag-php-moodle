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

use Middag\Moodle\Domain\Course\Course;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Course's only concrete member over the entity base is the table binding;
 * every accessor is inherited from AbstractMoodleEntity.
 *
 * @internal
 */
#[CoversClass(Course::class)]
final class CourseCoverageTest extends TestCase
{
    #[Test]
    public function tableIsCourse(): void
    {
        $this->assertSame('course', Course::getTable());
    }
}
