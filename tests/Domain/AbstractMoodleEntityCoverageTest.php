<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Domain;

use ArrayAccess;
use ArrayObject;
use BadMethodCallException;
use Countable;
use Middag\Framework\Persistence\Contract\EntityInterface;
use Middag\Moodle\Domain\AbstractMoodleEntity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Concrete fixture exercising every property-type branch of the base entity.
 *
 * Declares a spread of typed properties (int, float, string, bool, nullable,
 * union, intersection, untyped) plus a redeclared inherited property and a
 * static property so the reflection-driven code paths are all reachable.
 *
 * @internal
 */
// Not final and properties are protected, mirroring real entities (User,
// Context, ...): AbstractMoodleEntity's magic __get/__set read $this->{$name}
// from the parent scope, so the entity contract requires protected (not
// private) members. Keeping the class non-final also stops rector's
// final-class privatization rule from silently breaking that contract.
class FixtureMoodleEntity extends AbstractMoodleEntity
{
    // Redeclares an inherited property so getAllProperties() hits its dedup skip.
    protected int $timecreated = 0;

    protected ?string $name = null;

    protected float $score = 0.0;

    protected bool $active = false;

    protected string $email = '';

    protected int|string $code = 0;

    protected ?array $data = null;

    // Not readonly: fromRecord() reassigns it via reflection to exercise the
    // intersection-type branch of castValue(). (Left non-readonly on purpose so
    // rector's readonly-promotion rule does not reintroduce the write failure.)
    protected ArrayAccess&Countable $bag;

    protected $untyped;

    private static int $ignored = 5;

    public function __construct()
    {
        // Intersection-typed property has no valid default; initialise it here
        // so reflection reads never hit an uninitialised typed property.
        $this->bag = new ArrayObject();
    }

    public static function getTable(): string
    {
        return 'middag_fixture';
    }
}

/**
 * @internal
 */
#[CoversClass(AbstractMoodleEntity::class)]
final class AbstractMoodleEntityCoverageTest extends TestCase
{
    #[Test]
    public function implementsFrameworkEntityContract(): void
    {
        $entity = new FixtureMoodleEntity();

        self::assertInstanceOf(EntityInterface::class, $entity);
        self::assertSame('middag_fixture', FixtureMoodleEntity::getTable());
    }

    #[Test]
    public function magicGetReturnsPropertyOrNull(): void
    {
        $entity = new FixtureMoodleEntity();
        $entity->name = 'Alice';

        self::assertSame('Alice', $entity->name);
        self::assertNull($entity->missing);
    }

    #[Test]
    public function magicIssetTracksExistenceAndNull(): void
    {
        $entity = new FixtureMoodleEntity();

        // name defaults to null -> exists but not set.
        self::assertFalse(isset($entity->name));
        // missing property never exists.
        self::assertFalse(isset($entity->missing));

        $entity->name = 'Bob';
        self::assertTrue(isset($entity->name));
    }

    #[Test]
    public function magicSetOnlyWritesKnownProperties(): void
    {
        $entity = new FixtureMoodleEntity();

        $entity->email = 'a@b.test';
        self::assertSame('a@b.test', $entity->email);

        // Unknown property is silently ignored (property_exists guard).
        $entity->ghost = 'nope';
        self::assertNull($entity->ghost);
    }

    #[Test]
    public function getCallReturnsPropertyValue(): void
    {
        $entity = new FixtureMoodleEntity();
        $entity->email = 'x@y.test';

        self::assertSame('x@y.test', $entity->get_email());
    }

    #[Test]
    public function getCallThrowsForUnknownProperty(): void
    {
        $entity = new FixtureMoodleEntity();

        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('Property nope does not exist on');

        $entity->get_nope();
    }

    #[Test]
    public function withCallSetsCastValueAndReturnsSelf(): void
    {
        $entity = new FixtureMoodleEntity();

        // Union type int|string resolves to its first non-null named type
        // (string, per reflection order here), so the value is (string)-cast.
        $returned = $entity->with_code(7);

        self::assertSame($entity, $returned);
        self::assertSame('7', $entity->code);
    }

    #[Test]
    public function withCallThrowsForUnknownProperty(): void
    {
        $entity = new FixtureMoodleEntity();

        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('Property nope does not exist on');

        $entity->with_nope('value');
    }

