<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

use core\task\adhoc_task;

/*
 * Moodle function/class stubs for the messaging / notification / task / file /
 * calendar / event / di-bridge / router-bridge Support wrappers (batch "msg-file").
 *
 * Guarded with !function_exists / !class_exists / !defined / !interface_exists so
 * the file is order-independent and purely additive (mirrors request.php and
 * config-env.php). Dynamic returns are driven via $GLOBALS['__middag_test_*'] so
 * tests stay in control; stubs throw when their $GLOBALS['__middag_test_throw_*']
 * flag is set, letting tests cover the wrappers' catch branches.
 */

// --- Moodle FORMAT_* constant (NotificationSupport::send) ---

if (!defined('FORMAT_HTML')) {
    define('FORMAT_HTML', 1);
}

if (!defined('FORMAT_PLAIN')) {
    define('FORMAT_PLAIN', 2);
}

// --- Messaging classes/functions (MessageSupport, NotificationSupport) ---

if (!class_exists('core\message\message', false)) {
    eval('namespace core\message; class message {
        public $convid;
        public $courseid;
        public $component;
        public $name;
        public $userfrom;
        public $userto;
        public $subject;
        public $fullmessage;
        public $fullmessageformat;
        public $fullmessagehtml;
        public $smallmessage;
        public $notification;
        public $contexturl;
        public $contexturlname;
        public $replyto;
        public $replytoname;
        public $attachment;
        public $attachname;
    }');
}

if (!class_exists('core_message\api', false)) {
    eval('namespace core_message; class api {
        const MESSAGE_CONVERSATION_TYPE_INDIVIDUAL = 1;
        const MESSAGE_CONVERSATION_TYPE_GROUP = 2;
        const MESSAGE_CONVERSATION_TYPE_SELF = 3;
        public static function get_self_conversation($userid) { return $GLOBALS["__middag_test_self_conversation"] ?? false; }
        public static function create_conversation($type, $userids, $name = null) { return $GLOBALS["__middag_test_created_conversation"] ?? (object) ["id" => 900 + $type]; }
        public static function get_conversation_between_users($userids) { return $GLOBALS["__middag_test_conversation_between"] ?? false; }
        public static function mark_notification_as_read($notification, $timeread = null) { $GLOBALS["__middag_test_marked_read"] = [$notification, $timeread]; return true; }
    }');
}

if (!class_exists('message_popup\api', false)) {
    eval('namespace message_popup; class api {
        public static function count_unread_popup_notifications($useridto = 0) {
            if (!empty($GLOBALS["__middag_test_throw_unread"])) { throw new \Exception("unread"); }
            return $GLOBALS["__middag_test_unread_count"] ?? 0;
        }
    }');
}

if (!function_exists('message_send')) {
    function message_send($eventdata)
    {
        if (!empty($GLOBALS['__middag_test_throw_message_send'])) {
            throw new RuntimeException('message_send failed');
        }

        // Capture the message object so tests can assert the fields the wrapper set.
        $GLOBALS['__middag_test_sent_message'] = $eventdata;

        return $GLOBALS['__middag_test_message_send_result'] ?? 321;
    }
}

if (!function_exists('make_temp_directory')) {
    function make_temp_directory($directory, $exceptiononerror = true): string
    {
        $path = sys_get_temp_dir() . '/middag_test_temp/' . $directory;

        if (!is_dir($path)) {
            @mkdir($path, 0o777, true);
        }

        return $path;
    }
}

// --- File storage classes/functions (FileSupport, MessageSupport::createTempAttachment) ---
//
// The stored_file stub is configurable per-instance: values come from the ctor
// array; the throw* flags let tests reach FileSupport's catch branches. file_storage
// reads/throws via $GLOBALS so tests fully control get_area_files / get_file / create*.

