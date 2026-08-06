<?php
/**
 * ACF integration — Local JSON field groups and options pages.
 *
 * The field groups used to be registered here in PHP with
 * acf_add_local_field_group(). ACF hides PHP-registered groups from the
 * Custom Fields → Field Groups admin screen entirely, so there was no way to
 * see or edit them in the backend. They now live as ACF **Local JSON** in
 * acf-json/ instead — one file per group, still version-controlled, but ACF
 * lists them under Field Groups → "Sync available" and they become fully
 * editable in the UI once synced.
 *
 * Ordering note: ACF fires `acf/include_fields` (where it reads Local JSON)
 * *before* `acf/init`. That is why the old PHP registration on `acf/init`
 * could not simply be left in place alongside the JSON — it re-registered each
 * group afterwards and flipped it back to a hidden `local => php` group.
 *
 * Adding a field group: create it in the admin and let ACF write the JSON, or
 * drop a group_*.json file in acf-json/ by hand. Nothing here needs changing.
 */

if (!defined('ABSPATH')) {
    exit;
}

class FPC_ACF_Fields {

    /**
     * Initialize ACF integration.
     *
     * Called on plugins_loaded, which is early enough for the load point to be
     * in place before ACF reads Local JSON on init (priority 5).
     */
    public static function init() {
        add_filter('acf/settings/load_json', array(__CLASS__, 'add_json_load_point'));

        // Keep saves for this plugin's groups inside the plugin. The theme sets
        // the global save point to its own acf-json/ (farbest-classic
        // inc/acf.php), so without this an edit made in the admin would write
        // the JSON there and the copy shipped here would go stale — and the
        // next deploy would quietly revert the edit.
        $write_hooks = array(
            'acf/update_field_group',
            'acf/untrash_field_group',
            'acf/trash_field_group',
            'acf/delete_field_group',
        );
        foreach ($write_hooks as $hook) {
            add_action($hook, array(__CLASS__, 'route_json_save'), 5);
            add_action($hook, array(__CLASS__, 'restore_json_save'), 20);
        }

        add_action('acf/init', array(__CLASS__, 'register_options_pages'));
    }

    /**
     * Absolute path to this plugin's Local JSON directory.
     *
     * Also used as an `acf/settings/save_json` filter callback, which passes
     * the current path — deliberately ignored.
     *
     * @return string
     */
    public static function json_dir() {
        return untrailingslashit(FPC_PLUGIN_DIR) . '/acf-json';
    }

    /**
     * Add this plugin's acf-json/ to ACF's Local JSON load points.
     *
     * Appended rather than replacing: the theme registers its own directory
     * for the Card Grid group, and ACF reads every path in the list.
     *
     * @param array $paths Existing load paths.
     * @return array
     */
    public static function add_json_load_point($paths) {
        $paths[] = self::json_dir();
        return $paths;
    }

    /**
     * Point ACF's save path at this plugin while one of its groups is saved.
     *
     * Ownership is decided by which directory already holds the group's JSON,
     * so this stays correct as groups are added or moved without a hardcoded
     * key list.
     *
     * @param array $field_group The field group being written.
     * @return void
     */
    public static function route_json_save($field_group) {
        if (!self::owns_field_group($field_group)) {
            return;
        }

        add_filter('acf/settings/save_json', array(__CLASS__, 'json_dir'), 99);
    }

    /**
     * Hand the save path back to whatever set it, once ACF has written the file.
     *
     * @param array $field_group The field group that was written.
     * @return void
     */
    public static function restore_json_save($field_group) {
        remove_filter('acf/settings/save_json', array(__CLASS__, 'json_dir'), 99);
    }

    /**
     * Whether a field group's Local JSON lives in this plugin.
     *
     * @param array $field_group The field group.
     * @return bool
     */
    private static function owns_field_group($field_group) {
        if (empty($field_group['key']) || !is_string($field_group['key'])) {
            return false;
        }

        // The key becomes a filename, so allow only ACF's own key format.
        if (!preg_match('/^group_[A-Za-z0-9_-]+$/', $field_group['key'])) {
            return false;
        }

        return file_exists(self::json_dir() . '/' . $field_group['key'] . '.json');
    }

    /**
     * Register ACF options sub-pages.
     *
     * The "Main Settings" group (group_archive_main_settings.json) is located
     * against this page, so it has to exist before ACF resolves locations.
     */
    public static function register_options_pages() {
        if (!function_exists('acf_add_options_sub_page')) {
            return;
        }

        acf_add_options_sub_page(array(
            'page_title'  => 'Main Settings',
            'menu_title'  => 'Main Settings',
            'menu_slug'   => 'fpc-main-settings',
            'parent_slug' => 'edit.php?post_type=fpc_ingredient',
            'capability'  => 'manage_options',
        ));
    }
}
