<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Domain\Role\Enum;

/**
 * Typed enum wrapping Moodle's core role archetypes.
 *
 * @api
 */
enum RoleArchetype: string
{
    case Manager = 'manager';

    case CourseCreator = 'coursecreator';

    case EditingTeacher = 'editingteacher';

    case Teacher = 'teacher';

    case Student = 'student';

    case Guest = 'guest';

    case User = 'user';

    case Frontpage = 'frontpage';

    /**
     * Whether the archetype represents a teaching role.
     */
    public function isTeacherLike(): bool
    {
        return in_array($this, [self::EditingTeacher, self::Teacher], true);
    }

    /**
     * Whether the archetype represents an administrative role.
     */
    public function isAdminLike(): bool
    {
        return in_array($this, [self::Manager, self::CourseCreator], true);
    }

    /**
     * Whether the archetype represents a learner.
     */
    public function isLearner(): bool
    {
        return $this === self::Student;
    }

    /**
     * Human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Manager => 'Manager',
            self::CourseCreator => 'Course creator',
            self::EditingTeacher => 'Editing teacher',
            self::Teacher => 'Non-editing teacher',
            self::Student => 'Student',
            self::Guest => 'Guest',
            self::User => 'Authenticated user',
            self::Frontpage => 'Frontpage user',
        };
    }

    /**
     * Resolve from Moodle's raw string value (defaults to USER).
     */
    public static function resolve(string $value): self
    {
        return self::tryFrom($value) ?? self::User;
    }
}
