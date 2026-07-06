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

use Middag\Moodle\Support\RequestSupport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(RequestSupport::class)]
final class RequestSupportCoverageTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['__middag_test_params'] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['__middag_test_params']);
    }

    #[Test]
    public function testOptionalParamReturnsTheValueWhenPresent(): void
    {
        $GLOBALS['__middag_test_params']['page'] = 3;

        self::assertSame(3, RequestSupport::optionalParam('page', 0, 'PARAM_INT'));
    }

    #[Test]
    public function testOptionalParamReturnsTheDefaultWhenAbsent(): void
    {
        self::assertSame(0, RequestSupport::optionalParam('page', 0, 'PARAM_INT'));
    }

    #[Test]
    public function testRequiredParamReturnsThePresentValue(): void
    {
        $GLOBALS['__middag_test_params']['id'] = 42;

        self::assertSame(42, RequestSupport::requiredParam('id', 'PARAM_INT'));
    }

    #[Test]
    public function testValidateEmailAcceptsAWellFormedAddress(): void
    {
        self::assertTrue(RequestSupport::validateEmail('user@example.com'));
    }

    #[Test]
    public function testValidateEmailRejectsAMalformedAddress(): void
    {
        self::assertFalse(RequestSupport::validateEmail('not-an-email'));
    }
}
