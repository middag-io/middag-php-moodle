<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Domain\Role;

/**
 * Typed enum wrapping Moodle's core role archetypes.
 *
 * @api
 */
enum RoleArchetype: string
{
    case MANAGER = 'manager';

    case COURSE_CREATOR = 'coursecreator';

    case EDITING_TEACHER = 'editingteacher';

    case TEACHER = 'teacher';

    case STUDENT = 'student';

    case GUEST = 'guest';

    case USER = 'user';

    case FRONTPAGE = 'frontpage';

    /**
     * Whether the archetype represents a teaching role.
     */
    public function isTeacherLike(): bool
    {
        return in_array($this, [self::EDITING_TEACHER, self::TEACHER], true);
    }

    /**
     * Whether the archetype represents an administrative role.
     */
    public function isAdminLike(): bool
    {
        return in_array($this, [self::MANAGER, self::COURSE_CREATOR], true);
    }

    /**
     * Whether the archetype represents a learner.
     */
    public function isLearner(): bool
    {
        return $this === self::STUDENT;
    }

    /**
     * Human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::MANAGER => 'Manager',
            self::COURSE_CREATOR => 'Course creator',
            self::EDITING_TEACHER => 'Editing teacher',
            self::TEACHER => 'Non-editing teacher',
            self::STUDENT => 'Student',
            self::GUEST => 'Guest',
            self::USER => 'Authenticated user',
            self::FRONTPAGE => 'Frontpage user',
        };
    }

    /**
     * Resolve from Moodle's raw string value (defaults to USER).
     */
    public static function resolve(string $value): self
    {
        return self::tryFrom($value) ?? self::USER;
    }
}
