<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Definition;

use Middag\Moodle\Definition\CapabilityDefinition;
use Middag\Moodle\Definition\Contract\DefinitionInterface;
use Middag\Moodle\Domain\Context\ContextLevel;
use Middag\Moodle\Security\Enum\CapabilityRisk;
use Middag\Moodle\Security\Enum\CapabilityType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

// Define the CAP_ALLOW constant if not already defined (Moodle constant)
if (!defined('CAP_ALLOW')) {
    define('CAP_ALLOW', 1);
}

/**
 * @internal
 */
#[CoversClass(CapabilityDefinition::class)]
final class CapabilityDefinitionTest extends TestCase
{
    #[Test]
    public function canBeConstructedWithNameOnly(): void
    {
        $cap = new CapabilityDefinition(name: 'manage');
        $this->assertSame('manage', $cap->name);
    }

    #[Test]
    public function hasCorrectDefaults(): void
    {
        $cap = new CapabilityDefinition(name: 'manage');
        $this->assertSame([], $cap->archetypes);
        $this->assertSame(CapabilityType::READ, $cap->type);
        $this->assertSame(ContextLevel::SYSTEM, $cap->context);
        $this->assertSame(CapabilityRisk::SPAM, $cap->risk);
        $this->assertNull($cap->clone_from);
        $this->assertNull($cap->min_moodle);
        $this->assertNull($cap->max_moodle);
    }

    #[Test]
    public function canBeConstructedWithAllArgs(): void
    {
        $cap = new CapabilityDefinition(
            name: 'manage',
            archetypes: ['manager', 'editingteacher'],
            type: CapabilityType::WRITE,
            context: ContextLevel::COURSE,
            risk: CapabilityRisk::CONFIG,
            clone_from: 'moodle/course:manage',
            min_moodle: '4.0',
            max_moodle: '4.5',
        );

        $this->assertSame('manage', $cap->name);
        $this->assertSame(['manager', 'editingteacher'], $cap->archetypes);
        $this->assertSame(CapabilityType::WRITE, $cap->type);
        $this->assertSame(ContextLevel::COURSE, $cap->context);
        $this->assertSame(CapabilityRisk::CONFIG, $cap->risk);
        $this->assertSame('moodle/course:manage', $cap->clone_from);
        $this->assertSame('4.0', $cap->min_moodle);
        $this->assertSame('4.5', $cap->max_moodle);
    }

    #[Test]
    public function implementsDefinitionInterface(): void
    {
        $cap = new CapabilityDefinition(name: 'manage');
        $this->assertInstanceOf(DefinitionInterface::class, $cap);
    }

    #[Test]
    public function getNameReturnsCapabilityName(): void
    {
        $cap = new CapabilityDefinition(name: 'manage');
        $this->assertSame('manage', $cap->getName());
    }

    #[Test]
    public function toMoodleArrayReturnsBasicStructure(): void
    {
        $cap = new CapabilityDefinition(
            name: 'view',
            type: CapabilityType::READ,
            context: ContextLevel::SYSTEM,
            risk: CapabilityRisk::SPAM,
        );

        $result = $cap->toMoodleArray('local_example');

        $this->assertSame(CapabilityRisk::SPAM->value, $result['riskbitmask']);
        $this->assertSame('read', $result['captype']);
        $this->assertSame(ContextLevel::SYSTEM->value, $result['contextlevel']);
        $this->assertSame([], $result['archetypes']);
    }

    #[Test]
    public function toMoodleArrayMapsArchetypesToCapAllow(): void
    {
        $cap = new CapabilityDefinition(
            name: 'manage',
            archetypes: ['manager', 'editingteacher'],
        );

        $result = $cap->toMoodleArray('local_example');

        $this->assertArrayHasKey('archetypes', $result);
        $this->assertSame(CAP_ALLOW, $result['archetypes']['manager']);
        $this->assertSame(CAP_ALLOW, $result['archetypes']['editingteacher']);
        $this->assertCount(2, $result['archetypes']);
    }

    #[Test]
    public function toMoodleArrayIncludesCloneFromWhenSet(): void
    {
        $cap = new CapabilityDefinition(
            name: 'manage',
            clone_from: 'moodle/course:manage',
        );

        $result = $cap->toMoodleArray('local_example');

        $this->assertArrayHasKey('clonepermissionsfrom', $result);
        $this->assertSame('moodle/course:manage', $result['clonepermissionsfrom']);
    }

