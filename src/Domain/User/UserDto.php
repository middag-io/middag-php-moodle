<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Domain\User;

/**
 * Lightweight user projection for API responses and transport.
 *
 * Contains only the most commonly needed user fields, avoiding
 * the full 59-property user entity when only basic info is required.
 *
 * @api
 */
final readonly class UserDto
{
    public function __construct(
        public int $id,
        public string $username,
        public string $firstname,
        public string $lastname,
        public string $email,
        public ?string $idnumber = null,
        public ?string $lang = null,
        public ?string $city = null,
        public ?string $country = null,
        public ?int $picture = null,
    ) {}

    /**
     * Full name (firstname + lastname).
     */
    public function fullname(): string
    {
        return trim($this->firstname . ' ' . $this->lastname);
    }

    /**
     * Create from a full user entity.
     */
    public static function fromEntity(User $user): self
    {
        return new self(
            id: $user->getId(),
            username: $user->getUsername(),
            firstname: $user->getFirstname(),
            lastname: $user->getLastname(),
            email: $user->getEmail(),
            idnumber: $user->getIdnumber(),
            lang: $user->getLang(),
            city: $user->getCity(),
            country: $user->getCountry(),
            picture: $user->getPicture(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'firstname' => $this->firstname,
            'lastname' => $this->lastname,
            'fullname' => $this->fullname(),
            'email' => $this->email,
            'idnumber' => $this->idnumber,
            'lang' => $this->lang,
            'city' => $this->city,
            'country' => $this->country,
            'picture' => $this->picture,
        ];
    }
}
