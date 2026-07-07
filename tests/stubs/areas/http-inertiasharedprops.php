<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

/*
 * Moodle output stub for the Middag\Moodle\Http\Inertia\InertiaSharedProps
 * coverage test.
 *
 * InertiaSharedProps::buildAuth() constructs a core\output\user_picture from the
 * global $USER, sets its size, and resolves the avatar URL via
 * get_url($PAGE)->out(false). moodle-stubs declare user_picture for PHPStan only
 * (not autoloadable at runtime), so a behavioural stand-in lives here. Guarded
 * with !class_exists so the file is order-independent, purely additive, and
 * collision-free with parallel writers.
 *
 * The URL emitted by get_url() is driven via $GLOBALS['__middag_test_avatar_url']
 * and the constructor can be told to throw (via
 * $GLOBALS['__middag_test_user_picture_throw']) so the buildAuth() catch branch
 * (avatar unavailable → null) is reachable.
 */

namespace core\output;

use core\url;
use RuntimeException;

if (!class_exists('core\output\user_picture', false)) {
    class user_picture
    {
        public int $size = 35;

        /**
         * @param mixed $user
         */
        public function __construct(public $user)
        {
            if (!empty($GLOBALS['__middag_test_user_picture_throw'])) {
                throw new RuntimeException('avatar boom');
            }
        }

        /**
         * @param mixed $page
         */
        public function get_url($page): url
        {
            return new url($GLOBALS['__middag_test_avatar_url'] ?? 'https://moodle.test/u/avatar.png');
        }
    }
}