    #[Test]
    public function toMoodleArrayExcludesCloneFromWhenNull(): void
    {
        $cap = new CapabilityDefinition(name: 'view');

        $result = $cap->toMoodleArray('local_example');

        $this->assertArrayNotHasKey('clonepermissionsfrom', $result);
    }

    #[Test]
    public function toMoodleArrayUsesWriteCaptype(): void
    {
        $cap = new CapabilityDefinition(
            name: 'edit',
            type: CapabilityType::WRITE,
        );

        $result = $cap->toMoodleArray('local_example');

        $this->assertSame('write', $result['captype']);
    }

    #[Test]
    public function toMoodleArrayUsesModuleContextLevel(): void
    {
        $cap = new CapabilityDefinition(
            name: 'view',
            context: ContextLevel::MODULE,
        );

        $result = $cap->toMoodleArray('local_example');

        $this->assertSame(70, $result['contextlevel']);
    }

    #[Test]
    public function getQualifiedNameWithoutExtension(): void
    {
        $cap = new CapabilityDefinition(name: 'manage');

        $qualified = $cap->get_qualified_name('local_example');
        $this->assertSame('local/example:manage', $qualified);
    }

    #[Test]
    public function getQualifiedNameWithCoreExtension(): void
    {
        $cap = new CapabilityDefinition(name: 'manage');

        $qualified = $cap->get_qualified_name('local_example', 'core');
        $this->assertSame('local/example:manage', $qualified);
    }

    #[Test]
    public function getQualifiedNameWithNullExtension(): void
    {
        $cap = new CapabilityDefinition(name: 'manage');

        $qualified = $cap->get_qualified_name('local_example');
        $this->assertSame('local/example:manage', $qualified);
    }

    #[Test]
    public function getQualifiedNameWithCustomExtension(): void
    {
        $cap = new CapabilityDefinition(name: 'manage');

        $qualified = $cap->get_qualified_name('local_example', 'myext');
        $this->assertSame('local/example:myext_manage', $qualified);
    }

    #[Test]
    public function getQualifiedNameWithModPlugin(): void
    {
        $cap = new CapabilityDefinition(name: 'addinstance');

        $qualified = $cap->get_qualified_name('mod_forum');
        $this->assertSame('mod/forum:addinstance', $qualified);
    }

    #[Test]
    public function isCompatibleReturnsTrueWithNoVersionConstraints(): void
    {
        $cap = new CapabilityDefinition(name: 'view');

        $this->assertTrue($cap->isCompatible('4.5'));
        $this->assertTrue($cap->isCompatible('3.0'));
    }

    #[Test]
    public function isCompatibleRespectsMinMoodle(): void
    {
        $cap = new CapabilityDefinition(name: 'view', min_moodle: '4.0');

        $this->assertTrue($cap->isCompatible('4.0'));
        $this->assertTrue($cap->isCompatible('4.5'));
        $this->assertFalse($cap->isCompatible('3.11'));
    }

    #[Test]
    public function isCompatibleRespectsMaxMoodle(): void
    {
        $cap = new CapabilityDefinition(name: 'view', max_moodle: '4.5');

        $this->assertTrue($cap->isCompatible('4.5'));
        $this->assertTrue($cap->isCompatible('4.0'));
        $this->assertFalse($cap->isCompatible('4.6'));
    }

    #[Test]
    public function isCompatibleRespectsMinAndMaxMoodle(): void
    {
        $cap = new CapabilityDefinition(name: 'view', min_moodle: '4.0', max_moodle: '4.5');

        $this->assertTrue($cap->isCompatible('4.0'));
        $this->assertTrue($cap->isCompatible('4.3'));
        $this->assertTrue($cap->isCompatible('4.5'));
        $this->assertFalse($cap->isCompatible('3.11'));
        $this->assertFalse($cap->isCompatible('4.6'));
    }

    #[Test]
    public function isReadonly(): void
    {
        $reflection = new ReflectionClass(CapabilityDefinition::class);
        $this->assertTrue($reflection->isReadOnly());
    }

    #[Test]
    public function isFinal(): void
    {
        $reflection = new ReflectionClass(CapabilityDefinition::class);
        $this->assertTrue($reflection->isFinal());
    }
}
