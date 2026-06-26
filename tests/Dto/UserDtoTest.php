<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Dto;

use Middag\Moodle\Dto\UserDto;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @internal
 *
 * @coversNothing
 */
final class UserDtoTest extends TestCase
{
    #[Test]
    public function canBeConstructedWithRequiredArgs(): void
    {
        $dto = new UserDto(
            id: 1,
            username: 'jdoe',
            firstname: 'John',
            lastname: 'Doe',
            email: 'john@example.com',
        );

        $this->assertSame(1, $dto->id);
        $this->assertSame('jdoe', $dto->username);
        $this->assertSame('John', $dto->firstname);
        $this->assertSame('Doe', $dto->lastname);
        $this->assertSame('john@example.com', $dto->email);
    }

    #[Test]
    public function optionalFieldsDefaultToNull(): void
    {
        $dto = new UserDto(
            id: 1,
            username: 'jdoe',
            firstname: 'John',
            lastname: 'Doe',
            email: 'john@example.com',
        );

        $this->assertNull($dto->idnumber);
        $this->assertNull($dto->lang);
        $this->assertNull($dto->city);
        $this->assertNull($dto->country);
        $this->assertNull($dto->picture);
    }

    #[Test]
    public function canBeConstructedWithAllArgs(): void
    {
        $dto = new UserDto(
            id: 42,
            username: 'maria',
            firstname: 'Maria',
            lastname: 'Silva',
            email: 'maria@example.com',
            idnumber: 'EMP001',
            lang: 'pt_br',
            city: 'Sao Paulo',
            country: 'BR',
            picture: 5,
        );

        $this->assertSame(42, $dto->id);
        $this->assertSame('maria', $dto->username);
        $this->assertSame('Maria', $dto->firstname);
        $this->assertSame('Silva', $dto->lastname);
        $this->assertSame('maria@example.com', $dto->email);
        $this->assertSame('EMP001', $dto->idnumber);
        $this->assertSame('pt_br', $dto->lang);
        $this->assertSame('Sao Paulo', $dto->city);
        $this->assertSame('BR', $dto->country);
        $this->assertSame(5, $dto->picture);
    }

    #[Test]
    public function fullnameReturnsConcatenatedFirstAndLast(): void
    {
        $dto = new UserDto(
            id: 1,
            username: 'jdoe',
            firstname: 'John',
            lastname: 'Doe',
            email: 'john@example.com',
        );

        $this->assertSame('John Doe', $dto->fullname());
    }

    #[Test]
    public function fullnameTrimsWhitespaceWhenPartsAreEmpty(): void
    {
        $dto = new UserDto(
            id: 1,
            username: 'jdoe',
            firstname: 'John',
            lastname: '',
            email: 'john@example.com',
        );

        $this->assertSame('John', $dto->fullname());
    }

    #[Test]
    public function fullnameTrimsWhenFirstnameIsEmpty(): void
    {
        $dto = new UserDto(
            id: 1,
            username: 'jdoe',
            firstname: '',
            lastname: 'Doe',
            email: 'john@example.com',
        );

        $this->assertSame('Doe', $dto->fullname());
    }

    #[Test]
    public function toArrayReturnsCompleteRepresentation(): void
    {
        $dto = new UserDto(
            id: 42,
            username: 'maria',
            firstname: 'Maria',
            lastname: 'Silva',
            email: 'maria@example.com',
            idnumber: 'EMP001',
            lang: 'pt_br',
            city: 'Sao Paulo',
            country: 'BR',
            picture: 5,
        );

        $expected = [
            'id' => 42,
            'username' => 'maria',
            'firstname' => 'Maria',
            'lastname' => 'Silva',
            'fullname' => 'Maria Silva',
            'email' => 'maria@example.com',
            'idnumber' => 'EMP001',
            'lang' => 'pt_br',
            'city' => 'Sao Paulo',
            'country' => 'BR',
            'picture' => 5,
        ];

        $this->assertSame($expected, $dto->toArray());
    }

    #[Test]
    public function toArrayIncludesFullnameComputedField(): void
    {
        $dto = new UserDto(
            id: 1,
            username: 'jdoe',
            firstname: 'John',
            lastname: 'Doe',
            email: 'john@example.com',
        );

        $array = $dto->toArray();
        $this->assertArrayHasKey('fullname', $array);
        $this->assertSame('John Doe', $array['fullname']);
    }

    #[Test]
    public function toArrayIncludesNullOptionalFields(): void
    {
        $dto = new UserDto(
            id: 1,
            username: 'jdoe',
            firstname: 'John',
            lastname: 'Doe',
            email: 'john@example.com',
        );

        $array = $dto->toArray();
        $this->assertArrayHasKey('idnumber', $array);
        $this->assertNull($array['idnumber']);
        $this->assertArrayHasKey('lang', $array);
        $this->assertNull($array['lang']);
    }

    #[Test]
    public function isReadonly(): void
    {
        $reflection = new ReflectionClass(UserDto::class);
        $this->assertTrue($reflection->isReadOnly());
    }

    #[Test]
    public function isFinal(): void
    {
        $reflection = new ReflectionClass(UserDto::class);
        $this->assertTrue($reflection->isFinal());
    }
}
