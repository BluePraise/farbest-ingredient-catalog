<?php
/**
 * Template Loader
 */

if (!defined('ABSPATH')) {
    exit;
}

class FPC_Template_Loader {

    /**
     * Load appropriate template
     */
    public static function load_template($template) {
        if (is_singular('fpc_ingredient')) {
            return self::get_template('single-ingredient.php', $template);
        }

        if (is_post_type_archive('fpc_ingredient')) {
            return self::get_template('archive-ingredient.php', $template);
        }

        if (is_tax('fpc_category')) {
            return self::get_template('taxonomy-ingredient-category.php', $template);
        }

        return $template;
    }

    /**
     * Get template file — checks theme first, then plugin directory.
     */
    private static function get_template($template_name, $default_template) {
        $theme_template = locate_template(array(
            'farbest-catalog/' . $template_name,
            $template_name,
        ));

        if ($theme_template) {
            return $theme_template;
        }

        $plugin_template = FPC_PLUGIN_DIR . 'templates/' . $template_name;

        if (file_exists($plugin_template)) {
            return $plugin_template;
        }

        return $default_template;
    }

    /**
     * Get template part
     */
    public static function get_template_part($slug, $name = null, $args = array()) {
        $templates = array();

        if ($name) {
            $templates[] = "farbest-catalog/{$slug}-{$name}.php";
            $templates[] = "{$slug}-{$name}.php";
        }

        $templates[] = "farbest-catalog/{$slug}.php";
        $templates[] = "{$slug}.php";

        $located = locate_template($templates, false, false);

        if (!$located) {
            foreach ($templates as $template) {
                $plugin_template = FPC_PLUGIN_DIR . 'templates/' . basename($template);
                if (file_exists($plugin_template)) {
                    $located = $plugin_template;
                    break;
                }
            }
        }

        if ($located) {
            if (!empty($args)) {
                extract($args);
            }
            include $located;
        }
    }
}
