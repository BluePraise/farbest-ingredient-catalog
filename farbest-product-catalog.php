<?php
/**
 * Plugin Name: Farbest Product Catalog
 * Plugin URI: https://farbest.com
 * Description: Custom product catalog solution replacing WooCommerce with advanced filtering and contact form integration
 * Version: 1.9.0
 * Author: BeckerGuerry
 * Author URI: https://beckerguerry.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: farbest-catalog
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('FPC_VERSION', '1.9.0');
define('FPC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FPC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('FPC_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main Farbest Product Catalog Class
 */
class Farbest_Product_Catalog {

    /**
     * Single instance of the class
     */
    private static $instance = null;

    /**
     * Get the singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Load required dependencies
     */
    private function load_dependencies() {
        // Core classes
        require_once FPC_PLUGIN_DIR . 'includes/class-rewrites.php';
        require_once FPC_PLUGIN_DIR . 'includes/class-post-types.php';
        require_once FPC_PLUGIN_DIR . 'includes/class-taxonomies.php';
        require_once FPC_PLUGIN_DIR . 'includes/class-acf-fields.php';
        require_once FPC_PLUGIN_DIR . 'includes/class-contact-form.php';
        require_once FPC_PLUGIN_DIR . 'includes/class-email-routing.php';
        require_once FPC_PLUGIN_DIR . 'includes/class-template-loader.php';

        // Migration utility (if needed)
        if (defined('WP_CLI') && WP_CLI) {
            require_once FPC_PLUGIN_DIR . 'includes/class-migration.php';
        }
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Activation/Deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // URL routing under /ingredients/ (registers its own init hooks)
        FPC_Rewrites::init();

        // Initialization
        add_action('plugins_loaded', array($this, 'init_acf_fields'));
        add_action('init', array($this, 'init'));
        add_action('init', array($this, 'load_textdomain'));

        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

        // Template loader
        add_filter('template_include', array('FPC_Template_Loader', 'load_template'));

        // REST API
        add_action('rest_api_init', array($this, 'register_rest_routes'));

        // Allow SVG uploads for administrators
        add_filter('upload_mimes', array($this, 'allow_svg_uploads'));
    }

    /**
     * Allow SVG file uploads for trusted admin users.
     * SVGs are restricted by default in WordPress due to potential XSS risk.
     * Only administrators should be uploading category icon SVGs.
     *
     * @param array $mimes Allowed mime types.
     * @return array
     */
    public function allow_svg_uploads( $mimes ) {
        if ( current_user_can( 'manage_options' ) ) {
            $mimes['svg'] = 'image/svg+xml';
        }
        return $mimes;
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Register post types and taxonomies
        FPC_Post_Types::register();
        FPC_Taxonomies::register();

        // Flush rewrite rules
        flush_rewrite_rules();

        // Create necessary database tables if needed
        $this->create_tables();

        // Set default options
        $this->set_default_options();

        // Log activation
        error_log('Farbest Product Catalog plugin activated');
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();

        // Log deactivation
        error_log('Farbest Product Catalog plugin deactivated');
    }

    /**
     * Initialize ACF fields early (plugins_loaded, before acf/init fires)
     */
    public function init_acf_fields() {
        FPC_ACF_Fields::init();
        if (!function_exists('acf_add_local_field_group')) {
            add_action('admin_notices', array($this, 'acf_missing_notice'));
        }
    }

    /**
     * Initialize plugin
     */
    public function init() {
        // Register post types
        FPC_Post_Types::register();

        // Register taxonomies
        FPC_Taxonomies::register();

        // Initialize contact form
        FPC_Contact_Form::init();

        // Initialize email routing
        FPC_Email_Routing::init();
    }

