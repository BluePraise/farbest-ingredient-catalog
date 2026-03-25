<?php
/**
 * Plugin Name: Farbest Product Catalog
 * Plugin URI: https://farbest.com
 * Description: Custom product catalog solution replacing WooCommerce with advanced filtering and contact form integration
 * Version: 1.0.1
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
define('FPC_VERSION', '1.0.1');
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

        // Initialization
        add_action('init', array($this, 'init'));
        add_action('init', array($this, 'load_textdomain'));

        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

        // Template loader
        add_filter('template_include', array('FPC_Template_Loader', 'load_template'));

        // REST API
        add_action('rest_api_init', array($this, 'register_rest_routes'));
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
     * Initialize plugin
     */
    public function init() {
        // Register post types
        FPC_Post_Types::register();

        // Register taxonomies
        FPC_Taxonomies::register();

        // Initialize ACF fields
        if (class_exists('ACF')) {
            FPC_ACF_Fields::init();
        } else {
            add_action('admin_notices', array($this, 'acf_missing_notice'));
        }

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
        // Only load on relevant pages
        if (!is_singular('fpc_ingredient') && !is_post_type_archive('fpc_ingredient') && !is_tax('fpc_category')) {
            return;
        }

        // Check if build files exist
        $build_css = FPC_PLUGIN_DIR . 'assets/build/index.css';
        $build_js = FPC_PLUGIN_DIR . 'assets/build/index.js';
        $asset_file = FPC_PLUGIN_DIR . 'assets/build/index.asset.php';

        // Frontend styles
        if (file_exists($build_css)) {
            wp_enqueue_style(
                'farbest-catalog-styles',
                FPC_PLUGIN_URL . 'assets/build/index.css',
                array(),
                FPC_VERSION
            );
        }

        // React app bundle
        if (file_exists($build_js) && file_exists($asset_file)) {
            $asset_data = include $asset_file;

            wp_enqueue_script(
                'farbest-catalog-app',
                FPC_PLUGIN_URL . 'assets/build/index.js',
                $asset_data['dependencies'],
                $asset_data['version'],
                true
            );

            // Localize script with data
            wp_localize_script(
                'farbest-catalog-app',
                'fpcData',
                array(
                    'restUrl'        => rest_url('farbest/v1/'),
                    'nonce' => wp_create_nonce('wp_rest'),
                    'currentProduct' => is_singular('fpc_ingredient') ? get_the_ID() : null,
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'pluginUrl' => FPC_PLUGIN_URL,
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
                array('jquery'),
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
            'permission_callback' => '__return_true',
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
                    'taxonomy' => 'fpc_category',
                    'field'    => 'slug',
                    'terms'    => $category_slugs,
                    'operator' => 'IN',
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

        $products = array();
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $id = get_the_ID();
                $products[] = array(
                    'id'             => $id,
                    'title'          => get_the_title(),
                    'excerpt'        => get_the_excerpt(),
                    'description'    => function_exists('get_field') ? (get_field('product_description', $id) ?: '') : '',
                    'permalink'      => get_permalink(),
                    'thumbnail'      => get_the_post_thumbnail_url($id, 'medium'),
                    'categories'     => wp_get_post_terms($id, 'fpc_category', array('fields' => 'names')),
                    'subcategories'  => array_values(array_map(function($t) { return $t->name; }, array_filter(wp_get_post_terms($id, 'fpc_category'), function($t) { return $t->parent !== 0; }))),
                    'claims'         => wp_get_post_terms($id, 'fpc_claim', array('fields' => 'names')),
                    'certifications' => wp_get_post_terms($id, 'fpc_certification', array('fields' => 'names')),
                    'applications'   => wp_get_post_terms($id, 'fpc_application', array('fields' => 'names')),
                );
            }
            wp_reset_postdata();
        }

        return new WP_REST_Response(array(
            'ingredients' => $products,
            'total'       => $query->found_posts,
            'pages'       => $query->max_num_pages,
        ), 200);
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
                return array(
                    'id'        => $term->term_id,
                    'name'      => $term->name,
                    'slug'      => $term->slug,
                    'count'     => $term->count,
                    'parent_id' => $term->parent,
                );
            }, $terms));
        };

        return new WP_REST_Response(array(
            'categories'        => $format_categories($categories),
            'parent_categories' => $format_categories($parent_categories),
            'claims'            => $format($claims),
            'certifications'    => $format($certifications),
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

        return new WP_REST_Response(array(
            'id'         => $ingredient->ID,
            'title'      => $ingredient->post_title,
            'content'    => apply_filters('the_content', $ingredient->post_content),
            'permalink'  => get_permalink($ingredient->ID),
            'thumbnail'  => get_the_post_thumbnail_url($ingredient->ID, 'large'),
            'acf_fields' => function_exists('get_fields') ? get_fields($ingredient->ID) : array(),
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
 * Initialize the plugin
 */
function farbest_product_catalog() {
    return Farbest_Product_Catalog::get_instance();
}

// Start the plugin
farbest_product_catalog();
