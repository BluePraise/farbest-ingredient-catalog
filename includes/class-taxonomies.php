<?php
/**
 * Register Custom Taxonomies
 */

if (!defined('ABSPATH')) {
    exit;
}

class FPC_Taxonomies {

    /**
     * Register all taxonomies
     */
    public static function register() {
        self::register_category();
        self::register_claims();
        self::register_certifications();
        self::register_applications();
        self::register_fiber_benefits();
        self::register_category_meta();
        add_action('add_meta_boxes', array(__CLASS__, 'remove_native_metaboxes'));
    }

    /**
     * Remove native WordPress taxonomy metaboxes replaced by ACF fields
     */
    public static function remove_native_metaboxes() {
        remove_meta_box('tagsdiv-fpc_application',   'fpc_ingredient', 'side');
        remove_meta_box('tagsdiv-fpc_claim',         'fpc_ingredient', 'side');
        remove_meta_box('tagsdiv-fpc_certification', 'fpc_ingredient', 'side');
        remove_meta_box('tagsdiv-fpc_fiber_benefit', 'fpc_ingredient', 'side');
    }

    const TAGLINE_MAX_LINES = 5;

    /**
     * Register term meta and admin UI fields for fpc_category tagline lines.
     */
    private static function register_category_meta() {
        register_term_meta('fpc_category', 'fpc_tagline_lines', array(
            'type'         => 'string',
            'description'  => 'JSON array of tagline lines shown on the category card',
            'single'       => true,
            'show_in_rest' => true,
        ));

        add_action('fpc_category_add_form_fields', array(__CLASS__, 'render_tagline_add_field'));
        add_action('fpc_category_edit_form_fields', array(__CLASS__, 'render_tagline_edit_field'));
        add_action('created_fpc_category', array(__CLASS__, 'save_tagline_field'));
        add_action('edited_fpc_category', array(__CLASS__, 'save_tagline_field'));
        add_action('admin_footer', array(__CLASS__, 'render_tagline_script'));
    }

    public static function render_tagline_add_field() {
        self::render_tagline_inputs(array());
    }

    public static function render_tagline_edit_field($term) {
        $raw   = get_term_meta($term->term_id, 'fpc_tagline_lines', true);
        $lines = $raw ? json_decode($raw, true) : array();
        if (!is_array($lines)) $lines = array();
        self::render_tagline_inputs($lines, true);
    }

    private static function render_tagline_inputs($lines, $is_edit = false) {
        $max = self::TAGLINE_MAX_LINES;
        $wrap_open  = $is_edit ? '<tr class="form-field"><th scope="row">' : '<div class="form-field">';
        $wrap_mid   = $is_edit ? '</th><td>' : '';
        $wrap_close = $is_edit ? '</td></tr>' : '</div>';

        echo wp_kses_post($wrap_open);
        echo '<label>' . esc_html__('Card Tagline Lines', 'farbest-catalog') . '</label>';
        echo wp_kses_post($wrap_mid);
        echo '<div id="fpc-tagline-repeater">';

        for ($i = 0; $i < $max; $i++) {
            $val = isset($lines[$i]) ? $lines[$i] : '';
            printf(
                '<p><input type="text" name="fpc_tagline_lines[]" value="%s" style="width:100%%;max-width:25em;" placeholder="%s" /></p>',
                esc_attr($val),
                esc_attr(sprintf(__('Line %d', 'farbest-catalog'), $i + 1))
            );
        }

        echo '<p class="description">' . esc_html__('Up to 5 lines displayed beneath the category name on the browse grid. Leave lines blank to omit them.', 'farbest-catalog') . '</p>';
        echo '</div>';
        echo wp_kses_post($wrap_close);
    }

