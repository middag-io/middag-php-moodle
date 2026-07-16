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
 * Moodle function/class stubs for the course / category / completion / competency
 * / grade Support wrappers. Guarded with !function_exists / !class_exists /
 * !defined so the file is order-independent and purely additive (mirrors
 * tests/stubs/support/request.php and config-env.php). Dynamic returns are
 * driven via $GLOBALS['__middag_test_*'] so tests stay in control; stubs throw
 * when a $GLOBALS['__middag_test_throw_<fn>'] flag (or a supplied Throwable) is
 * set, letting tests cover the wrappers' catch branches.
 */

// --- Constants (course/enrolment context levels) ---

if (!defined('CONTEXT_COURSE')) {
    define('CONTEXT_COURSE', 50);
}
if (!defined('ENROL_USER_ACTIVE')) {
    define('ENROL_USER_ACTIVE', 0);
}
if (!defined('ENROL_INSTANCE_ENABLED')) {
    define('ENROL_INSTANCE_ENABLED', 0);
}

// --- Exceptions ---
// dml_exception is the global DML failure type caught across the Support layer.
// michaelmeneses/moodle-stubs declares it for PHPStan only (not runtime
// autoloadable), so a behavioural stand-in extending the bootstrap
// moodle_exception is needed.
if (!class_exists('dml_exception', false)) {
    eval('class dml_exception extends \moodle_exception {}');
}

// --- Course functions (CourseSupport) ---
// get_course() returns $GLOBALS['__middag_test_course'] (default false), or
// throws a caller-supplied Throwable placed in $GLOBALS['__middag_test_get_course_throw']
// so the dml_exception / moodle_exception catch branches are reachable.

if (!function_exists('get_course')) {
    function get_course(int $courseorid, bool $clone = true): mixed
    {
        if (($GLOBALS['__middag_test_get_course_throw'] ?? null) instanceof Throwable) {
            throw $GLOBALS['__middag_test_get_course_throw'];
        }

        return $GLOBALS['__middag_test_course'] ?? false;
    }
}

if (!function_exists('get_courses')) {
    function get_courses(mixed $categoryid = 'all', string $sort = 'c.sortorder ASC', string $fields = 'c.*'): array
    {
        return $GLOBALS['__middag_test_courses'] ?? [];
    }
}

if (!function_exists('is_enrolled')) {
    function is_enrolled(object $context, mixed $user = null, string $withcapability = '', bool $onlyactive = false): bool
    {
        if (!empty($GLOBALS['__middag_test_throw_is_enrolled'])) {
            throw new RuntimeException('is_enrolled failed');
        }

        return $GLOBALS['__middag_test_is_enrolled'] ?? false;
    }
}

if (!function_exists('user_has_role_assignment')) {
    function user_has_role_assignment(int $userid, int $roleid, int $contextid = 0): bool
    {
        return !empty($GLOBALS['__middag_test_user_has_role']);
    }
}

if (!function_exists('course_get_format')) {
    function course_get_format(mixed $courseorid): object
    {
        return $GLOBALS['__middag_test_course_format'] ?? new class {
            public function get_last_section_number(): int
            {
                return 0;
            }

            public function get_modinfo(): stdClass
            {
                return (object) ['sections' => [], 'cms' => []];
            }

            public function is_section_visible(mixed $section): bool
            {
                return true;
            }
        };
    }
}

if (!function_exists('get_fast_modinfo')) {
    function get_fast_modinfo(mixed $courseorid, int $userid = 0, bool $resetonly = false): object
    {
        if (!empty($GLOBALS['__middag_test_throw_get_fast_modinfo'])) {
            throw new RuntimeException('get_fast_modinfo failed');
        }

        return $GLOBALS['__middag_test_modinfo'] ?? (object) ['cms' => [], 'sections' => []];
    }
}

// --- Category class (CategorySupport::getSubcategoriesRecursive) ---
// core_course_category::get() returns the category object mapped by id in
// $GLOBALS['__middag_test_categories'] (default false), or throws the global
// moodle_exception the wrapper's catch swallows when the throw flag is set.

if (!class_exists('core_course_category', false)) {
    eval('class core_course_category {
        public static function get($id, $strictness = 2, $alwaysreturnhidden = false) {
            if (!empty($GLOBALS["__middag_test_throw_core_course_category"])) {
                throw new \moodle_exception("categorynotexist");
            }

            return $GLOBALS["__middag_test_categories"][$id] ?? false;
        }
    }');
}

// --- Completion classes/functions (CompletionSupport) ---
// completion_info is instantiated inside CompletionSupport::infoForCourse, so it
// cannot be a PHPUnit mock; it is a data-driven stand-in. Its constructor throws
// a caller-supplied Throwable placed in $GLOBALS['__middag_test_completion_info_throw']
// so the moodle_exception / Exception catch arms of infoForCourse are reachable.

