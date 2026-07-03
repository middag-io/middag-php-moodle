<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Translation;

use Middag\Framework\Translation\Contract\TranslatorInterface;
use Middag\Moodle\Translation\MoodleTranslator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

/**
 * @internal
 */
#[CoversClass(MoodleTranslator::class)]
final class MoodleTranslatorTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['__middag_test_get_string'], $GLOBALS['__middag_test_string_exists']);
    }

    #[Test]
    public function implementsTheFrameworkTranslatorContract(): void
    {
        self::assertInstanceOf(TranslatorInterface::class, new MoodleTranslator());
    }

    #[Test]
    public function getFallsBackToTheConfiguredComponent(): void
    {
        $seen = [];
        $GLOBALS['__middag_test_get_string'] = static function (string $identifier, string $component, mixed $a) use (&$seen): string {
            $seen = [$identifier, $component, $a];

            return 'Hello';
        };

        $result = (new MoodleTranslator())->get('greeting');

        self::assertSame('Hello', $result);
        // tests/bootstrap.php configures ComponentContext with 'local_example'.
        self::assertSame(['greeting', 'local_example', null], $seen);
    }

    #[Test]
    public function getPassesAnExplicitComponentThrough(): void
    {
        $seen = [];
        $GLOBALS['__middag_test_get_string'] = static function (string $identifier, string $component, mixed $a) use (&$seen): string {
            $seen = [$identifier, $component, $a];

            return 'Oi';
        };

        (new MoodleTranslator())->get('greeting', 'mod_unidade');

        self::assertSame(['greeting', 'mod_unidade', null], $seen);
    }

    #[Test]
    public function getMapsParamsOntoThePlaceholderObjectStrippingPercentDelimiters(): void
    {
        $captured = null;
        $GLOBALS['__middag_test_get_string'] = static function (string $identifier, string $component, mixed $a) use (&$captured): string {
            $captured = $a;

            return 'ok';
        };

        (new MoodleTranslator())->get('items', 'local_example', [
            '%count%' => 3,
            'name' => 'Ana',
        ]);

        self::assertInstanceOf(stdClass::class, $captured);
        self::assertSame(3, $captured->count);
        self::assertSame('Ana', $captured->name);
    }

    #[Test]
    public function hasReportsExistenceAndFallsBackToTheConfiguredComponent(): void
    {
        $seen = [];
        $GLOBALS['__middag_test_string_exists'] = static function (string $identifier, string $component) use (&$seen): bool {
            $seen[] = [$identifier, $component];

            return $identifier === 'known';
        };

        $translator = new MoodleTranslator();

        self::assertTrue($translator->has('known'));
        self::assertFalse($translator->has('unknown', 'mod_unidade'));
        self::assertSame([['known', 'local_example'], ['unknown', 'mod_unidade']], $seen);
    }

    #[Test]
    public function getPropagatesHostFailures(): void
    {
        $GLOBALS['__middag_test_get_string'] = static function (): string {
            throw new RuntimeException('Invalid get_string() identifier');
        };

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid get_string() identifier');

        (new MoodleTranslator())->get('missing');
    }

    #[Test]
    public function hasPropagatesHostFailures(): void
    {
        $GLOBALS['__middag_test_string_exists'] = static function (): bool {
            throw new RuntimeException('string manager unavailable');
        };

        $this->expectException(RuntimeException::class);

        (new MoodleTranslator())->has('anything');
    }
}
