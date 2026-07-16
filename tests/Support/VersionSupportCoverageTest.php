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

use InvalidArgumentException;
use Middag\Moodle\Domain\Platform\MoodleVersion;
use Middag\Moodle\Exception\MoodleVersionException;
use Middag\Moodle\Support\VersionSupport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

/**
 * VersionSupport caches version data in private static properties after the
 * first {@see VersionSupport::bootstrap()} call, so every test resets that cache
 * via reflection and drives the version through the global $CFG. symbolExists()
 * is covered by {@see VersionSupportSymbolTest} and is intentionally not retested
 * here.
 *
 * @internal
 */
#[CoversClass(VersionSupport::class)]
final class VersionSupportCoverageTest extends TestCase
{
    private mixed $prevCfg;

    protected function setUp(): void
    {
        $this->prevCfg = $GLOBALS['CFG'] ?? null;

        // Default running version: branch 405 -> "4.5.0".
        $GLOBALS['CFG'] = (object) [
            'branch' => '405',
            'version' => 2024100100,
            'release' => '4.5+ (Build: 20241001)',
        ];

        $this->resetVersionCache();
    }

    protected function tearDown(): void
    {
        $GLOBALS['CFG'] = $this->prevCfg;

        $this->resetVersionCache();
    }

    #[Test]
    public function testVersionSemverDerivesTheNormalizedVersionFromBranch(): void
    {
        self::assertSame('4.5.0', VersionSupport::versionSemver());
    }

    #[Test]
    public function testVersionReturnsATypedValueObject(): void
    {
        $version = VersionSupport::version();

        self::assertInstanceOf(MoodleVersion::class, $version);
        self::assertSame(4, $version->major);
        self::assertSame(5, $version->minor);
        self::assertSame('4.5.0', (string) $version);
    }

    #[Test]
    public function testBranchReturnsTheNumericBranch(): void
    {
        self::assertSame(405, VersionSupport::branch());
    }

    #[Test]
    public function testBuildReturnsTheNumericBuild(): void
    {
        self::assertSame(2024100100, VersionSupport::build());
    }

    #[Test]
    public function testMajorMinorReturnsTheParsedPair(): void
    {
        self::assertSame([4, 5], VersionSupport::majorMinor());
    }

    #[Test]
    public function testCompareSatisfiesGreaterOrEqual(): void
    {
        self::assertTrue(VersionSupport::compare('>=', '4.0'));
        self::assertFalse(VersionSupport::compare('>=', '5.0'));
    }

    #[Test]
    public function testCompareNormalizesTwoPartConstraint(): void
    {
        // "4.5" is normalized to "4.5.0" before the version_compare().
        self::assertTrue(VersionSupport::compare('==', '4.5'));
    }

    #[Test]
    public function testCompareThrowsOnAnInvalidConstraint(): void
    {
        $this->expectException(InvalidArgumentException::class);

        VersionSupport::compare('>=', 'not-a-version');
    }

    #[Test]
    public function testAtLeastAcceptsAStringConstraint(): void
    {
        self::assertTrue(VersionSupport::atLeast('4.0'));
        self::assertFalse(VersionSupport::atLeast('9.0'));
    }

    #[Test]
    public function testAtLeastAcceptsAMoodleVersionObject(): void
    {
        self::assertTrue(VersionSupport::atLeast(new MoodleVersion(4, 0)));
    }

    #[Test]
    public function testBetweenWithStringBounds(): void
    {
        self::assertTrue(VersionSupport::between('4.0', '5.0'));
        self::assertFalse(VersionSupport::between('4.0', '4.4'));
    }

    #[Test]
    public function testBetweenWithMoodleVersionBounds(): void
    {
        self::assertTrue(VersionSupport::between(new MoodleVersion(4, 0), new MoodleVersion(5, 0)));
    }

    #[Test]
    public function testAssertMinPassesWhenTheVersionIsMet(): void
    {
        $thrown = false;

        try {
            VersionSupport::assertMin('4.0');
        } catch (RuntimeException) {
            $thrown = true;
        }

        self::assertFalse($thrown);
    }

    #[Test]
    public function testAssertMinThrowsWithADefaultLocalizedMessage(): void
    {
        // No $msg supplied -> the wrapper resolves the message via LangSupport.
        $this->expectException(RuntimeException::class);

        VersionSupport::assertMin('9.0');
    }