    /**
     * Load plugin textdomain for translations
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'farbest-catalog',
            false,
            dirname(FPC_PLUGIN_BASENAME) . '/languages'
        );
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        $on_ingredient_page = is_singular('fpc_ingredient')
            || is_post_type_archive('fpc_ingredient')
            || is_tax('fpc_category');

        // Also load when the [fpc_ingredients] shortcode is in the queried
        // post's content, so the grid works on any Page. (The shortcode also
        // enqueues on render, covering content rendered outside the main query,
        // e.g. inside a category content-zone page.)
        $post = get_post();
        $has_grid_shortcode = is_singular() && $post
            && has_shortcode( (string) $post->post_content, 'fpc_ingredients' );

        if ( ! $on_ingredient_page && ! $has_grid_shortcode ) {
            return;
        }

        $this->enqueue_catalog_assets();

        // Tab switching script — vanilla JS, single ingredient only.
        if ( is_singular( 'fpc_ingredient' ) ) {
            $tabs_js = FPC_PLUGIN_DIR . 'assets/js/ingredient-tabs.js';
            if ( file_exists( $tabs_js ) ) {
                wp_enqueue_script(
                    'farbest-ingredient-tabs',
                    FPC_PLUGIN_URL . 'assets/js/ingredient-tabs.js',
                    array(),
                    FPC_VERSION,
                    true
                );
            }
        }
    }

    /**
     * Enqueue the catalog React app + styles. Idempotent, so it can be called
     * from the page-type check above or lazily from the [fpc_ingredients]
     * shortcode when the grid is placed on an arbitrary page.
     */
    public function enqueue_catalog_assets() {
        static $done = false;
        if ( $done ) {
            return;
        }
        $done = true;

        $build_js   = FPC_PLUGIN_DIR . 'assets/build/index.js';
        $asset_file = FPC_PLUGIN_DIR . 'assets/build/index.asset.php';

        // Frontend styles — plain CSS, versioned by file mtime.
        foreach ( array( 'catalog', 'archive' ) as $handle ) {
            $path = FPC_PLUGIN_DIR . "assets/css/{$handle}.css";
            if ( file_exists( $path ) ) {
                wp_enqueue_style(
                    "farbest-catalog-{$handle}",
                    FPC_PLUGIN_URL . "assets/css/{$handle}.css",
                    array(),
                    (string) filemtime( $path )
                );
            }
        }

        // React app bundle
        if ( file_exists( $build_js ) && file_exists( $asset_file ) ) {
            $asset_data = include $asset_file;

            wp_enqueue_script(
                'farbest-catalog-app',
                FPC_PLUGIN_URL . 'assets/build/index.js',
                $asset_data['dependencies'],
                $asset_data['version'],
                true
            );

            wp_localize_script(
                'farbest-catalog-app',
                'fpcData',
                array(
                    'restUrl'        => rest_url('farbest/v1/'),
                    'nonce'          => wp_create_nonce('wp_rest'),
                    'currentProduct' => is_singular('fpc_ingredient') ? get_the_ID() : null,
                    'ajaxUrl'        => admin_url('admin-ajax.php'),
                    'pluginUrl'      => FPC_PLUGIN_URL,
                    'archiveUrl'     => get_post_type_archive_link('fpc_ingredient'),
                )
            );
        }
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Only load on product edit screens
        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== 'fpc_ingredient') {
            return;
        }

        $admin_css = FPC_PLUGIN_DIR . 'assets/css/admin.css';
        $admin_js = FPC_PLUGIN_DIR . 'assets/js/admin.js';

        if (file_exists($admin_css)) {
            wp_enqueue_style(
                'farbest-catalog-admin',
                FPC_PLUGIN_URL . 'assets/css/admin.css',
                array(),
                FPC_VERSION
            );
        }

