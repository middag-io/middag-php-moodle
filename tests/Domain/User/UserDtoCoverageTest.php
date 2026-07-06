<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Domain\User;

use Middag\Moodle\Domain\User\User;
use Middag\Moodle\Domain\User\UserDto;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Complements UserDtoTest: covers the fromEntity() factory (the branch the
 * base test does not exercise). Does not duplicate the construction / fullname /
 * toArray assertions already covered there.
 *
 * @internal
 */
#[CoversClass(UserDto::class)]
final class UserDtoCoverageTest extends TestCase
{
    #[Test]
    public function fromEntityMapsCoreFieldsOfAUserEntity(): void
    {
        $user = User::fromRecord([
            'id' => 42,
            'username' => 'maria',
            'firstname' => 'Maria',
            'lastname' => 'Silva',
            'email' => 'maria@example.com',
            'idnumber' => 'EMP001',
            'lang' => 'pt_br',
            'city' => 'Sao Paulo',
            'country' => 'BR',
            'picture' => 5,
        ]);

        $dto = UserDto::fromEntity($user);

        self::assertSame(42, $dto->id);
        self::assertSame('maria', $dto->username);
        self::assertSame('Maria', $dto->firstname);
        self::assertSame('Silva', $dto->lastname);
        self::assertSame('maria@example.com', $dto->email);
        self::assertSame('EMP001', $dto->idnumber);
        self::assertSame('pt_br', $dto->lang);
        self::assertSame('Sao Paulo', $dto->city);
        self::assertSame('BR', $dto->country);
        self::assertSame(5, $dto->picture);
        self::assertSame('Maria Silva', $dto->fullname());
    }
}
