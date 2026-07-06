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

use core\context\base as context_base;
use core\context\block as context_block;
use core\context\course as context_course;
use core\context\coursecat as context_coursecat;
use core\context\module as context_module;
use core\context\user as context_user;
use Middag\Moodle\Support\ContextSupport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TypeError;

/**
 * @internal
 */
#[CoversClass(ContextSupport::class)]
final class ContextSupportCoverageTest extends TestCase
{
    #[Test]
    public function testSystemSurfacesTheStubReturnTypeMismatch(): void
    {
        // tests/bootstrap.php models core\context\system::instance() as returning a
        // base core\context, so the wrapper's declared core\context\system return
        // type is exercised and rejected — proving classFor('system') is wired.
        $this->expectException(TypeError::class);

        ContextSupport::system();
    }

    #[Test]
    public function testCourseReturnsACourseContext(): void
    {
        self::assertInstanceOf(context_course::class, ContextSupport::course(5));
    }

    #[Test]
    public function testCoursecatReturnsACategoryContext(): void
    {
        self::assertInstanceOf(context_coursecat::class, ContextSupport::coursecat(3));
    }

    #[Test]
    public function testModuleReturnsAModuleContext(): void
    {
        self::assertInstanceOf(context_module::class, ContextSupport::module(7));
    }

    #[Test]
    public function testUserReturnsAUserContext(): void
    {
        self::assertInstanceOf(context_user::class, ContextSupport::user(2));
    }

    #[Test]
    public function testBlockReturnsABlockContext(): void
    {
        self::assertInstanceOf(context_block::class, ContextSupport::block(9));
    }

    #[Test]
    public function testInstanceByIdPrefersTheNamespacedBaseApi(): void
    {
        self::assertInstanceOf(context_base::class, ContextSupport::instanceById(11));
    }
}