if (!class_exists('stored_file', false)) {
    class stored_file
    {
        public bool $directory = false;

        public bool $validImage = true;

        public bool $deleteResult = true;

        public bool $throwOnDelete = false;

        public bool $throwOnValidImage = false;

        public bool $throwOnContent = false;

        public bool $throwOnFilesize = false;

        public bool $throwContextOnce = false;

        private bool $contextThrown = false;

        /**
         * @param array<string, mixed> $values
         */
        public function __construct(public array $values = []) {}

        public function get_filename(): string
        {
            return (string) ($this->values['filename'] ?? 'doc.txt');
        }

        public function get_filepath(): string
        {
            return (string) ($this->values['filepath'] ?? '/');
        }

        public function get_itemid(): int
        {
            return (int) ($this->values['itemid'] ?? 0);
        }

        public function get_component(): string
        {
            return (string) ($this->values['component'] ?? 'local_example');
        }

        public function get_filearea(): string
        {
            return (string) ($this->values['filearea'] ?? 'attachments');
        }

        public function get_contextid(): int
        {
            if ($this->throwContextOnce && !$this->contextThrown) {
                $this->contextThrown = true;

                throw new Exception('contextid');
            }

            return (int) ($this->values['contextid'] ?? 1);
        }

        public function get_id(): int
        {
            return (int) ($this->values['id'] ?? 1);
        }

        public function get_contenthash(): string
        {
            return (string) ($this->values['contenthash'] ?? 'chash');
        }

        public function get_pathnamehash(): string
        {
            return (string) ($this->values['pathnamehash'] ?? 'phash');
        }

        public function get_userid(): int
        {
            return (int) ($this->values['userid'] ?? 0);
        }

        public function get_filesize(): int
        {
            if ($this->throwOnFilesize) {
                throw new Exception('filesize');
            }

            return (int) ($this->values['filesize'] ?? 0);
        }

        public function get_mimetype(): string
        {
            return (string) ($this->values['mimetype'] ?? 'text/plain');
        }

        public function get_status(): int
        {
            return (int) ($this->values['status'] ?? 0);
        }

        public function get_source(): ?string
        {
            return $this->values['source'] ?? null;
        }

        public function get_author(): ?string
        {
            return $this->values['author'] ?? null;
        }

        public function get_license(): ?string
        {
            return $this->values['license'] ?? null;
        }

        public function get_timecreated(): int
        {
            return (int) ($this->values['timecreated'] ?? 0);
        }

        public function get_timemodified(): int
        {
            return (int) ($this->values['timemodified'] ?? 0);
        }

        public function get_sortorder(): int
        {
            return (int) ($this->values['sortorder'] ?? 0);
        }

        public function is_directory(): bool
        {
            return $this->directory;
        }

        public function is_valid_image(): bool
        {
            if ($this->throwOnValidImage) {
                throw new Exception('valid_image');
            }

            return $this->validImage;
        }

        public function get_content(): string
        {
            if ($this->throwOnContent) {
                throw new Exception('content');
            }

            return (string) ($this->values['content'] ?? 'CONTENT');
        }

        public function get_content_file_handle()
        {
            if ($this->throwOnContent) {
                throw new Exception('handle');
            }

            return fopen('php://memory', 'rb');
        }

        public function delete(): bool
        {
            if ($this->throwOnDelete) {
                throw new Exception('delete');
            }

            return $this->deleteResult;
        }
    }
}

if (!class_exists('file_storage', false)) {
    class file_storage
    {
        public function get_area_files($contextid, $component, $filearea, $itemid = false, $sort = 'sortorder', $includedirs = true, $updatedsince = 0, $limitfrom = 0, $limitnum = 0): array
        {
            if (!empty($GLOBALS['__middag_test_throw_area_files'])) {
                throw new Exception('get_area_files');
            }

            return $GLOBALS['__middag_test_area_files'] ?? [];
        }

        public function get_file($contextid, $component, $filearea, $itemid, $filepath, $filename)
        {
            if (!empty($GLOBALS['__middag_test_throw_get_file'])) {
                throw new Exception('get_file');
            }

            return $GLOBALS['__middag_test_get_file'] ?? false;
        }

        public function get_file_by_id($fileid)
        {
            if (!empty($GLOBALS['__middag_test_throw_get_file_by_id'])) {
                throw new Exception('get_file_by_id');
            }

            return $GLOBALS['__middag_test_get_file_by_id'] ?? false;
        }

        public function create_file_from_storedfile($filerecord, $fileorid)
        {
            if (!empty($GLOBALS['__middag_test_throw_create_file'])) {
                throw new Exception('create_file');
            }

            return $GLOBALS['__middag_test_created_file'] ?? null;
        }

        public function create_file_from_string($filerecord, $content)
        {
            if (!empty($GLOBALS['__middag_test_throw_create_file'])) {
                throw new Exception('create_file');
            }

            return $GLOBALS['__middag_test_created_file'] ?? null;
        }

        public function create_file_from_pathname($filerecord, $pathname)
        {
            if (!empty($GLOBALS['__middag_test_throw_create_file'])) {
                throw new Exception('create_file');
            }

            return $GLOBALS['__middag_test_created_file'] ?? null;
        }

        public function delete_area_files($contextid, $component = false, $filearea = false, $itemid = false): bool
        {
            if (!empty($GLOBALS['__middag_test_throw_delete_area'])) {
                throw new Exception('delete_area_files');
            }

            return $GLOBALS['__middag_test_delete_area_result'] ?? true;
        }
    }
}