    #[Test]
    public function testAssertMinThrowsWithACustomMessage(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('custom failure');

        VersionSupport::assertMin('9.0', 'custom failure');
    }

    #[Test]
    public function testSupportsReturnsFalseForAnUnknownFeature(): void
    {
        self::assertFalse(VersionSupport::supports('missing', []));
    }

    #[Test]
    public function testSupportsHonorsSinceBounds(): void
    {
        self::assertTrue(VersionSupport::supports('f', ['f' => ['since' => '4.0']]));
        self::assertFalse(VersionSupport::supports('f', ['f' => ['since' => '9.0']]));
    }

    #[Test]
    public function testSupportsHonorsUntilBounds(): void
    {
        self::assertTrue(VersionSupport::supports('f', ['f' => ['until' => '5.0']]));
        self::assertFalse(VersionSupport::supports('f', ['f' => ['until' => '4.0']]));
    }

    #[Test]
    public function testSupportsTreatsAnEmptyRuleAsAlwaysSupported(): void
    {
        self::assertTrue(VersionSupport::supports('f', ['f' => []]));
    }

    #[Test]
    public function testBootstrapDerivesBranchAndSemverFromTheRelease(): void
    {
        $GLOBALS['CFG'] = (object) ['release' => 'Moodle 4.3.2 (Build: 20230101)'];
        $this->resetVersionCache();

        self::assertSame(403, VersionSupport::branch());
        self::assertSame('4.3.2', VersionSupport::versionSemver());
        self::assertSame(0, VersionSupport::build());
    }

    #[Test]
    public function testBootstrapAdoptsTheReleasePatchWhenBranchIsPresent(): void
    {
        // The exact shape every real host presents (SUPPORT-HOST-EVIDENCE.md):
        // branch supplies major/minor, release carries the real patch digit.
        // Collapsing to 5.0.0 froze atLeast()/supports() gates at x.y.0.
        $GLOBALS['CFG'] = (object) [
            'branch' => '500',
            'version' => 2025041400,
            'release' => '5.0.7 (Build: 20260420)',
        ];
        $this->resetVersionCache();

        self::assertSame('5.0.7', VersionSupport::versionSemver());
        self::assertTrue(VersionSupport::compare('>=', '5.0.3'));
        self::assertFalse(VersionSupport::supports('f', ['f' => ['until' => '5.0.2']]));
    }

    #[Test]
    public function testBootstrapIgnoresAReleasePatchFromAMismatchedVersion(): void
    {
        // A release that disagrees with branch on major.minor must not
        // contribute its patch digit to the branch-derived version.
        $GLOBALS['CFG'] = (object) ['branch' => '500', 'release' => '4.9.9 (Build: 20250101)'];
        $this->resetVersionCache();

        self::assertSame('5.0.0', VersionSupport::versionSemver());
    }

    #[Test]
    public function testCompareThrowsTheTypedExceptionForAnUnknownOperator(): void
    {
        // PHP 8 raises a raw ValueError for a bad operator; the documented
        // failure mode is MoodleVersionException, matching bad constraints.
        $this->expectException(MoodleVersionException::class);

        VersionSupport::compare('=>', '5.0');
    }

    #[Test]
    public function testBootstrapParsesSemverFromReleaseWhenBranchResolvesToZero(): void
    {
        // Branch "00" is all-digits yet casts to 0, forcing the release-parse path.
        $GLOBALS['CFG'] = (object) ['branch' => '00', 'release' => '4.5.1 (Build: 20240101)'];
        $this->resetVersionCache();

        self::assertSame(0, VersionSupport::branch());
        self::assertSame('4.5.1', VersionSupport::versionSemver());
    }

    #[Test]
    public function testBootstrapFallsBackToZeroVersionWhenNothingParses(): void
    {
        $GLOBALS['CFG'] = (object) ['release' => 'no-version-string-here'];
        $this->resetVersionCache();

        self::assertSame(0, VersionSupport::branch());
        self::assertSame('0.0.0', VersionSupport::versionSemver());
        self::assertSame([0, 0], VersionSupport::majorMinor());
    }

    private function resetVersionCache(): void
    {
        $reflection = new ReflectionClass(VersionSupport::class);
        $reflection->getProperty('bootstrapped')->setValue(null, false);
        $reflection->getProperty('semantic')->setValue(null, '');
        $reflection->getProperty('branch')->setValue(null, 0);
        $reflection->getProperty('build')->setValue(null, 0);
    }
}
