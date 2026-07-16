<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

use core\exception\moodle_exception;

/*
 * Moodle function/class stubs for the cohort / enrol / group / custom-field /
 * user-profile-field Support wrappers. Guarded with !function_exists /
 * !class_exists / !defined so the file is order-independent and purely additive
 * (mirrors tests/stubs/support/request.php and config-env.php). Dynamic returns
 * are driven via $GLOBALS['__middag_test_*'] so tests stay in control; stubs
 * throw when a $GLOBALS['__middag_test_throw_<fn>'] flag is set, letting tests
 * cover the wrappers' catch branches.
 *
 * dml_exception and is_enrolled() are already provided by
 * tests/stubs/support/course.php (loaded first by the glob) and are relied upon
 * here rather than redefined. The global $DB is injected as a recording double
 * by CohortSupport/EnrolSupport/GroupSupport/UserFieldSupport tests, so no
 * central $DB stub is added for them.
 */

// --- Text format constant (GroupSupport::createGroup) ---

if (!defined('FORMAT_HTML')) {
    define('FORMAT_HTML', 1);
}

// --- Cohort functions (CohortSupport) ---

if (!function_exists('cohort_get_cohorts')) {
    function cohort_get_cohorts($contextid, $page = 0, $perpage = 25, $search = ''): array
    {
        return $GLOBALS['__middag_test_cohorts'] ?? [];
    }
}

if (!function_exists('cohort_add_cohort')) {
    function cohort_add_cohort($cohort)
    {
        $GLOBALS['__middag_test_added_cohorts'][] = $cohort;

        return $GLOBALS['__middag_test_cohort_add_id'] ?? 1;
    }
}

// --- Group functions (GroupSupport) ---
// groups_get_group_by_name() throws the namespaced core\exception\moodle_exception
// that addUserInGroup() catches; groups_add_member() throws a plain Exception for
// addMember()'s catch arm.

if (!function_exists('groups_is_member')) {
    function groups_is_member($groupid, $userid = null): bool
    {
        return $GLOBALS['__middag_test_groups_is_member'] ?? false;
    }
}

if (!function_exists('groups_add_member')) {
    function groups_add_member($grouporid, $userid, $component = '', $itemid = 0): bool
    {
        if (!empty($GLOBALS['__middag_test_throw_groups_add_member'])) {
            throw new Exception('groups_add_member failed');
        }

        return $GLOBALS['__middag_test_groups_add_member'] ?? true;
    }
}

if (!function_exists('groups_create_group')) {
    function groups_create_group($data, $editform = false, $editoroptions = false)
    {
        $GLOBALS['__middag_test_created_group'] = $data;

        return $GLOBALS['__middag_test_groups_create_group'] ?? 1;
    }
}

if (!function_exists('groups_get_group_by_name')) {
    function groups_get_group_by_name($courseid, $name)
    {
        if (!empty($GLOBALS['__middag_test_throw_groups_get_group_by_name'])) {
            throw new moodle_exception('group lookup failed');
        }

        return $GLOBALS['__middag_test_groups_get_group_by_name'] ?? false;
    }
}

// --- Enrol functions (EnrolSupport) ---
// enrol_get_plugin() returns the recording plugin double (or false to hit the
// missing-plugin throw); enrol_get_instances() returns the instance list the
// enrolUser() loop scans. is_enrolled() comes from course.php.

if (!function_exists('enrol_get_plugin')) {
    function enrol_get_plugin($name)
    {
        return $GLOBALS['__middag_test_enrol_plugin'] ?? false;
    }
}

if (!function_exists('enrol_get_instances')) {
    function enrol_get_instances($courseid, $onlyenabled): array
    {
        return $GLOBALS['__middag_test_enrol_instances'] ?? [];
    }
}

// --- User profile field functions (UserFieldSupport) ---

if (!function_exists('profile_get_user_fields_with_data')) {
    function profile_get_user_fields_with_data($userid): array
    {
        return $GLOBALS['__middag_test_profile_fields'] ?? [];
    }
}

if (!function_exists('profile_save_custom_fields')) {
    function profile_save_custom_fields($userid, $profilefields): void
    {
        $GLOBALS['__middag_test_saved_profile_fields'][] = [$userid, $profilefields];
    }
}

// --- core_customfield\handler (CustomFieldSupport) ---
// core_customfield\handler is a static factory in Moodle; michaelmeneses/moodle-stubs
// declare it for PHPStan only (not runtime autoloadable), so a behavioural stand-in
// is needed. get_handler() always returns a handler unless the get-handler throw
// flag is set; the per-record methods throw on $GLOBALS['__middag_test_cf_throw']
// so every catch arm of CustomFieldSupport is reachable. The field/data controller
// objects the handler returns are supplied by the test via $GLOBALS.

if (!class_exists('core_customfield\handler', false)) {
    eval(<<<'PHP'
        namespace core_customfield;

        class handler
        {
            public static function get_handler(string $component, string $area, int $itemid = 0): self
            {
                if (!empty($GLOBALS['__middag_test_cf_throw_get_handler'])) {
                    throw new \dml_exception('handler unavailable');
                }

                return new self();
            }

            public function export_instance_data_object(int $instanceid, bool $returnall = false): \stdClass
            {
                if (!empty($GLOBALS['__middag_test_cf_throw'])) {
                    throw new \dml_exception('custom field read failed');
                }

                $GLOBALS['__middag_test_cf_export_returnall'] = $returnall;

                return (object) ($GLOBALS['__middag_test_cf_values'] ?? []);
            }

            public function get_instances_data(array $instanceids, bool $returnall = false): array
            {
                if (!empty($GLOBALS['__middag_test_cf_throw'])) {
                    throw new \dml_exception('custom field bulk read failed');
                }

                $GLOBALS['__middag_test_cf_bulk_returnall'] = $returnall;

                return $GLOBALS['__middag_test_cf_bulk'] ?? [];
            }

            public function get_fields(): array
            {
                if (!empty($GLOBALS['__middag_test_cf_throw'])) {
                    throw new \dml_exception('custom field definitions read failed');
                }

                return $GLOBALS['__middag_test_cf_fields'] ?? [];
            }

            public function get_instance_data(int $instanceid, bool $returnall = false): array
            {
                if (!empty($GLOBALS['__middag_test_cf_throw'])) {
                    throw new \dml_exception('custom field instance read failed');
                }

                $GLOBALS['__middag_test_cf_instance_returnall'] = $returnall;

                return $GLOBALS['__middag_test_cf_instance_data'] ?? [];
            }

            public function delete_instance(int $instanceid): void
            {
                if (!empty($GLOBALS['__middag_test_cf_throw'])) {
                    throw new \dml_exception('custom field delete failed');
                }

                $GLOBALS['__middag_test_cf_deleted'][] = $instanceid;
            }
        }
        PHP);
}
