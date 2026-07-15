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

use InvalidArgumentException;
use Middag\Moodle\Definition\Contract\DefinitionInterface;
use Middag\Moodle\Definition\LangDefinition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(LangDefinition::class)]
final class LangDefinitionTest extends TestCase
{
    #[Test]
    public function implementsDefinitionInterface(): void
    {
        $def = new LangDefinition(key: 'pluginname', strings: ['en' => 'Example']);
        $this->assertInstanceOf(DefinitionInterface::class, $def);
    }

    #[Test]
    public function exposesKeyAsName(): void
    {
        $def = new LangDefinition(key: 'middag_core_task_outbox_worker', strings: ['en' => 'Process outbox']);
        $this->assertSame('middag_core_task_outbox_worker', $def->getName());
    }

    #[Test]
    public function toMoodleArrayReturnsLocaleMap(): void
    {
        $strings = ['en' => 'Process outbox', 'pt_br' => 'Processar outbox'];
        $def = new LangDefinition(key: 'task_outbox', strings: $strings);

        $this->assertSame($strings, $def->toMoodleArray('local_example'));
    }

    #[Test]
    public function getStringReturnsLocaleOrNull(): void
    {
        $def = new LangDefinition(key: 'k', strings: ['en' => 'English', 'pt_br' => 'Português']);

        $this->assertSame('English', $def->getString('en'));
        $this->assertSame('Português', $def->getString('pt_br'));
        $this->assertNull($def->getString('fr'));
    }

    #[Test]
    public function getLocalesListsDeclaredLocales(): void
    {
        $def = new LangDefinition(key: 'k', strings: ['en' => 'a', 'pt_br' => 'b']);
        $this->assertSame(['en', 'pt_br'], $def->getLocales());
    }

    #[Test]
    public function rejectsEmptyKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new LangDefinition(key: '  ', strings: ['en' => 'x']);
    }

    #[Test]
    public function rejectsMissingEnglishString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new LangDefinition(key: 'k', strings: ['pt_br' => 'só português']);
    }

    #[Test]
    public function rejectsBlankEnglishString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new LangDefinition(key: 'k', strings: ['en' => '   ']);
    }

    #[Test]
    public function versionGatingMatchesTheDefinitionFamilyPattern(): void
    {
        $def = new LangDefinition(
            key: 'k',
            strings: ['en' => 'x'],
            min_moodle: '4.5',
            max_moodle: '5.0',
        );

        $this->assertFalse($def->isCompatible('4.4'));
        $this->assertTrue($def->isCompatible('4.5'));
        $this->assertTrue($def->isCompatible('5.0'));
        $this->assertFalse($def->isCompatible('5.1'));

        $unbounded = new LangDefinition(key: 'k', strings: ['en' => 'x']);
        $this->assertTrue($unbounded->isCompatible('3.9'));
        $this->assertTrue($unbounded->isCompatible('6.0'));
    }
}
