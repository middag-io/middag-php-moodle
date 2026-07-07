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

use Middag\Moodle\Domain\Platform\PluginDto;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * PluginDto is an immutable projection of Moodle's plugininfo structure. Every
 * accessor and both branches of each predicate (existsOnDisk / isInstalled /
 * hasCoreRequirement) are exercised through fully-populated and null-populated
 * instances, plus the toArray/toObject/__toString projections.
 *
 * @internal
 */
#[CoversClass(PluginDto::class)]
final class PluginDtoCoverageTest extends TestCase
{
    #[Test]
    public function testConstructorExposesEveryPromotedProperty(): void
    {
        $dto = $this->makeFull();

        self::assertSame('mod', $dto->type);
        self::assertSame('forum', $dto->name);
        self::assertSame('mod_forum', $dto->component);
        self::assertSame('/var/www/mod/forum', $dto->rootdir);
        self::assertSame('Forum', $dto->displayname);
        self::assertSame('standard', $dto->source);
        self::assertSame(2024010100, $dto->versiondisk);
        self::assertSame(2023010100, $dto->versiondb);
        self::assertSame(2022010100, $dto->versionrequires);
        self::assertSame(['mod_data' => 2021010100], $dto->dependencies);
        self::assertTrue($dto->enabled);
        self::assertSame('4.5', $dto->release);
        self::assertSame([405], $dto->supported);
        self::assertSame(500, $dto->incompatible);
        self::assertSame('uptodate', $dto->status);
    }

    #[Test]
    public function testToStringReturnsTheComponentName(): void
    {
        $dto = $this->makeFull();

        self::assertSame('mod_forum', (string) $dto);
    }

    #[Test]
    public function testToArrayProjectsTheKeyMetadata(): void
    {
        $dto = $this->makeFull();

        self::assertSame([
            'type' => 'mod',
            'name' => 'forum',
            'component' => 'mod_forum',
            'displayname' => 'Forum',
            'enabled' => true,
            'status' => 'uptodate',
        ], $dto->toArray());
    }

    #[Test]
    public function testJsonSerializeDelegatesToToArray(): void
    {
        $dto = $this->makeFull();

        self::assertSame($dto->toArray(), $dto->jsonSerialize());
    }

    #[Test]
    public function testToObjectMirrorsTheArrayKeys(): void
    {
        $dto = $this->makeFull();

        $obj = $dto->toObject();

        self::assertInstanceOf(stdClass::class, $obj);
        self::assertSame('mod', $obj->type);
        self::assertSame('forum', $obj->name);
        self::assertSame('mod_forum', $obj->component);
        self::assertSame('Forum', $obj->displayname);
        self::assertTrue($obj->enabled);
        self::assertSame('uptodate', $obj->status);
        self::assertSame($dto->toArray(), (array) $obj);
    }

    #[Test]
    public function testExistsOnDiskReflectsThePresenceOfRootdir(): void
    {
        self::assertTrue($this->makeFull()->existsOnDisk());
        self::assertFalse($this->makeEmpty()->existsOnDisk());
    }

    #[Test]
    public function testIsInstalledIsTrueWhenTheDbVersionIsPresent(): void
    {
        // versiondb non-null short-circuits the OR before rootdir is inspected.
        $dto = new PluginDto(
            type: 'local',
            name: 'example',
            component: 'local_example',
            rootdir: null,
            displayname: null,
            source: null,
            versiondisk: null,
            versiondb: 2023010100,
            versionrequires: null,
            dependencies: null,
            enabled: null,
            release: null,
            supported: null,
            incompatible: null,
            status: null,
        );

        self::assertTrue($dto->isInstalled());
    }

    #[Test]
    public function testIsInstalledIsTrueWhenOnlyTheFolderIsPresent(): void
    {
        // versiondb null, but rootdir present -> the right-hand OR branch wins.
        $dto = new PluginDto(
            type: 'local',
            name: 'ondisk',
            component: 'local_ondisk',
            rootdir: '/var/www/local/ondisk',
            displayname: null,
            source: null,
            versiondisk: null,
            versiondb: null,
            versionrequires: null,
            dependencies: null,
            enabled: null,
            release: null,
            supported: null,
            incompatible: null,
            status: null,
        );

        self::assertTrue($dto->isInstalled());
    }

    #[Test]
    public function testIsInstalledIsFalseWhenNeitherDbVersionNorFolderExists(): void
    {
        self::assertFalse($this->makeEmpty()->isInstalled());
    }

    #[Test]
    public function testHasCoreRequirementReflectsVersionrequires(): void
    {
        self::assertTrue($this->makeFull()->hasCoreRequirement());
        self::assertFalse($this->makeEmpty()->hasCoreRequirement());
    }

    private function makeFull(): PluginDto
    {
        return new PluginDto(
            type: 'mod',
            name: 'forum',
            component: 'mod_forum',
            rootdir: '/var/www/mod/forum',
            displayname: 'Forum',
            source: 'standard',
            versiondisk: 2024010100,
            versiondb: 2023010100,
            versionrequires: 2022010100,
            dependencies: ['mod_data' => 2021010100],
            enabled: true,
            release: '4.5',
            supported: [405],
            incompatible: 500,
            status: 'uptodate',
        );
    }

    private function makeEmpty(): PluginDto
    {
        return new PluginDto(
            type: 'local',
            name: 'ghost',
            component: 'local_ghost',
            rootdir: null,
            displayname: null,
            source: null,
            versiondisk: null,
            versiondb: null,
            versionrequires: null,
            dependencies: null,
            enabled: null,
            release: null,
            supported: null,
            incompatible: null,
            status: null,
        );
    }
}