        if (file_exists($admin_js)) {
            wp_enqueue_script(
                'farbest-catalog-admin',
                FPC_PLUGIN_URL . 'assets/js/admin.js',
                array('jquery', 'wp-dom-ready', 'wp-edit-post'),
                FPC_VERSION,
                true
            );
        }
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        register_rest_route('farbest/v1', '/ingredients', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_ingredients'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route('farbest/v1', '/ingredients/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_ingredient'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route('farbest/v1', '/submit-contact', array(
            'methods' => 'POST',
            'callback' => array('FPC_Contact_Form', 'handle_submission'),
            'permission_callback' => function($request) {
                return wp_verify_nonce($request->get_header('X-WP-Nonce'), 'wp_rest');
            },
        ));

        register_rest_route('farbest/v1', '/filter-options', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_filter_options'),
            'permission_callback' => '__return_true',
        ));
    }

    /**
     * Get ingredients via REST API
     *
     * Supported params:
     *   category       - single fpc_category slug
     *   claims         - comma-separated fpc_claim slugs
     *   certifications - comma-separated fpc_certification slugs
     *   applications   - comma-separated fpc_application slugs
     *   search         - keyword search
     *   orderby        - 'name' (default) | 'date'
     *   order          - 'ASC' (default) | 'DESC'
     *   per_page       - default 12
     *   page           - default 1
     */
    public function get_ingredients($request) {
        $params = $request->get_params();

        // Sorting
        $orderby_map = array('name' => 'title', 'date' => 'date');
        $orderby_raw = isset($params['orderby']) ? sanitize_text_field($params['orderby']) : 'name';
        $orderby = isset($orderby_map[$orderby_raw]) ? $orderby_map[$orderby_raw] : 'title';
        $order = isset($params['order']) && strtoupper($params['order']) === 'DESC' ? 'DESC' : 'ASC';

        $args = array(
            'post_type'      => 'fpc_ingredient',
            'posts_per_page' => isset($params['per_page']) ? intval($params['per_page']) : 12,
            'paged'          => isset($params['page']) ? intval($params['page']) : 1,
            'post_status'    => 'publish',
            'orderby'        => $orderby,
            'order'          => $order,
        );

        // Build tax_query
        $tax_query = array('relation' => 'AND');

        if (!empty($params['categories'])) {
            $category_slugs = array_filter(array_map('sanitize_text_field', explode(',', $params['categories'])));
            if (!empty($category_slugs)) {
                $tax_query[] = array(
                    'taxonomy'         => 'fpc_category',
                    'field'            => 'slug',
                    'terms'            => $category_slugs,
                    'operator'         => 'IN',
                    'include_children' => true,
                );
            }
        }

        if (!empty($params['claims'])) {
            $claim_slugs = array_filter(array_map('sanitize_text_field', explode(',', $params['claims'])));
            if (!empty($claim_slugs)) {
                $tax_query[] = array(
                    'taxonomy' => 'fpc_claim',
                    'field'    => 'slug',
                    'terms'    => $claim_slugs,
                    'operator' => 'IN',
                );
            }
        }

        if (!empty($params['certifications'])) {
            $cert_slugs = array_filter(array_map('sanitize_text_field', explode(',', $params['certifications'])));
            if (!empty($cert_slugs)) {
                $tax_query[] = array(
                    'taxonomy' => 'fpc_certification',
                    'field'    => 'slug',
                    'terms'    => $cert_slugs,
                    'operator' => 'IN',
                );
            }
        }

        if (!empty($params['applications'])) {
            $app_slugs = array_filter(array_map('sanitize_text_field', explode(',', $params['applications'])));
            if (!empty($app_slugs)) {
                $tax_query[] = array(
                    'taxonomy' => 'fpc_application',
                    'field'    => 'slug',
                    'terms'    => $app_slugs,
                    'operator' => 'IN',
                );
            }
        }

        if (count($tax_query) > 1) {
            $args['tax_query'] = $tax_query;
        }

        // Keyword search
        if (!empty($params['search'])) {
            $args['s'] = sanitize_text_field($params['search']);
        }

        $query = new WP_Query($args);

        $ingredients = array();
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $id = get_the_ID();
                $category_terms = wp_get_post_terms($id, 'fpc_category');
                if (is_wp_error($category_terms)) {
                    $category_terms = array();
                }

                $category_names = wp_get_post_terms($id, 'fpc_category', array('fields' => 'names'));
                $claim_names = wp_get_post_terms($id, 'fpc_claim', array('fields' => 'names'));
                $certification_names = wp_get_post_terms($id, 'fpc_certification', array('fields' => 'names'));
                $application_names = wp_get_post_terms($id, 'fpc_application', array('fields' => 'names'));

                $ingredients[] = array(
                    'id'             => $id,
                    'title'          => html_entity_decode( wp_strip_all_tags( get_the_title() ), ENT_QUOTES, 'UTF-8' ),
                    'excerpt'        => get_the_excerpt(),
                    'description'    => function_exists('get_field') ? (get_field('product_description', $id) ?: '') : '',
                    'permalink'      => get_permalink(),
                    'thumbnail'      => get_the_post_thumbnail_url($id, 'medium'),
                    'categories'     => is_wp_error($category_names) ? array() : $category_names,
                    'subcategories'  => array_values(array_map(function($term) { return $term->name; }, array_filter($category_terms, function($term) { return $term->parent !== 0; }))),
                    'claims'         => is_wp_error($claim_names) ? array() : $claim_names,
                    'certifications' => is_wp_error($certification_names) ? array() : $certification_names,
                    'applications'   => is_wp_error($application_names) ? array() : $application_names,
                    'benefits'       => fpc_extract_benefits($id),
                );
            }
            wp_reset_postdata();
        }

        return new WP_REST_Response(array(
            'ingredients' => $ingredients,
            'total'       => $query->found_posts,
            'pages'       => $query->max_num_pages,
            'facets'      => $this->compute_facets($params),
        ), 200);
    }

    /**
     * Disjunctive facet counts for the filter dropdowns.
     *
     * For each facet we count matching ingredients per term while applying every
     * OTHER active facet (and the search term) but NOT the facet's own selection.
     * That gives counts scoped to the current context — e.g. Application counts
     * reflect the selected category as soon as you open the dropdown — and makes
     * the other facets narrow as more filters are checked, while a facet's own
     * options stay selectable (they are OR-combined server-side).
     *
     * @param array $params Request params (categories, claims, certifications, applications, search).
     * @return array Map of facet key => ( term slug => count ).
     */
    private function compute_facets($params) {
        $facet_map = array(
            'categories'     => 'fpc_category',
            'claims'         => 'fpc_claim',
            'certifications' => 'fpc_certification',
            'applications'   => 'fpc_application',
        );

        // Build each facet's tax_query clause from the request params, once.
        $clauses = array();
        foreach ($facet_map as $key => $taxonomy) {
            $clauses[$key] = null;
            if (empty($params[$key])) {
                continue;
            }
            $slugs = array_filter(array_map('sanitize_text_field', explode(',', $params[$key])));
            if (empty($slugs)) {
                continue;
            }
            $clause = array(
                'taxonomy' => $taxonomy,
                'field'    => 'slug',
                'terms'    => $slugs,
                'operator' => 'IN',
            );
            if ($taxonomy === 'fpc_category') {
                $clause['include_children'] = true;
            }
            $clauses[$key] = $clause;
        }

        $search = !empty($params['search']) ? sanitize_text_field($params['search']) : '';

        $facets = array();
        foreach ($facet_map as $key => $taxonomy) {
            // Apply every OTHER facet's clause, but not this facet's own.
            $tax_query = array('relation' => 'AND');
            foreach ($clauses as $other_key => $clause) {
                if ($other_key === $key || null === $clause) {
                    continue;
                }
                $tax_query[] = $clause;
            }

            $args = array(
                'post_type'      => 'fpc_ingredient',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'no_found_rows'  => true,
            );
            if (count($tax_query) > 1) {
                $args['tax_query'] = $tax_query;
            }
            if ('' !== $search) {
                $args['s'] = $search;
            }

            $query = new WP_Query($args);
            $counts = array();
            if (!empty($query->posts)) {
                $terms = wp_get_object_terms($query->posts, $taxonomy, array('fields' => 'all_with_object_id'));
                if (!is_wp_error($terms)) {
                    foreach ($terms as $term) {
                        $counts[$term->slug] = isset($counts[$term->slug]) ? $counts[$term->slug] + 1 : 1;
                    }
                }
            }
            $facets[$key] = $counts;
        }

        return $facets;
    }

    /**
     * Get available filter options (categories, claims, certifications with counts)
     */
    public function get_filter_options($request) {
        // Top-level parent categories only (for the category browse grid)
        $parent_categories = get_terms(array(
            'taxonomy'   => 'fpc_category',
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'ASC',
            'parent'     => 0,
        ));

        // All categories (parents + children) for filter dropdowns and slug resolution
        $categories = get_terms(array(
            'taxonomy'   => 'fpc_category',
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ));

        $claims = get_terms(array(
            'taxonomy'   => 'fpc_claim',
            'hide_empty' => true,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ));

        $certifications = get_terms(array(
            'taxonomy'   => 'fpc_certification',
            'hide_empty' => true,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ));

        $applications = get_terms(array(
            'taxonomy'   => 'fpc_application',
            'hide_empty' => true,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ));

        $format = function($terms) {
            if (is_wp_error($terms)) return array();
            return array_values(array_map(function($term) {
                return array(
                    'id'    => $term->term_id,
                    'name'  => $term->name,
                    'slug'  => $term->slug,
                    'count' => $term->count,
                );
            }, $terms));
        };

        $format_categories = function($terms) {
            if (is_wp_error($terms)) return array();
            return array_values(array_map(function($term) {
                $link = get_term_link($term);
                return array(
                    'id'          => $term->term_id,
                    'name'        => $term->name,
                    'slug'        => $term->slug,
                    'count'       => $term->count,
                    'parent_id'   => $term->parent,
                    'link'        => is_wp_error($link) ? '' : $link,
                    'tagline_lines' => (function($term_id) {
                        $raw = get_term_meta($term_id, 'fpc_tagline_lines', true);
                        if (!$raw) return array();
                        $lines = json_decode($raw, true);
                        return is_array($lines) ? $lines : array();
                    })($term->term_id),
                    'icon_url' => (function($term_id) {
                        if (!function_exists('get_field')) return '';
                        $icon = get_field('category_grid_icon', 'fpc_category_' . $term_id);
                        return !empty($icon['url']) ? esc_url_raw($icon['url']) : '';
                    })($term->term_id),
                    'description' => wp_strip_all_tags($term->description),
                );
            }, $terms));
        };

        $format_certifications = function($terms) {
            if (is_wp_error($terms)) return array();
            return array_values(array_map(function($term) {
                $acf_key  = 'fpc_certification_' . $term->term_id;
                $logo     = function_exists('get_field') ? get_field('certification_logo', $acf_key) : null;
                $on_card  = function_exists('get_field') ? (bool) get_field('show_on_card',   $acf_key) : false;
                $on_detail = function_exists('get_field') ? (bool) get_field('show_on_detail', $acf_key) : false;
                return array(
                    'id'            => $term->term_id,
                    'name'          => $term->name,
                    'slug'          => $term->slug,
                    'count'         => $term->count,
                    'logo_url'      => !empty($logo['url'])  ? esc_url_raw($logo['url']) : '',
                    'logo_alt'      => !empty($logo['alt'])  ? sanitize_text_field($logo['alt']) : $term->name,
                    'show_on_card'  => $on_card,
                    'show_on_detail' => $on_detail,
                );
            }, $terms));
        };

        return new WP_REST_Response(array(
            'categories'        => $format_categories($categories),
            'parent_categories' => $format_categories($parent_categories),
            'claims'            => $format($claims),
            'certifications'    => $format_certifications($certifications),
            'applications'      => $format($applications),
        ), 200);
    }

    /**
     * Get single ingredient via REST API
     */
    public function get_ingredient($request) {
        $id = intval($request['id']);
        $ingredient = get_post($id);

        if (!$ingredient || $ingredient->post_type !== 'fpc_ingredient') {
            return new WP_Error('not_found', 'Ingredient not found', array('status' => 404));
        }

        $acf_fields = function_exists('get_fields') ? get_fields($ingredient->ID) : array();
        unset($acf_fields['rep_code_primary'], $acf_fields['rep_code_secondary']);

        return new WP_REST_Response(array(
            'id'         => $ingredient->ID,
            'title'      => wp_strip_all_tags( html_entity_decode( $ingredient->post_title, ENT_QUOTES, 'UTF-8' ) ),
            'content'    => apply_filters('the_content', $ingredient->post_content),
            'permalink'  => get_permalink($ingredient->ID),
            'thumbnail'  => get_the_post_thumbnail_url($ingredient->ID, 'large'),
            'acf_fields' => $acf_fields,
        ), 200);
    }

    /**
     * Create necessary database tables
     */
    private function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Contact form submissions table
        $table_name = $wpdb->prefix . 'fpc_submissions';

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            product_id bigint(20) UNSIGNED DEFAULT NULL,
            name varchar(255) NOT NULL,
            email varchar(255) NOT NULL,
            company varchar(255) DEFAULT NULL,
            message text DEFAULT NULL,
            request_type varchar(50) DEFAULT NULL,
            representative_code varchar(50) DEFAULT NULL,
            submitted_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY product_id (product_id),
            KEY email (email),
            KEY submitted_at (submitted_at)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Set default plugin options
     */
    private function set_default_options() {
        // Set default options if they don't exist
        if (get_option('fpc_ingredients_per_page') === false) {
            add_option('fpc_ingredients_per_page', 12);
        }

        if (get_option('fpc_enable_search') === false) {
            add_option('fpc_enable_search', true);
        }
    }

    /**
     * Admin notice if ACF is missing
     */
    public function acf_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p>
                <?php
                echo wp_kses_post(
                    sprintf(
                        __('Farbest Ingredient Catalog requires <strong>Advanced Custom Fields Pro</strong> to be installed and activated. <a href="%s" target="_blank">Get ACF Pro</a>', 'farbest-catalog'),
                        'https://www.advancedcustomfields.com/pro/'
                    )
                );
                ?>
            </p>
        </div>
        <?php
    }
}