if (!function_exists('get_file_storage')) {
    function get_file_storage(): file_storage
    {
        return new file_storage();
    }
}

// --- Calendar (CalendarSupport) ---
// calendar_event mirrors the tiny slice CalendarSupport uses; static create/load and
// instance update/delete/properties throw via $GLOBALS flags for catch coverage.

if (!class_exists('calendar_event', false)) {
    class calendar_event
    {
        public mixed $id = null;

        /** @var array<string, mixed> */
        public array $props = [];

        /**
         * @param array<string, mixed>|object $props
         */
        public function __construct($props = [])
        {
            $this->props = (array) $props;
            $this->id = $this->props['id'] ?? null;
        }

        public static function create($data, $checkcapability = true): self
        {
            if (!empty($GLOBALS['__middag_test_throw_calendar_create'])) {
                throw new Exception('calendar create');
            }

            $GLOBALS['__middag_test_calendar_create_data'] = $data;
            $GLOBALS['__middag_test_calendar_create_checkcap'] = $checkcapability;
            $event = new self((array) $data);
            $event->id = $GLOBALS['__middag_test_calendar_new_id'] ?? 501;

            return $event;
        }

        public static function load($param, $reset = false): self
        {
            if (!empty($GLOBALS['__middag_test_throw_calendar_load'])) {
                throw new Exception('calendar load');
            }

            return new self($GLOBALS['__middag_test_calendar_props'] ?? ['id' => $param]);
        }

        public function update($data, $checkcapability = true): bool
        {
            if (!empty($GLOBALS['__middag_test_throw_calendar_update'])) {
                throw new Exception('calendar update');
            }

            $GLOBALS['__middag_test_calendar_update_data'] = $data;
            $GLOBALS['__middag_test_calendar_update_checkcap'] = $checkcapability;
            $this->props = array_merge($this->props, (array) $data);

            return true;
        }

        public function delete($deleterepeated = false): bool
        {
            if (!empty($GLOBALS['__middag_test_throw_calendar_delete'])) {
                throw new Exception('calendar delete');
            }

            $GLOBALS['__middag_test_calendar_deleted'] = [$this->id, $deleterepeated];

            return true;
        }

        public function properties(): object
        {
            return (object) $this->props;
        }
    }
}

// --- Tasks (TaskSupport) ---
// core\task\manager and scheduled_task are not central; adhoc_task IS central
// (tests/bootstrap.php) but lacks the getters mapAdhoc() reads, so a concrete
// subclass fixture (middag_test_adhoc_task) adds them.

if (!class_exists('core\task\manager', false)) {
    eval('namespace core\task; class manager {
        public static function get_scheduled_task($classname) { return $GLOBALS["__middag_test_scheduled_task"] ?? null; }
        public static function get_all_scheduled_tasks() { return $GLOBALS["__middag_test_all_scheduled_tasks"] ?? []; }
        public static function get_adhoc_tasks($classname, $failedonly = false) { return $GLOBALS["__middag_test_adhoc_tasks"] ?? []; }
        public static function queue_adhoc_task($task, $checkforexisting = false) { return $GLOBALS["__middag_test_queue_result"] ?? 77; }
        public static function reschedule_or_queue_adhoc_task($task) { $GLOBALS["__middag_test_rescheduled"] = $task; }
        public static function get_next_scheduled_task($timestart) { return $GLOBALS["__middag_test_next_scheduled"] ?? null; }
        public static function get_next_adhoc_task($timestart, $checklimits = true, $classname = null) { return $GLOBALS["__middag_test_next_adhoc"] ?? null; }
        public static function run_from_cli($task) { return $GLOBALS["__middag_test_run_from_cli"] ?? true; }
        public static function run_adhoc_from_cli($taskid) { $GLOBALS["__middag_test_ran_adhoc_cli"] = $taskid; }
        public static function configure_scheduled_task($task) { $GLOBALS["__middag_test_configured"][] = $task; }
        public static function reset_scheduled_tasks_for_component($componentname) { $GLOBALS["__middag_test_reset_component"] = $componentname; }
        public static function get_adhoc_tasks_summary() { return $GLOBALS["__middag_test_adhoc_summary"] ?? []; }
        public static function get_running_tasks($sort = "") { $GLOBALS["__middag_test_running_sort"] = $sort; return $GLOBALS["__middag_test_running_tasks"] ?? []; }
    }');
}

