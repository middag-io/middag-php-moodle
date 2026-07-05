<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Domain\Context;

use context as moodle_context;
use Middag\Moodle\Domain\AbstractMoodleEntity;

/**
 * Context Entity (Moodle Native).
 *
 * @method int         get_contextlevel()
 * @method self        with_contextlevel(int $contextlevel)
 * @method int         get_instanceid()
 * @method self        with_instanceid(int $instanceid)
 * @method null|string get_path()
 * @method self        with_path(?string $path)
 * @method int         get_depth()
 * @method self        with_depth(int $depth)
 * @method int         get_locked()
 * @method self        with_locked(int $locked)
 *
 * @api
 */
class Context extends AbstractMoodleEntity
{
    protected int $contextlevel = 0;

    protected int $instanceid = 0;

    protected ?string $path = null;

    protected int $depth = 0;

    protected int $locked = 0;

    /**
     * {@inheritDoc}
     */
    public static function getTable(): string
    {
        return 'context';
    }

    /**
     * Factory method from Moodle context object.
     *
     * @param moodle_context $context
     */
    public static function fromContext(moodle_context $context): static
    {
        return static::fromRecord($context);
    }
}
