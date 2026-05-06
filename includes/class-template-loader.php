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
            return self::get_template('single-fpc_ingredient', 'single-ingredient.php', $template);
        }

        if (is_post_type_archive('fpc_ingredient') || is_tax('fpc_category')) {
            return self::get_template('archive-fpc_ingredient', 'archive-ingredient.php', $template);
        }

        return $template;
    }

    /**
     * Get template file — checks block theme HTML templates first, then
     * classic theme PHP overrides, then plugin PHP templates.
     *
     * @param string $block_slug  Block theme template slug (no extension).
     * @param string $php_name    PHP template filename for classic/plugin fallback.
     * @param string $default     WordPress default template path.
     */
    private static function get_template($block_slug, $php_name, $default) {
        // Block theme: WP handles .html templates natively — bail out so WP uses it.
        $block_template = get_template_directory() . '/templates/' . $block_slug . '.html';
        if (function_exists('wp_is_block_theme') && wp_is_block_theme() && file_exists($block_template)) {
            return $default;
        }

        // Classic theme override in farbest-catalog/ or root.
        $theme_template = locate_template(array(
            'farbest-catalog/' . $php_name,
            $php_name,
        ));

        if ($theme_template) {
            return $theme_template;
        }

        // Plugin fallback.
        $plugin_template = FPC_PLUGIN_DIR . 'templates/' . $php_name;

        if (file_exists($plugin_template)) {
            return $plugin_template;
        }

        return $default;
    }

    /**
     * Render the single ingredient template and return the HTML string.
     *
     * Used by block themes: the block theme's the_content filter calls this
     * method so it gets clean HTML without needing to know the template path
     * or manage ob_start / include itself.
     *
     * @return string Rendered HTML output.
     */
    public static function render_single() {
        $template = FPC_PLUGIN_DIR . 'templates/single-ingredient.php';

        if ( ! file_exists( $template ) ) {
            return '';
        }

        ob_start();
        include $template;
        return ob_get_clean();
    }

    /**
     * Render the ingredient archive template and return the HTML string.
     *
     * This is the render_callback for the farbest/ingredient-archive server-side
     * block. Block theme HTML templates include this block to get the PHP-rendered
     * archive hero + React mount point without any PHP in the template file.
     *
     * @param array    $attributes Block attributes (unused — no editor UI).
     * @param string   $content    Inner block content (unused).
     * @param WP_Block $block      Block instance (unused).
     * @return string Rendered HTML output.
     */
    public static function render_archive( $attributes, $content, $block ) {
        $template = FPC_PLUGIN_DIR . 'templates/archive-ingredient.php';

        if ( ! file_exists( $template ) ) {
            return '';
        }

        ob_start();
        include $template;
        return ob_get_clean();
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
