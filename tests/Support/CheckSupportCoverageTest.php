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

use core\check\result;
use core\url as moodle_url;
use Middag\Moodle\Domain\Platform\CheckResultDto;
use Middag\Moodle\Domain\Platform\CheckResultStatus;
use Middag\Moodle\Support\CheckSupport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * A \core\check\result-shaped double the check fixtures return.
 *
 * @internal
 */
final readonly class CheckSupportResultFixture
{
    public function __construct(
        private string $status,
    ) {}

    public function get_status(): string
    {
        return $this->status;
    }

    public function get_summary(): string
    {
        return 'summary text';
    }

    public function get_details(): string
    {
        return 'details text';
    }
}

/**
 * A \core\output\action_link-shaped double exposing a public $url (moodle_url),
 * as the real action_link does. In Moodle 5.0 the action link lives on the
 * CHECK (core\check\check::get_action_link()), not on the result.
 *
 * @internal
 */
final class CheckSupportActionLinkFixture
{
    public function __construct(public moodle_url $url) {}
}

/**
 * A \core\check\check-shaped double exposing an action link.
 *
 * @internal
 */
final class CheckSupportOkCheckFixture
{
    public function get_id(): string
    {
        return 'okcheck';
    }

    public function get_name(): string
    {
        return 'OK Check';
    }

    public function get_result(): CheckSupportResultFixture
    {
        return new CheckSupportResultFixture(result::OK);
    }

    public function get_action_link(): CheckSupportActionLinkFixture
    {
        return new CheckSupportActionLinkFixture(new moodle_url('https://moodle.test/fix'));
    }
}

/**
 * A check double whose result has no action link.
 *
 * @internal
 */
final class CheckSupportNoLinkCheckFixture
{
    public function get_id(): string
    {
        return 'nolink';
    }

    public function get_name(): string
    {
        return 'No Link Check';
    }

    public function get_result(): CheckSupportResultFixture
    {
        return new CheckSupportResultFixture(result::WARNING);
    }

    public function get_action_link(): ?CheckSupportActionLinkFixture
    {
        return null;
    }
}

/**
 * A check double whose result resolution throws, driving runCheck()'s catch.
 *
 * @internal
 */
final class CheckSupportThrowingCheckFixture
{
    public function get_id(): string
    {
        return 'boom';
    }

    public function get_name(): string
    {
        return 'Boom';
    }

    public function get_result(): CheckSupportResultFixture
    {
        throw new RuntimeException('check failed');
    }
}

/**
 * @internal
 */
#[CoversClass(CheckSupport::class)]
final class CheckSupportCoverageTest extends TestCase
{
    protected function setUp(): void
    {
        unset($GLOBALS['__middag_test_checks'], $GLOBALS['__middag_test_throw_get_checks']);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['__middag_test_checks'], $GLOBALS['__middag_test_throw_get_checks']);
    }

    #[Test]
    public function testGetResultStatusLabelMapsEveryStatusConstant(): void
    {
        self::assertSame('ok', CheckSupport::getResultStatusLabel(result::OK));
        self::assertSame('info', CheckSupport::getResultStatusLabel(result::INFO));
        self::assertSame('warning', CheckSupport::getResultStatusLabel(result::WARNING));
        self::assertSame('error', CheckSupport::getResultStatusLabel(result::ERROR));
        self::assertSame('critical', CheckSupport::getResultStatusLabel(result::CRITICAL));
        self::assertSame('na', CheckSupport::getResultStatusLabel(result::NA));
        self::assertSame('unknown', CheckSupport::getResultStatusLabel('some-other-status'));
    }

    #[Test]
    public function testRunCheckReturnsAResultArrayWithAnActionLink(): void
    {
        $result = CheckSupport::runCheck(CheckSupportOkCheckFixture::class);

        self::assertSame('okcheck', $result['id']);
        self::assertSame('OK Check', $result['name']);
        self::assertSame('ok', $result['status']);
        self::assertSame('summary text', $result['summary']);
        self::assertSame('details text', $result['details']);
        self::assertSame('https://moodle.test/fix', $result['action_link']);
    }

    #[Test]
    public function testRunCheckHandlesANullActionLink(): void
    {
        $result = CheckSupport::runCheck(CheckSupportNoLinkCheckFixture::class);

        self::assertNull($result['action_link']);
        self::assertSame('warning', $result['status']);
    }

    #[Test]
    public function testRunCheckReturnsNullWhenTheCheckThrows(): void
    {
        self::assertNull(CheckSupport::runCheck(CheckSupportThrowingCheckFixture::class));
    }

    #[Test]
    public function testGetChecksReturnsTheRegisteredChecks(): void
    {
        // check_manager::get_checks() yields check OBJECTS, not arrays.
        $checks = [new CheckSupportOkCheckFixture(), new CheckSupportNoLinkCheckFixture()];
        $GLOBALS['__middag_test_checks'] = $checks;

        self::assertSame($checks, CheckSupport::getChecks());
    }

    #[Test]
    public function testGetChecksReturnsEmptyArrayWhenTheManagerThrows(): void
    {
        $GLOBALS['__middag_test_throw_get_checks'] = true;

        self::assertSame([], CheckSupport::getChecks('security'));
    }

    #[Test]
    public function testGetCheckResultsBuildsTypedDtosFromCheckObjects(): void
    {
        // Feed check OBJECTS (the real check_manager contract). getCheckResults
        // must read them through accessors and resolve the status enum.
        $GLOBALS['__middag_test_checks'] = [
            new CheckSupportOkCheckFixture(),
            new CheckSupportNoLinkCheckFixture(),
        ];

        $results = CheckSupport::getCheckResults();

        self::assertInstanceOf(CheckResultDto::class, $results['okcheck']);
        self::assertSame('okcheck', $results['okcheck']->checkId);
        self::assertSame(CheckResultStatus::OK, $results['okcheck']->status);
        self::assertSame('summary text', $results['okcheck']->summary);
        self::assertSame('details text', $results['okcheck']->details);
        self::assertSame(CheckResultStatus::WARNING, $results['nolink']->status);
    }
}