/**
 * Extract benefit groups from a post's block content.
 *
 * Parses any core/columns block where each column contains a heading + list,
 * and any core/group block with the same structure (single-column variant).
 * Returns an array of [ 'heading' => string, 'items' => string[] ] per group.
 *
 * @param int $post_id
 * @return array
 */
function fpc_extract_benefits( $post_id ) {
    if ( ! function_exists( 'parse_blocks' ) ) {
        return array();
    }
    $content = get_post_field( 'post_content', $post_id );
    if ( empty( $content ) ) {
        return array();
    }

    $groups = array();

    foreach ( parse_blocks( $content ) as $block ) {
        $name = $block['blockName'] ?? '';

        // Two-column layout: core/columns → core/column (×n), each with heading + list
        if ( $name === 'core/columns' ) {
            foreach ( $block['innerBlocks'] as $col ) {
                $heading = '';
                $items   = array();
                foreach ( $col['innerBlocks'] ?? array() as $inner ) {
                    $iname = $inner['blockName'] ?? '';
                    if ( $iname === 'core/heading' ) {
                        $heading = trim( wp_strip_all_tags( $inner['innerHTML'] ) );
                    } elseif ( $iname === 'core/list' ) {
                        foreach ( $inner['innerBlocks'] ?? array() as $li ) {
                            $text = trim( wp_strip_all_tags( $li['innerHTML'] ) );
                            if ( $text !== '' ) {
                                $items[] = $text;
                            }
                        }
                    }
                }
                if ( $heading !== '' || ! empty( $items ) ) {
                    $groups[] = array( 'heading' => $heading, 'items' => $items );
                }
            }
        }

        // Single-column layout: core/group → core/heading + core/list
        if ( $name === 'core/group' ) {
            $heading = '';
            $items   = array();
            foreach ( $block['innerBlocks'] ?? array() as $inner ) {
                $iname = $inner['blockName'] ?? '';
                if ( $iname === 'core/heading' ) {
                    $heading = trim( wp_strip_all_tags( $inner['innerHTML'] ) );
                } elseif ( $iname === 'core/list' ) {
                    foreach ( $inner['innerBlocks'] ?? array() as $li ) {
                        $text = trim( wp_strip_all_tags( $li['innerHTML'] ) );
                        if ( $text !== '' ) {
                            $items[] = $text;
                        }
                    }
                }
            }
            if ( $heading !== '' || ! empty( $items ) ) {
                $groups[] = array( 'heading' => $heading, 'items' => $items );
            }
        }
    }

    return $groups;
}

