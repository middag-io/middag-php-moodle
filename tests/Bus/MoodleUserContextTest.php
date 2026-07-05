<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Bus;

use Middag\Moodle\Bus\MoodleUserContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @internal
 */
#[CoversClass(MoodleUserContext::class)]
final class MoodleUserContextTest extends TestCase
{
    private MoodleUserContext $context;

    protected function setUp(): void
    {
        $this->context = new MoodleUserContext();
    }

    protected function tearDown(): void
    {
        // Clean up global $USER
        unset($GLOBALS['USER']);
    }

    #[Test]
    public function getCurrentUserIdReturnsUserIdWhenSet(): void
    {
        $GLOBALS['USER'] = (object) ['id' => 42];

        $this->assertSame(42, $this->context->getCurrentUserId());
    }

    #[Test]
    public function getCurrentUserIdReturnsNullWhenUserNotSet(): void
    {
        unset($GLOBALS['USER']);

        $this->assertNull($this->context->getCurrentUserId());
    }

    #[Test]
    public function getCurrentUserIdReturnsNullWhenUserIdIsZero(): void
    {
        $GLOBALS['USER'] = (object) ['id' => 0];

        $this->assertNull($this->context->getCurrentUserId());
    }

    #[Test]
    public function getCurrentUserIdReturnsNullWhenUserHasNoIdProperty(): void
    {
        $GLOBALS['USER'] = (object) [];

        $this->assertNull($this->context->getCurrentUserId());
    }

    #[Test]
    public function getCurrentUserIdCastsStringIdToInt(): void
    {
        $GLOBALS['USER'] = (object) ['id' => '123'];

        $this->assertSame(123, $this->context->getCurrentUserId());
    }

    #[Test]
    public function getCurrentUserIdReturnsNullForStringZero(): void
    {
        $GLOBALS['USER'] = (object) ['id' => '0'];

        $this->assertNull($this->context->getCurrentUserId());
    }

    #[Test]
    public function getCurrentUserIdReturnsCorrectIdForLargeValue(): void
    {
        $GLOBALS['USER'] = (object) ['id' => 999999];

        $this->assertSame(999999, $this->context->getCurrentUserId());
    }

    #[Test]
    public function isFinal(): void
    {
        $reflection = new ReflectionClass(MoodleUserContext::class);
        $this->assertTrue($reflection->isFinal());
    }
}
