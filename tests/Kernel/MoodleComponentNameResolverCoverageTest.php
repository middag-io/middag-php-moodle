<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Kernel;

use Middag\Framework\Kernel\Contract\ComponentNameResolverInterface;
use Middag\Moodle\Config\ComponentContext;
use Middag\Moodle\Kernel\MoodleComponentNameResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * MoodleComponentNameResolver is a one-line delegation: nativeComponent() returns
 * the frankenstyle component the product wired via ComponentContext::configure().
 * The resolver holds no state of its own, so the tests drive the seam and assert
 * the resolver mirrors whatever the composition root configured — never a
 * hard-coded literal.
 *
 * @internal
 */
#[CoversClass(MoodleComponentNameResolver::class)]
final class MoodleComponentNameResolverCoverageTest extends TestCase
{
    protected function setUp(): void
    {
        // The test bootstrap configures the component seam; re-assert for isolation.
        ComponentContext::configure('local_example', 'local_example_autoload');
    }

    protected function tearDown(): void
    {
        // Restore the bootstrap default so sibling suites relying on the seam
        // (and this class's own instantiation) are not affected by reconfigures.
        ComponentContext::configure('local_example', 'local_example_autoload');
    }

    #[Test]
    public function implementsTheFrameworkContract(): void
    {
        self::assertInstanceOf(
            ComponentNameResolverInterface::class,
            new MoodleComponentNameResolver(),
        );
    }

    #[Test]
    public function nativeComponentReturnsTheConfiguredComponent(): void
    {
        $resolver = new MoodleComponentNameResolver();

        self::assertSame('local_example', $resolver->nativeComponent());
    }

    #[Test]
    public function nativeComponentDelegatesToComponentContextRatherThanAHardCodedLiteral(): void
    {
        // Reconfigure the seam to a distinct component; the resolver must reflect
        // it, proving it reads ComponentContext::name() live on every call.
        ComponentContext::configure('mod_widget', 'mod_widget_autoload');

        $resolver = new MoodleComponentNameResolver();

        self::assertSame('mod_widget', $resolver->nativeComponent());
    }
}
