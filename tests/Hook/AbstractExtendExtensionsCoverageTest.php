<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Hook;

use core\hook\described_hook;
use Middag\Moodle\Hook\AbstractExtendExtensions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Test AbstractExtendExtensions.
 *
 * The class is abstract and implements core\hook\described_hook; a concrete
 * anonymous subclass exercises the definition accumulator plus the static hook
 * metadata. The interface's snake_case accessors are supplied by the subclass
 * (the abstract parent leaves them unimplemented, providing camelCase helpers
 * instead).
 *
 * @internal
 */
#[CoversClass(AbstractExtendExtensions::class)]
final class AbstractExtendExtensionsCoverageTest extends TestCase
{
    #[Test]
    public function constructorSeedsInitialDefinitions(): void
    {
        $seed = [$this->def('a'), $this->def('b')];

        $hook = $this->makeHook($seed);

        $this->assertSame($seed, $hook->getDefinitions());
    }

    #[Test]
    public function getDefinitionsIsEmptyByDefault(): void
    {
        $this->assertSame([], $this->makeHook()->getDefinitions());
    }

    #[Test]
    public function addExtensionsAppendsToExistingDefinitions(): void
    {
        $hook = $this->makeHook([$this->def('seed')]);

        $hook->addExtensions([$this->def('x'), $this->def('y')]);

        $slugs = array_column($hook->getDefinitions(), 'slug');
        $this->assertSame(['seed', 'x', 'y'], $slugs);
    }

    #[Test]
    public function addExtensionsWithEmptyArrayIsNoOp(): void
    {
        $hook = $this->makeHook([$this->def('only')]);

        $hook->addExtensions([]);

        $this->assertSame(['only'], array_column($hook->getDefinitions(), 'slug'));
    }

    #[Test]
    public function hookDescriptionIsNonEmptyString(): void
    {
        $this->assertStringContainsString('MIDDAG', AbstractExtendExtensions::getHookDescription());
    }

    #[Test]
    public function hookTagsIncludeMiddagAndExtensions(): void
    {
        $this->assertSame(['middag', 'extensions'], AbstractExtendExtensions::getHookTags());
    }

    #[Test]
    public function implementsDescribedHook(): void
    {
        $this->assertInstanceOf(described_hook::class, $this->makeHook());
    }

    /**
     * @param array<int, array{class: string, slug: string, group: string, priority: int, hidden: bool}> $definitions
     */
    private function makeHook(array $definitions = []): AbstractExtendExtensions
    {
        return new class($definitions) extends AbstractExtendExtensions {
            public static function get_hook_description(): string
            {
                return self::getHookDescription();
            }

            /** @return string[] */
            public static function get_hook_tags(): array
            {
                return self::getHookTags();
            }
        };
    }

    /**
     * @return array{class: string, slug: string, group: string, priority: int, hidden: bool}
     */
    private function def(string $slug): array
    {
        return ['class' => 'Ext\\' . $slug, 'slug' => $slug, 'group' => 'g', 'priority' => 10, 'hidden' => false];
    }
}