/**
 * Render a category's editable content zone.
 *
 * Loads the Page linked on the fpc_category term via the `category_content_page`
 * ACF field and echoes its block content, so the client can compose an FAQ
 * accordion, callouts, or any other (Kadence) blocks below the ingredient grid
 * without a developer. This is the generic mechanism — pass a different $zone
 * (and add a matching render call) to place client content elsewhere later, on
 * category or single-ingredient templates.
 *
 * Content is run through the `the_content` filter, which triggers do_blocks()
 * and lets Kadence Blocks build and enqueue its per-block CSS for this content.
 * The linked page is rendered regardless of status (it is meant to live as a
 * Draft, with no public URL); only trashed/empty pages are skipped.
 *
 * @param int    $term_id fpc_category term ID.
 * @param string $zone    Zone identifier (only 'below_results' today; kept for future zones).
 * @return void
 */
function fpc_render_category_zone( $term_id, $zone = 'below_results' ) {
    if ( ! function_exists( 'get_field' ) || ! $term_id ) {
        return;
    }

    $page_id = get_field( 'category_content_page', 'fpc_category_' . $term_id );
    if ( ! $page_id ) {
        return;
    }

    $page = get_post( $page_id );
    if ( ! $page || 'trash' === $page->post_status || '' === trim( (string) $page->post_content ) ) {
        return;
    }

    // the_content runs do_blocks(); Kadence hooks in there to emit its block CSS.
    $rendered = apply_filters( 'the_content', $page->post_content );

    printf(
        '<section class="fpc-category-zone fpc-category-zone--%s"><div class="content-wrapper container">%s</div></section>',
        esc_attr( $zone ),
        // Block output is trusted admin content already sanitised by the_content filters.
        $rendered // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    );
}

/**
 * [fpc_ingredients] — render the filterable ingredient grid on any page.
 *
 * Lets the client drop the catalog (or a category-scoped view of it) into any
 * Page, Kadence layout, or the category content zone — the first step toward
 * building category pages as real editor-composed Pages.
 *
 * Attributes:
 *   category — an fpc_category slug to pre-scope the grid (optional; empty shows all).
 *
 * The grid is the same React app used on the archive; it reads the category
 * from data-initial-category. Only one grid per page is supported (the app
 * mounts to the #farbest-ingredient-grid id).
 *
 * @param array $atts Shortcode attributes.
 * @return string
 */
function fpc_ingredients_shortcode( $atts ) {
    $atts = shortcode_atts( array( 'category' => '' ), $atts, 'fpc_ingredients' );

    // Ensure the bundle + styles load, including when this runs outside the main
    // query (a category content-zone page, a Kadence-embedded layout, etc.).
    Farbest_Product_Catalog::get_instance()->enqueue_catalog_assets();

    return sprintf(
        '<div class="fpc-shortcode-grid"><div id="farbest-ingredient-grid" data-initial-category="%s"></div></div>',
        esc_attr( sanitize_title( $atts['category'] ) )
    );
}
add_shortcode( 'fpc_ingredients', 'fpc_ingredients_shortcode' );

/**
 * Initialize the plugin
 */
function farbest_product_catalog() {
    return Farbest_Product_Catalog::get_instance();
}

// Start the plugin
farbest_product_catalog();
