<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Kernel\WebService;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use Middag\Moodle\Kernel\WebService\AbstractExternal;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Proves the AbstractExternal marker is usable: a concrete external function can
 * extend it and declare parameter/return structures + an execute() body against
 * the core_external API, with no MIDDAG helper layer (THIN, MOODLE-02).
 *
 * Exercises the contract shape only — NOT Moodle's validation engine, which needs
 * a booted Moodle. Structure classes come from tests/stubs/external-api-stubs.php.
 *
 * @internal
 */
#[CoversClass(AbstractExternal::class)]
final class AbstractExternalFixtureTest extends TestCase
{
    #[Test]
    public function fixtureExtendsAbstractExternalAndExternalApi(): void
    {
        self::assertTrue(is_subclass_of(SumExternalFixture::class, AbstractExternal::class));
        self::assertTrue(is_subclass_of(SumExternalFixture::class, external_api::class));
    }

    #[Test]
    public function declaresParameterStructure(): void
    {
        // The `: external_function_parameters` return type already pins the type;
        // these assertions test the fixture-authored shape inside it.
        $params = SumExternalFixture::execute_parameters();

        self::assertArrayHasKey('a', $params->keys);
        self::assertArrayHasKey('b', $params->keys);
        self::assertInstanceOf(external_value::class, $params->keys['a']);
        self::assertSame(PARAM_INT, $params->keys['a']->type);
    }

    #[Test]
    public function declaresReturnStructure(): void
    {
        $returns = SumExternalFixture::execute_returns();

        self::assertArrayHasKey('sum', $returns->keys);
        self::assertInstanceOf(external_value::class, $returns->keys['sum']);
        self::assertSame(PARAM_INT, $returns->keys['sum']->type);
    }

    #[Test]
    public function executeReturnsExpectedArray(): void
    {
        self::assertSame(['sum' => 5], SumExternalFixture::execute(2, 3));
        self::assertSame(['sum' => 0], SumExternalFixture::execute(-4, 4));
    }

    #[Test]
    public function executeResultSerializesAgainstReturnStructure(): void
    {
        $returns = SumExternalFixture::execute_returns();

        // Round-trip the execute() output through the declared return structure.
        $clean = external_api::clean_returnvalue($returns, SumExternalFixture::execute(2, 3));
        self::assertSame(['sum' => 5], $clean);

        // And prove the structure coerces a raw scalar to its PARAM_INT type.
        $coerced = external_api::clean_returnvalue($returns, ['sum' => '5']);
        self::assertSame(['sum' => 5], $coerced);
    }
}

/**
 * Minimal concrete external function used only by the test above. Mirrors the
 * canonical Moodle external-function shape (static execute_parameters/execute/
 * execute_returns) without depending on any MIDDAG helper.
 *
 * @internal
 */
final class SumExternalFixture extends AbstractExternal
{
    public static function execute_parameters(): external_function_parameters
    {
        return new external_function_parameters([
            'a' => new external_value(PARAM_INT, 'first addend'),
            'b' => new external_value(PARAM_INT, 'second addend'),
        ]);
    }

    public static function execute(int $a, int $b): array
    {
        return ['sum' => $a + $b];
    }

    public static function execute_returns(): external_single_structure
    {
        return new external_single_structure([
            'sum' => new external_value(PARAM_INT, 'the sum of a and b'),
        ]);
    }
}
