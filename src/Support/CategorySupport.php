<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Support;

use core\context\coursecat as context_coursecat;
use core\exception\moodle_exception;
use core_course_category;
use dml_exception;
use Middag\Moodle\Domain\Course\Category;
use Middag\Moodle\Shared\Util\Debug;

/**
 * Utility functions for Moodle course categories.
 *
 * @internal
 */
class CategorySupport
{
    /**
     * Retrieves a category entity by its ID.
     *
     * @param int $categoryid Category ID
     *
     * @return null|Category Category entity or null if not found
     */
    public static function getCategory(int $categoryid): ?Category
    {
        global $DB;

        try {
            $record = $DB->get_record('course_categories', ['id' => $categoryid]);

            return $record ? Category::fromRecord($record) : null;
        } catch (dml_exception $dmlexception) {
            Debug::traceException($dmlexception);

            return null;
        }
    }

    /**
     * Retrieves category contexts as an options list [contextid => label].
     *
     * @param int $visible visibility filter (default: 1)
     *
     * @return array<int, string> Map of context ID to formatted label
     */
    public static function getCategoryContextOptions(int $visible = 1): array
    {
        global $DB;

        $options = [];

        try {
            $categories = $DB->get_records('course_categories', ['visible' => $visible]);
        } catch (dml_exception $dmlexception) {
            Debug::traceException($dmlexception);
            $categories = [];
        }

        foreach ($categories as $category) {
            $context = context_coursecat::instance($category->id);
            $options[$context->id] = 'ID ' . $category->id . ' - ' . $category->name;
        }

        return $options;
    }

    /**
     * Recursively collects subcategory IDs for a given category ID.
     *
     * @param int             $categoryid    Parent category ID
     * @param array<int, int> $subcategories array passed by reference to be filled with subcategory IDs
     *
     * @throws moodle_exception if an error occurs during category retrieval
     */
    public static function getSubcategoriesRecursive(int $categoryid, array &$subcategories): void
    {
        try {
            if ($category = core_course_category::get($categoryid, MUST_EXIST, true)) {
                $children = $category->get_children();
                foreach ($children as $subcategory) {
                    $subcategories[] = (int) $subcategory->id;
                    self::getSubcategoriesRecursive((int) $subcategory->id, $subcategories);
                }
            }
        } catch (\moodle_exception) {
            // Intentionally suppressed: category may not exist or be inaccessible; recursion stops gracefully.
        }
    }
}