    public static function save_tagline_field($term_id) {
        $taxonomy = get_taxonomy('fpc_category');

        if (!$taxonomy || !current_user_can($taxonomy->cap->edit_terms)) {
            return;
        }

        $nonce_action = isset($_POST['tag_ID']) ? 'update-tag_' . $term_id : 'add-tag';
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), $nonce_action)) {
            return;
        }

        if (!isset($_POST['fpc_tagline_lines'])) {
            return;
        }

        $raw   = (array) wp_unslash($_POST['fpc_tagline_lines']);
        $lines = array_values(array_filter(array_map('sanitize_text_field', $raw)));
        $lines = array_slice($lines, 0, self::TAGLINE_MAX_LINES);
        update_term_meta($term_id, 'fpc_tagline_lines', wp_json_encode($lines));
    }

    public static function render_tagline_script() {
        $screen = get_current_screen();
        if (!$screen || $screen->taxonomy !== 'fpc_category') return;
        // No JS needed — static repeater with fixed inputs.
    }

    /**
     * Register Ingredient Category taxonomy
     */
    private static function register_category() {
        $labels = array(
            'name'                       => _x('Ingredient Categories', 'Taxonomy General Name', 'farbest-catalog'),
            'singular_name'              => _x('Ingredient Category', 'Taxonomy Singular Name', 'farbest-catalog'),
            'menu_name'                  => __('Categories', 'farbest-catalog'),
            'all_items'                  => __('All Categories', 'farbest-catalog'),
            'parent_item'                => __('Parent Category', 'farbest-catalog'),
            'parent_item_colon'          => __('Parent Category:', 'farbest-catalog'),
            'new_item_name'              => __('New Category Name', 'farbest-catalog'),
            'add_new_item'               => __('Add New Category', 'farbest-catalog'),
            'edit_item'                  => __('Edit Category', 'farbest-catalog'),
            'update_item'                => __('Update Category', 'farbest-catalog'),
            'view_item'                  => __('View Category', 'farbest-catalog'),
            'separate_items_with_commas' => __('Separate categories with commas', 'farbest-catalog'),
            'add_or_remove_items'        => __('Add or remove categories', 'farbest-catalog'),
            'choose_from_most_used'      => __('Choose from the most used', 'farbest-catalog'),
            'popular_items'              => __('Popular Categories', 'farbest-catalog'),
            'search_items'               => __('Search Categories', 'farbest-catalog'),
            'not_found'                  => __('Not Found', 'farbest-catalog'),
            'no_terms'                   => __('No categories', 'farbest-catalog'),
            'items_list'                 => __('Categories list', 'farbest-catalog'),
            'items_list_navigation'      => __('Categories list navigation', 'farbest-catalog'),
        );

        $args = array(
            'labels'            => $labels,
            'hierarchical'      => true,
            'public'            => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_nav_menus' => true,
            'show_tagcloud'     => true,
            'show_in_rest'      => true,
            'rest_base'         => 'ingredient-categories',
            'rewrite'           => array(
                'slug'        => 'ingredient-category',
                'with_front'  => false,
                'hierarchical' => true,
            ),
        );

        register_taxonomy('fpc_category', array('fpc_ingredient'), $args);
    }

    /**
     * Register Label Claims taxonomy (non-hierarchical)
     */
    private static function register_claims() {
        $labels = array(
            'name'                       => _x('Label Claims', 'Taxonomy General Name', 'farbest-catalog'),
            'singular_name'              => _x('Label Claim', 'Taxonomy Singular Name', 'farbest-catalog'),
            'menu_name'                  => __('Label Claims', 'farbest-catalog'),
            'all_items'                  => __('All Claims', 'farbest-catalog'),
            'new_item_name'              => __('New Claim Name', 'farbest-catalog'),
            'add_new_item'               => __('Add New Claim', 'farbest-catalog'),
            'edit_item'                  => __('Edit Claim', 'farbest-catalog'),
            'update_item'                => __('Update Claim', 'farbest-catalog'),
            'view_item'                  => __('View Claim', 'farbest-catalog'),
            'separate_items_with_commas' => __('Separate claims with commas', 'farbest-catalog'),
            'add_or_remove_items'        => __('Add or remove claims', 'farbest-catalog'),
            'choose_from_most_used'      => __('Choose from the most used', 'farbest-catalog'),
            'popular_items'              => __('Popular Claims', 'farbest-catalog'),
            'search_items'               => __('Search Claims', 'farbest-catalog'),
            'not_found'                  => __('Not Found', 'farbest-catalog'),
        );

        $args = array(
            'labels'            => $labels,
            'hierarchical'      => false,
            'public'            => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_nav_menus' => false,
            'show_tagcloud'     => false,
            'show_in_rest'      => true,
            'rewrite'           => array(
                'slug'       => 'claim',
                'with_front' => false,
            ),
        );

        register_taxonomy('fpc_claim', array('fpc_ingredient'), $args);
    }

    /**
     * Register Certifications taxonomy (non-hierarchical)
     */
    private static function register_certifications() {
        $labels = array(
            'name'          => _x('Certifications', 'Taxonomy General Name', 'farbest-catalog'),
            'singular_name' => _x('Certification', 'Taxonomy Singular Name', 'farbest-catalog'),
            'menu_name'     => __('Certifications', 'farbest-catalog'),
            'all_items'     => __('All Certifications', 'farbest-catalog'),
            'new_item_name' => __('New Certification Name', 'farbest-catalog'),
            'add_new_item'  => __('Add New Certification', 'farbest-catalog'),
            'edit_item'     => __('Edit Certification', 'farbest-catalog'),
            'update_item'   => __('Update Certification', 'farbest-catalog'),
            'view_item'     => __('View Certification', 'farbest-catalog'),
        );

        $args = array(
            'labels'            => $labels,
            'hierarchical'      => false,
            'public'            => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_nav_menus' => false,
            'show_tagcloud'     => false,
            'show_in_rest'      => true,
            'rewrite'           => array(
                'slug'       => 'certification',
                'with_front' => false,
            ),
        );

        register_taxonomy('fpc_certification', array('fpc_ingredient'), $args);
    }

    /**
     * Register Applications taxonomy (non-hierarchical)
     */
    private static function register_applications() {
        $labels = array(
            'name'                       => _x('Applications', 'Taxonomy General Name', 'farbest-catalog'),
            'singular_name'              => _x('Application', 'Taxonomy Singular Name', 'farbest-catalog'),
            'menu_name'                  => __('Applications', 'farbest-catalog'),
            'all_items'                  => __('All Applications', 'farbest-catalog'),
            'new_item_name'              => __('New Application Name', 'farbest-catalog'),
            'add_new_item'               => __('Add New Application', 'farbest-catalog'),
            'edit_item'                  => __('Edit Application', 'farbest-catalog'),
            'update_item'                => __('Update Application', 'farbest-catalog'),
            'view_item'                  => __('View Application', 'farbest-catalog'),
            'separate_items_with_commas' => __('Separate applications with commas', 'farbest-catalog'),
            'add_or_remove_items'        => __('Add or remove applications', 'farbest-catalog'),
            'choose_from_most_used'      => __('Choose from the most used', 'farbest-catalog'),
            'popular_items'              => __('Popular Applications', 'farbest-catalog'),
            'search_items'               => __('Search Applications', 'farbest-catalog'),
            'not_found'                  => __('Not Found', 'farbest-catalog'),
        );

        $args = array(
            'labels'            => $labels,
            'hierarchical'      => false,
            'public'            => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_nav_menus' => false,
            'show_tagcloud'     => false,
            'show_in_rest'      => true,
            'meta_box_cb'       => false,
            'rewrite'           => array(
                'slug'       => 'application',
                'with_front' => false,
            ),
        );

        register_taxonomy('fpc_application', array('fpc_ingredient'), $args);
    }

    /**
     * Register Fiber Benefits taxonomy (non-hierarchical)
     */
    private static function register_fiber_benefits() {
        $labels = array(
            'name'                       => _x('Fiber Benefits', 'Taxonomy General Name', 'farbest-catalog'),
            'singular_name'              => _x('Fiber Benefit', 'Taxonomy Singular Name', 'farbest-catalog'),
            'menu_name'                  => __('Fiber Benefits', 'farbest-catalog'),
            'all_items'                  => __('All Fiber Benefits', 'farbest-catalog'),
            'new_item_name'              => __('New Fiber Benefit Name', 'farbest-catalog'),
            'add_new_item'               => __('Add New Fiber Benefit', 'farbest-catalog'),
            'edit_item'                  => __('Edit Fiber Benefit', 'farbest-catalog'),
            'update_item'                => __('Update Fiber Benefit', 'farbest-catalog'),
            'view_item'                  => __('View Fiber Benefit', 'farbest-catalog'),
            'separate_items_with_commas' => __('Separate fiber benefits with commas', 'farbest-catalog'),
            'add_or_remove_items'        => __('Add or remove fiber benefits', 'farbest-catalog'),
            'choose_from_most_used'      => __('Choose from the most used', 'farbest-catalog'),
            'popular_items'              => __('Popular Fiber Benefits', 'farbest-catalog'),
            'search_items'               => __('Search Fiber Benefits', 'farbest-catalog'),
            'not_found'                  => __('Not Found', 'farbest-catalog'),
        );

        $args = array(
            'labels'            => $labels,
            'hierarchical'      => false,
            'public'            => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_nav_menus' => false,
            'show_tagcloud'     => false,
            'show_in_rest'      => true,
            'meta_box_cb'       => false,
            'rewrite'           => array(
                'slug'       => 'fiber-benefit',
                'with_front' => false,
            ),
        );

        register_taxonomy('fpc_fiber_benefit', array('fpc_ingredient'), $args);
    }
}
