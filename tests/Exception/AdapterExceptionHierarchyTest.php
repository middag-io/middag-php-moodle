<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Exception;

use InvalidArgumentException;
use LogicException;
use Middag\Framework\Exception\MiddagException;
use Middag\Framework\Exception\MiddagInfrastructureException;
use Middag\Moodle\Exception\MoodleAdapterException;
use Middag\Moodle\Exception\MoodleConfigurationException;
use Middag\Moodle\Exception\MoodleTransportException;
use Middag\Moodle\Exception\MoodleVersionException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The adapter's exceptions belong to the framework exception family, so the
 * kernel reads their HTTP status instead of mapping a foreign throwable.
 *
 * @internal
 */
#[CoversClass(MoodleAdapterException::class)]
#[CoversClass(MoodleConfigurationException::class)]
#[CoversClass(MoodleTransportException::class)]
#[CoversClass(MoodleVersionException::class)]
final class AdapterExceptionHierarchyTest extends TestCase
{
    /**
     * @param class-string<MiddagException> $class
     */
    #[DataProvider('adapterExceptions')]
    public function testExtendsTheFrameworkInfrastructureBranch(string $class): void
    {
        $exception = new $class('host failure');

        self::assertInstanceOf(MiddagInfrastructureException::class, $exception);
        self::assertSame(500, $exception->getStatusCode());
    }

    /**
     * @param class-string<MiddagException> $class
     */
    #[DataProvider('adapterExceptions')]
    public function testLeavesTheSplLogicBranches(string $class): void
    {
        $exception = new $class('host failure');

        self::assertNotInstanceOf(LogicException::class, $exception);
        self::assertNotInstanceOf(InvalidArgumentException::class, $exception);
    }

    /**
     * @return iterable<string, array{class-string<MiddagException>}>
     */
    public static function adapterExceptions(): iterable
    {
        yield 'adapter' => [MoodleAdapterException::class];

        yield 'configuration' => [MoodleConfigurationException::class];

        yield 'transport' => [MoodleTransportException::class];

        yield 'version' => [MoodleVersionException::class];
    }
}
