<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Domain\Context;

use Middag\Moodle\Domain\Context\Context;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Context is a native Moodle entity mapping mdl_context. Its own executable
 * surface is the getTable() mapping plus the fromContext() factory; the
 * accessor/mutator behaviour is inherited from AbstractMoodleEntity.
 *
 * fromContext() delegates to AbstractMoodleEntity::fromRecord(). The bootstrap
 * stub core\context extends stdClass (faithful to real Moodle, where
 * `abstract class core\context extends stdClass`), so a genuine context object
 * satisfies fromRecord()'s array|stdClass contract and the factory is exercised
 * end to end here.
 *
 * @internal
 */
#[CoversClass(Context::class)]
final class ContextCoverageTest extends TestCase
{
    #[Test]
    public function fromContextHydratesFromAMoodleContextObject(): void
    {
        $moodleContext = new \core\context(9);
        $moodleContext->contextlevel = 50;
        $moodleContext->instanceid = 12;
        $moodleContext->path = '/1/9';
        $moodleContext->depth = 2;
        $moodleContext->locked = 1;

        $context = Context::fromContext($moodleContext);

        self::assertInstanceOf(Context::class, $context);
        self::assertSame(9, $context->getId());
        self::assertSame(50, $context->get_contextlevel());
        self::assertSame(12, $context->get_instanceid());
        self::assertSame('/1/9', $context->get_path());
        self::assertSame(2, $context->get_depth());
        self::assertSame(1, $context->get_locked());
    }

    #[Test]
    public function getTableMapsToContext(): void
    {
        self::assertSame('context', Context::getTable());
    }

    #[Test]
    public function propertyDefaultsMatchMoodleSchema(): void
    {
        $context = new Context();

        self::assertSame(0, $context->get_contextlevel());
        self::assertSame(0, $context->get_instanceid());
        self::assertNull($context->get_path());
        self::assertSame(0, $context->get_depth());
        self::assertSame(0, $context->get_locked());
    }

    #[Test]
    public function fromRecordHydratesContextSpecificFields(): void
    {
        $context = Context::fromRecord([
            'id' => '9',
            'contextlevel' => '50',
            'instanceid' => '12',
            'path' => '/1/9',
            'depth' => '2',
            'locked' => '1',
        ]);

        self::assertInstanceOf(Context::class, $context);
        self::assertSame(9, $context->getId());
        self::assertSame(50, $context->get_contextlevel());
        self::assertSame(12, $context->get_instanceid());
        self::assertSame('/1/9', $context->get_path());
        self::assertSame(2, $context->get_depth());
        self::assertSame(1, $context->get_locked());
    }
}
