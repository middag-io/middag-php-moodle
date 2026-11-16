<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Integration\Engine;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Keeps the cross-engine probe in step with DbSupport without a database: a
 * new public static sql* method fails here until the probe has a check for it.
 * The probe enforces the same gate again when it runs inside Moodle.
 *
 * @internal
 */
#[CoversNothing]
final class DbSupportEngineProbeCoverageTest extends TestCase
{
    #[Test]
    public function probeCoversEveryPublicSqlMethod(): void
    {
        $probe = new DbSupportEngineProbe();

        self::assertSame([], $probe->coverageErrors());
        self::assertSame(DbSupportEngineProbe::publicSqlMethods(), $probe->coveredMethods());
    }
}