    #[Test]
    public function unsupportedCallThrows(): void
    {
        $entity = new FixtureMoodleEntity();

        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('Method frobnicate not supported in');

        $entity->frobnicate();
    }

    #[Test]
    public function jsonSerializeReturnsArray(): void
    {
        $entity = new FixtureMoodleEntity();
        $entity->email = 'j@s.test';

        $json = $entity->jsonSerialize();

        self::assertIsArray($json);
        self::assertSame('j@s.test', $json['email']);
        self::assertSame($entity->toArray(), $json);
    }

    #[Test]
    public function fromRecordCastsEachTypeAndSkipsUnknownKeys(): void
    {
        $entity = FixtureMoodleEntity::fromRecord([
            'id' => '42',            // int cast
            'score' => '3.5',        // float cast
            'active' => '1',         // bool cast
            'email' => 12345,        // string cast
            'code' => 99,            // union -> first named type (string) cast
            'data' => [1, 2, 3],     // array -> default branch (returned as-is)
            'bag' => new ArrayObject([9]), // intersection -> type_name null branch
            'untyped' => 'raw',      // no type -> returned as-is
            'name' => null,          // null short-circuit at top of castValue
            'unknown' => 'ignored',  // not a property -> skipped
        ]);

        self::assertSame(42, $entity->id);
        self::assertSame(3.5, $entity->score);
        self::assertTrue($entity->active);
        self::assertSame('12345', $entity->email);
        self::assertSame('99', $entity->code);
        self::assertSame([1, 2, 3], $entity->data);
        self::assertInstanceOf(ArrayObject::class, $entity->bag);
        self::assertSame('raw', $entity->untyped);
        self::assertNull($entity->name);
        self::assertNull($entity->unknown);
    }

    #[Test]
    public function fromRecordAcceptsStdClass(): void
    {
        $record = new stdClass();
        $record->id = 5;
        $record->email = 'std@class.test';

        $entity = FixtureMoodleEntity::fromRecord($record);

        self::assertSame(5, $entity->id);
        self::assertSame('std@class.test', $entity->email);
    }

    #[Test]
    public function toRecordAndAsStdClassIncludeAllInstanceProperties(): void
    {
        $entity = new FixtureMoodleEntity();
        $entity->email = 'rec@test';

        $record = $entity->toRecord();

        self::assertInstanceOf(stdClass::class, $record);
        self::assertSame('rec@test', $record->email);
        // Inherited base properties present.
        self::assertSame(0, $record->id);
        self::assertSame(0, $record->timemodified);
        // Static property must be excluded.
        self::assertObjectNotHasProperty('ignored', $record);

        $alias = $entity->asStdClass();
        self::assertEquals($record, $alias);
    }

    #[Test]
    public function getIdReturnsNullWhenUnset(): void
    {
        $entity = new FixtureMoodleEntity();

        self::assertNull($entity->getId());

        $entity->withId(10);
        self::assertSame(10, $entity->getId());
    }

    #[Test]
    public function withIdNormalizesNullToZero(): void
    {
        $entity = new FixtureMoodleEntity();
        $entity->withId(7);

        $returned = $entity->withId(null);

        self::assertSame($entity, $returned);
        self::assertNull($entity->getId());
        self::assertSame(0, $entity->id);
    }

    #[Test]
    public function timecreatedGetterAndSetter(): void
    {
        $entity = new FixtureMoodleEntity();

        $returned = $entity->withTimecreated(111);

        self::assertSame($entity, $returned);
        self::assertSame(111, $entity->getTimecreated());
    }

    #[Test]
    public function timemodifiedGetterAndSetter(): void
    {
        $entity = new FixtureMoodleEntity();

        $returned = $entity->withTimemodified(222);

        self::assertSame($entity, $returned);
        self::assertSame(222, $entity->getTimemodified());
    }

    #[Test]
    public function toArrayMirrorsToRecord(): void
    {
        $entity = new FixtureMoodleEntity();
        $entity->email = 'arr@test';

        $array = $entity->toArray();

        self::assertIsArray($array);
        self::assertSame('arr@test', $array['email']);
        self::assertArrayHasKey('id', $array);
    }
}