if (!class_exists('core\task\scheduled_task', false)) {
    eval('namespace core\task; class scheduled_task {
        public array $data = [];
        public function get_last_run_time() { return $this->data["lastruntime"] ?? 0; }
        public function get_component() { return $this->data["component"] ?? "core"; }
        public function get_next_run_time() { return $this->data["nextruntime"] ?? 0; }
        public function get_disabled() { return $this->data["disabled"] ?? false; }
        public function get_minute() { return $this->data["minute"] ?? "*"; }
        public function get_hour() { return $this->data["hour"] ?? "*"; }
        public function get_day() { return $this->data["day"] ?? "*"; }
        public function get_month() { return $this->data["month"] ?? "*"; }
        public function get_day_of_week() { return $this->data["dayofweek"] ?? "*"; }
        public function is_customised() { return $this->data["customised"] ?? false; }
        public function get_fail_delay() { return $this->data["faildelay"] ?? 0; }
        public function set_minute($minute, $expandr = true) { $this->data["minute"] = $minute; }
    }');
}

if (!class_exists('middag_test_adhoc_task', false)) {
    class middag_test_adhoc_task extends adhoc_task
    {
        public string $comp = 'local_example';

        public int $nextrun = 0;

        public string $customstr = '{}';

        public int $faildelay = 0;

        public ?int $timestarted = null;

        public ?int $taskid = 1;

        public function get_component(): string
        {
            return $this->comp;
        }

        public function get_next_run_time(): int
        {
            return $this->nextrun;
        }

        public function get_custom_data_as_string(): string
        {
            return $this->customstr;
        }

        public function get_fail_delay(): int
        {
            return $this->faildelay;
        }

        public function get_timestarted(): ?int
        {
            return $this->timestarted;
        }

        public function get_id(): ?int
        {
            return $this->taskid;
        }
    }
}

// --- Cache (CacheSupport, consumed by EventSupport) ---

// NOTE: core_cache\cache is defined canonically in tests/stubs/support/output-db.php
// (recording double honouring a backing store + per-op throw flags). It is NOT
// redefined here — a second guarded definition would collide by glob order and
// silently win/lose. EventSupport/Moodle cache tests use that shared vocabulary
// ($GLOBALS['__middag_test_cache_store'] + __middag_test_cache_<op>_throws).

// --- Plugin manager + event base (PluginSupport ctor, EventSupport) ---

if (!class_exists('core\plugin_manager', false)) {
    eval('namespace core; class plugin_manager {
        private static $inst = null;
        public static function instance() { return self::$inst ??= new self(); }
        public function plugin_name($component) { return $GLOBALS["__middag_test_plugin_display"][$component] ?? ("Name: " . $component); }
    }');
}

if (!class_exists('core\event\base', false)) {
    eval('namespace core\event; class base {
        const LEVEL_OTHER = 0;
        const LEVEL_TEACHING = 1;
        const LEVEL_PARTICIPATING = 2;
        public static function get_name() { return "Base event"; }
        public static function is_deprecated() { return false; }
        public static function get_static_info() { return ["edulevel" => self::LEVEL_OTHER]; }
    }');
}

// --- DI bridge + native router probe symbols (DiBridgeSupport, RouterBridgeSupport) ---

if (!class_exists('core\hook\di_configuration', false)) {
    eval('namespace core\hook; class di_configuration {
        public array $definitions = [];
        public function add_definition($id, $factory, $tags = []) { $this->definitions[$id] = $factory; return $this; }
    }');
}

if (!interface_exists('core\router\route_loader_interface', false)) {
    eval('namespace core\router; interface route_loader_interface {}');
}

// --- Host moodleroot for file-scope require_once wrappers ---
// CalendarSupport (and Moodle::cohort/group/userField) require_once
// $CFG->dirroot . '/{calendar,cohort,group,user/profile}/lib.php' at file scope.
// Provide an existing (empty) tree; tests point $CFG->dirroot here.

if (empty($GLOBALS['__middag_test_moodleroot'])) {
    $middagMoodleRoot = sys_get_temp_dir() . '/middag_moodle_stub_root';

    foreach (['calendar', 'cohort', 'group', 'user/profile'] as $middagLibSub) {
        $middagLibDir = $middagMoodleRoot . '/' . $middagLibSub;

        if (!is_dir($middagLibDir)) {
            @mkdir($middagLibDir, 0o777, true);
        }

        $middagLibFile = $middagLibDir . '/lib.php';

        if (!is_file($middagLibFile)) {
            file_put_contents($middagLibFile, "<?php\n");
        }
    }

    $GLOBALS['__middag_test_moodleroot'] = $middagMoodleRoot;
}