if (!class_exists('completion_info', false)) {
    eval('class completion_info {
        public $course;

        public function __construct($course) {
            $this->course = $course;
            if (($GLOBALS["__middag_test_completion_info_throw"] ?? null) instanceof \Throwable) {
                throw $GLOBALS["__middag_test_completion_info_throw"];
            }
        }

        public function is_enabled($cm = null) {
            if (!empty($GLOBALS["__middag_test_throw_is_enabled"])) {
                throw new \RuntimeException("is_enabled failed");
            }

            return $GLOBALS["__middag_test_completion_enabled"] ?? true;
        }

        public function is_course_complete($userid) {
            if (!empty($GLOBALS["__middag_test_throw_is_course_complete"])) {
                throw new \RuntimeException("is_course_complete failed");
            }

            return $GLOBALS["__middag_test_course_complete"] ?? false;
        }

        public function get_data($cm, $wholecourse = false, $userid = 0) {
            if (!empty($GLOBALS["__middag_test_throw_get_data"])) {
                throw new \RuntimeException("get_data failed");
            }
            $id = is_object($cm) && isset($cm->id) ? (int) $cm->id : 0;
            if (isset($GLOBALS["__middag_test_completion_data_map"][$id])) {
                return $GLOBALS["__middag_test_completion_data_map"][$id];
            }

            return $GLOBALS["__middag_test_completion_data"] ?? false;
        }

        public function get_activities() {
            if (!empty($GLOBALS["__middag_test_throw_get_activities"])) {
                throw new \RuntimeException("get_activities failed");
            }

            return $GLOBALS["__middag_test_completion_activities"] ?? [];
        }

        public function update_state($cm, $possibleresult, $userid) {
            if (!empty($GLOBALS["__middag_test_throw_update_state"])) {
                throw new \RuntimeException("update_state failed");
            }
            $GLOBALS["__middag_test_updated_state"] = [$possibleresult, $userid];
        }

        public function get_num_tracked_users($where = "", $whereparams = [], $groupid = 0) {
            if (!empty($GLOBALS["__middag_test_throw_get_num_tracked_users"])) {
                throw new \RuntimeException("get_num_tracked_users failed");
            }

            return $GLOBALS["__middag_test_tracked_users"] ?? 0;
        }
    }');
}

if (!class_exists('completion_completion', false)) {
    eval('class completion_completion {
        public $params;

        public function __construct($params = []) {
            $this->params = $params;
        }

        public function mark_enrolled() {
            if (!empty($GLOBALS["__middag_test_throw_mark_enrolled"])) {
                throw new \RuntimeException("mark_enrolled failed");
            }
            $GLOBALS["__middag_test_marked_enrolled"] = $this->params;
        }
    }');
}

// --- Competency API (CompetencySupport) ---
// core_competency\api is a static facade. Each method has its own throw flag so
// tests can drive a specific catch branch while keeping is_enabled() truthy.
// Returned "persistent" objects expose get(<field>); tests build them.

if (!class_exists('core_competency\api', false)) {
    eval('namespace core_competency; class api {
        public static function is_enabled() {
            if (!empty($GLOBALS["__middag_test_throw_competency_is_enabled"])) {
                throw new \RuntimeException("competency is_enabled failed");
            }

            return $GLOBALS["__middag_test_competency_enabled"] ?? true;
        }

        public static function list_frameworks($sort = "", $order = "", $skip = 0, $limit = 0, $context = null) {
            if (!empty($GLOBALS["__middag_test_throw_list_frameworks"])) {
                throw new \RuntimeException("list_frameworks failed");
            }

            return $GLOBALS["__middag_test_frameworks"] ?? [];
        }

        public static function read_framework($id) {
            if (!empty($GLOBALS["__middag_test_throw_read_framework"])) {
                throw new \RuntimeException("read_framework failed");
            }

            return $GLOBALS["__middag_test_framework"] ?? null;
        }

        public static function list_competencies($filters = [], $sort = "", $order = "", $skip = 0, $limit = 0) {
            if (!empty($GLOBALS["__middag_test_throw_list_competencies"])) {
                throw new \RuntimeException("list_competencies failed");
            }

            return $GLOBALS["__middag_test_competencies"] ?? [];
        }

        public static function read_competency($id) {
            if (!empty($GLOBALS["__middag_test_throw_read_competency"])) {
                throw new \RuntimeException("read_competency failed");
            }

            return $GLOBALS["__middag_test_competency"] ?? null;
        }

        public static function get_user_competency($userid, $competencyid) {
            if (!empty($GLOBALS["__middag_test_throw_get_user_competency"])) {
                throw new \RuntimeException("get_user_competency failed");
            }

            return $GLOBALS["__middag_test_user_competency"] ?? null;
        }

        public static function list_user_competencies_in_course($courseid, $userid) {
            if (!empty($GLOBALS["__middag_test_throw_list_user_competencies_in_course"])) {
                throw new \RuntimeException("list_user_competencies_in_course failed");
            }

            return $GLOBALS["__middag_test_user_competencies"] ?? [];
        }

        public static function add_evidence(...$args) {
            if (!empty($GLOBALS["__middag_test_throw_add_evidence"])) {
                throw new \RuntimeException("add_evidence failed");
            }

            // Mirror the real api::add_evidence contract: the 4th positional
            // arg is $action and must be one of evidence::ACTION_LOG (0),
            // ACTION_COMPLETE (2) or ACTION_OVERRIDE (3). Any other value hits
            // the default arm of the real switch and throws, so guard here too
            // — otherwise a wrong CompetencySupport::ACTION_* constant would
            // pass false-green (LB-MDL-SUP-004).
            $action = $args[3] ?? null;
            if (!in_array($action, [0, 2, 3], true)) {
                throw new \RuntimeException("Unexpected action parameter when registering an evidence.");
            }

            return $GLOBALS["__middag_test_evidence"] ?? null;
        }

        public static function list_evidence($userid = 0, $competencyid = 0, $planid = 0, $sort = "timecreated", $order = "DESC", $skip = 0, $limit = 0) {
            if (!empty($GLOBALS["__middag_test_throw_list_evidence"])) {
                throw new \RuntimeException("list_evidence failed");
            }

            return $GLOBALS["__middag_test_evidence_list"] ?? [];
        }
    }');
}
