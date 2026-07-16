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

use Middag\Moodle\Support\CrudConventionResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(CrudConventionResolver::class)]
final class CrudConventionResolverTest extends TestCase
{
    #[Test]
    public function testSlugPluralizesAndLowercases(): void
    {
        self::assertSame('invoices', CrudConventionResolver::slug('App\Entity\Invoice'));
        self::assertSame('orders', CrudConventionResolver::slug('Order'));
    }

    #[Test]
    public function testTitleUcfirstsSlug(): void
    {
        self::assertSame('Invoices', CrudConventionResolver::title('App\Entity\Invoice'));
    }

    #[Test]
    public function testSingularReturnsUcfirstBasename(): void
    {
        self::assertSame('Invoice', CrudConventionResolver::singular('App\Entity\Invoice'));
    }

    #[Test]
    public function testColumnsReturnsEmptyForMissingClass(): void
    {
        self::assertSame([], CrudConventionResolver::columns('Nonexistent\Entity'));
    }

    #[Test]
    public function testColumnsExcludesConventionalHiddenFields(): void
    {
        $columns = CrudConventionResolver::columns(CrudConventionResolverTestEntity::class);

        self::assertContains('name', $columns);
        self::assertContains('amount', $columns);
        self::assertNotContains('id', $columns);
        self::assertNotContains('timecreated', $columns);
        self::assertNotContains('timemodified', $columns);
        self::assertNotContains('usermodified', $columns);
    }

    #[Test]
    public function testFormClassReturnsNullWhenNoExtensionsSegment(): void
    {
        self::assertNull(CrudConventionResolver::formClass('App\Entity\Invoice'));
    }

    #[Test]
    public function testFormClassResolvesConventionalFormClassWhenItExists(): void
    {
        // Entity under an `extensions` segment: the resolver assembles the
        // {Extension}\forms\{Basename}_form candidate and returns it when present.
        $candidate = 'local_example\extensions\invoicing\forms\Invoice_form';
        if (!class_exists($candidate, false)) {
            class_alias(CrudConventionResolverFormStub::class, $candidate);
        }

        self::assertSame(
            $candidate,
            CrudConventionResolver::formClass('local_example\extensions\invoicing\Invoice'),
        );
    }

    #[Test]
    public function testFormClassReturnsNullWhenConventionalFormClassMissing(): void
    {
        // `extensions` segment present, so the candidate is assembled, but no such
        // form class exists → the class_exists ternary yields null.
        self::assertNull(
            CrudConventionResolver::formClass('local_example\extensions\billing\Ghost'),
        );
    }

    #[Test]
    public function testCapabilityFollowsHostComponentConvention(): void
    {
        self::assertSame('local/example:manage_invoice', CrudConventionResolver::capability('App\Entity\Invoice'));
    }

    #[Test]
    public function testCapabilityKeepsTrailingSForNativelyPluralBasenames(): void
    {
        // Entities whose basename natively ends in 's' (Status, Address) must
        // not be over-stripped to manage_statu / manage_addre.
        self::assertSame('local/example:manage_status', CrudConventionResolver::capability('App\Entity\Status'));
        self::assertSame('local/example:manage_address', CrudConventionResolver::capability('App\Entity\Address'));
    }

    #[Test]
    public function testRoutePrefixEqualsSlug(): void
    {
        self::assertSame('invoices', CrudConventionResolver::routePrefix('App\Entity\Invoice'));
    }
}

/**
 * Synthetic entity for column discovery test.
 */
final class CrudConventionResolverTestEntity
{
    public int $id = 0;

    public string $name = '';

    public float $amount = 0.0;

    public int $timecreated = 0;

    public int $timemodified = 0;

    public int $usermodified = 0;
}

/**
 * Synthetic form class aliased onto the conventional form FQCN so
 * formClass()'s class_exists() lookup resolves to it.
 */
final class CrudConventionResolverFormStub {}
