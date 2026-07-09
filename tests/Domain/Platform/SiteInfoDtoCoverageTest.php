<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Domain\Platform;

use Middag\Moodle\Domain\Platform\SiteInfoDto;
use Middag\Moodle\Shared\Enum\TextFormat;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * SiteInfoDto is a typed projection of Moodle's $SITE global (course id=1).
 * Covered through its defaults, a fully-populated instance, and the toArray()
 * projection (which flattens the TextFormat enum to its backing int value).
 *
 * @internal
 */
#[CoversClass(SiteInfoDto::class)]
final class SiteInfoDtoCoverageTest extends TestCase
{
    #[Test]
    public function testDefaultsMatchTheSitePlaceholder(): void
    {
        $dto = new SiteInfoDto();

        self::assertSame(1, $dto->id);
        self::assertSame('', $dto->fullname);
        self::assertSame('', $dto->shortname);
        self::assertSame('', $dto->summary);
        self::assertSame(TextFormat::Html, $dto->summaryformat);
        self::assertSame('', $dto->format);
        self::assertSame('', $dto->lang);
        self::assertSame('', $dto->theme);
        self::assertSame(0, $dto->timecreated);
        self::assertSame(0, $dto->timemodified);
    }

    #[Test]
    public function testConstructorAssignsEveryProvidedValue(): void
    {
        $dto = new SiteInfoDto(
            id: 1,
            fullname: 'Helico Site',
            shortname: 'helico',
            summary: '<p>Welcome</p>',
            summaryformat: TextFormat::Markdown,
            format: 'topics',
            lang: 'pt_br',
            theme: 'boost',
            timecreated: 1_700_000_000,
            timemodified: 1_700_100_000,
        );

        self::assertSame('Helico Site', $dto->fullname);
        self::assertSame('helico', $dto->shortname);
        self::assertSame('<p>Welcome</p>', $dto->summary);
        self::assertSame(TextFormat::Markdown, $dto->summaryformat);
        self::assertSame('topics', $dto->format);
        self::assertSame('pt_br', $dto->lang);
        self::assertSame('boost', $dto->theme);
        self::assertSame(1_700_000_000, $dto->timecreated);
        self::assertSame(1_700_100_000, $dto->timemodified);
    }

    #[Test]
    public function testToArrayFlattensTheEnumToItsBackingValue(): void
    {
        $dto = new SiteInfoDto(
            fullname: 'Helico Site',
            shortname: 'helico',
            summary: '<p>Welcome</p>',
            summaryformat: TextFormat::Markdown,
            format: 'topics',
            lang: 'pt_br',
            theme: 'boost',
            timecreated: 1_700_000_000,
            timemodified: 1_700_100_000,
        );

        self::assertSame([
            'id' => 1,
            'fullname' => 'Helico Site',
            'shortname' => 'helico',
            'summary' => '<p>Welcome</p>',
            'summaryformat' => TextFormat::Markdown->value,
            'format' => 'topics',
            'lang' => 'pt_br',
            'theme' => 'boost',
            'timecreated' => 1_700_000_000,
            'timemodified' => 1_700_100_000,
        ], $dto->toArray());
    }

    #[Test]
    public function testJsonSerializeDelegatesToToArray(): void
    {
        $dto = new SiteInfoDto(fullname: 'Helico Site', summaryformat: TextFormat::Plain);

        self::assertSame($dto->toArray(), $dto->jsonSerialize());
        self::assertSame(TextFormat::Plain->value, $dto->toArray()['summaryformat']);
    }
}
