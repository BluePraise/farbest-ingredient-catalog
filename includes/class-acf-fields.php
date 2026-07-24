<?php
/**
 * Register ACF Field Groups
 */

if (!defined('ABSPATH')) {
    exit;
}

class FPC_ACF_Fields {

    /**
     * Initialize ACF fields
     */
    public static function init() {
        add_action('acf/init', array(__CLASS__, 'register_options_pages'));
        add_action('acf/init', array(__CLASS__, 'register_field_groups'));
    }

    /**
     * Register ACF options sub-pages
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

    /**
     * Register all field groups
     */
    public static function register_field_groups() {
        if (!function_exists('acf_add_local_field_group')) {
            return;
        }

        self::register_product_details();
        self::register_ingredient_benefits();
        self::register_representative_codes();
        self::register_certification_logo();
        self::register_vendor_fields();
        self::register_category_hero();
        self::register_archive_main_settings();
    }

    /**
     * Ingredient Benefits field group
     *
     * Restored in 1.6.0. This was removed in June 2026 in favour of the block
     * theme's farbest/benefits-columns pattern; with the block theme retired
     * and the site back on classic PHP templates, patterns are no longer
     * available, so the repeater is the editing surface again.
     *
     * Deliberately simpler than the pre-June version: no auto-merge of
     * fpc_application / fpc_fiber_benefit taxonomy terms into synthetic
     * columns. Those terms still display in the Product Details tab.
     * Rendered by templates/single-ingredient.php.
     */
    private static function register_ingredient_benefits() {
        acf_add_local_field_group(array(
            'key'   => 'group_ingredient_benefits',
            'title' => 'Ingredient Benefits',
            'fields' => array(
                array(
                    'key'          => 'field_benefits_columns',
                    'label'        => 'Benefits Columns',
                    'name'         => 'benefits_columns',
                    'type'         => 'repeater',
                    'instructions' => 'Add one or more benefit columns (e.g. "Application Benefits", "Fiber Benefits")',
                    'button_label' => 'Add Column',
                    'sub_fields'   => array(
                        array(
                            'key'         => 'field_benefits_column_label',
                            'label'       => 'Column Heading',
                            'name'        => 'column_label',
                            'type'        => 'text',
                            'placeholder' => 'e.g. Application Benefits',
                            'required'    => 1,
                        ),
                        array(
                            'key'          => 'field_benefits_column_items',
                            'label'        => 'Benefit Items',
                            'name'         => 'column_items',
                            'type'         => 'repeater',
                            'button_label' => 'Add Item',
                            'sub_fields'   => array(
                                array(
                                    'key'      => 'field_benefits_item_text',
                                    'label'    => 'Item',
                                    'name'     => 'item_text',
                                    'type'     => 'text',
                                    'required' => 1,
                                ),
                            ),
                        ),
                    ),
                ),
            ),
            'location' => array(
                array(
                    array(
                        'param'    => 'post_type',
                        'operator' => '==',
                        'value'    => 'fpc_ingredient',
                    ),
                ),
            ),
            'menu_order' => 3,
            'position'   => 'normal',
            'style'      => 'default',
        ));
    }

    /**
     * Product Details field group
     */
    private static function register_product_details() {
        acf_add_local_field_group(array(
            'key' => 'group_product_details',
            'title' => 'Product Details',
            'fields' => array(
                array(
                    'key' => 'field_product_description',
                    'label' => 'Product Description',
                    'name' => 'product_description',
                    'type' => 'wysiwyg',
                    'instructions' => 'Detailed product description',
                    'required' => 0,
                    'tabs' => 'all',
                    'toolbar' => 'full',
                    'media_upload' => 1,
                ),
                array(
                    'key' => 'field_product_sheet',
                    'label' => 'Product Sheet (PDF)',
                    'name' => 'product_sheet',
                    'type' => 'file',
                    'instructions' => 'Upload the product specification sheet PDF',
                    'required' => 0,
                    'return_format' => 'array',
                    'library' => 'all',
                    'mime_types' => 'pdf',
                ),
                array(
                    'key'          => 'field_product_applications',
                    'label'        => 'Applications',
                    'name'         => 'product_applications',
                    'type'         => 'taxonomy',
                    'instructions' => 'Select the applications this ingredient is suited for',
                    'required'     => 0,
                    'taxonomy'     => 'fpc_application',
                    'field_type'   => 'multi_select',
                    'allow_null'   => 1,
                    'add_term'     => 1,
                    'save_terms'   => 1,
                    'load_terms'   => 1,
                    'return_format' => 'id',
                    'multiple'     => 1,
                ),
                array(
                    'key'           => 'field_product_vendors',
                    'label'         => 'Vendors',
                    'name'          => 'product_vendors',
                    'type'          => 'taxonomy',
                    'instructions'  => 'Select the vendors for this ingredient. Manage vendors under Ingredients → Vendors.',
                    'required'      => 0,
                    'taxonomy'      => 'fpc_vendor',
                    'field_type'    => 'checkbox',
                    'allow_null'    => 1,
                    'add_term'      => 0,
                    'save_terms'    => 1,
                    'load_terms'    => 1,
                    'return_format' => 'id',
                    'multiple'      => 1,
                ),
                array(
                    'key' => 'field_product_packaging',
                    'label' => 'Packaging',
                    'name' => 'product_packaging',
                    'type' => 'textarea',
                    'instructions' => 'Packaging details',
                    'required' => 0,
                    'rows' => 3,
                ),
                array(
                    'key' => 'field_display_order',
                    'label' => 'Display Order',
                    'name' => 'display_order',
                    'type' => 'number',
                    'instructions' => 'Order for sorting products (lower numbers appear first)',
                    'required' => 0,
                    'default_value' => 0,
                    'min' => 0,
                    'step' => 1,
                ),
            ),
            'location' => array(
                array(
                    array(
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'fpc_ingredient',
                    ),
                ),
            ),
            'menu_order' => 0,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
        ));
    }

    /**
     * Certification Logo image field (on fpc_certification taxonomy terms)
     */
    private static function register_certification_logo() {
        acf_add_local_field_group(array(
            'key'   => 'group_certification_logo',
            'title' => 'Certification Logo',
            'fields' => array(
                array(
                    'key'           => 'field_certification_logo_image',
                    'label'         => 'Logo Image',
                    'name'          => 'certification_logo',
                    'type'          => 'image',
                    'instructions'  => 'Upload the certification logo (transparent PNG preferred)',
                    'required'      => 0,
                    'return_format' => 'array',
                    'preview_size'  => 'thumbnail',
                    'library'       => 'all',
                ),
                array(
                    'key'           => 'field_cert_show_on_card',
                    'label'         => 'Show on Category Card',
                    'name'          => 'show_on_card',
                    'type'          => 'true_false',
                    'instructions'  => 'Display this logo on the ingredient card in the browse grid',
                    'required'      => 0,
                    'message'       => 'Show logo on ingredient card',
                    'default_value' => 0,
                    'ui'            => 1,
                ),
                array(
                    'key'           => 'field_cert_show_on_detail',
                    'label'         => 'Show on Detail Page',
                    'name'          => 'show_on_detail',
                    'type'          => 'true_false',
                    'instructions'  => 'Display this logo on the single ingredient detail page',
                    'required'      => 0,
                    'message'       => 'Show logo on ingredient detail page',
                    'default_value' => 0,
                    'ui'            => 1,
                ),
            ),
            'location' => array(
                array(
                    array(
                        'param'    => 'taxonomy',
                        'operator' => '==',
                        'value'    => 'fpc_certification',
                    ),
                ),
            ),
            'menu_order' => 0,
            'position'   => 'normal',
            'style'      => 'default',
        ));
    }

    /**
     * Representative Codes field group
     */
    private static function register_representative_codes() {
        acf_add_local_field_group(array(
            'key' => 'group_representative_codes',
            'title' => 'Sales Representative Routing',
            'fields' => array(
                array(
                    'key' => 'field_rep_code_primary',
                    'label' => 'Primary Representative Code',
                    'name' => 'rep_code_primary',
                    'type' => 'text',
                    'instructions' => 'Numerical code for primary sales representative',
                    'required' => 0,
                    'maxlength' => 10,
                ),
                array(
                    'key' => 'field_rep_code_secondary',
                    'label' => 'Secondary Representative Code',
                    'name' => 'rep_code_secondary',
                    'type' => 'text',
                    'instructions' => 'Optional secondary representative code',
                    'required' => 0,
                    'maxlength' => 10,
                ),
                array(
                    'key' => 'field_rep_notes',
                    'label' => 'Representative Notes',
                    'name' => 'rep_notes',
                    'type' => 'textarea',
                    'instructions' => 'Internal notes about representative routing',
                    'required' => 0,
                    'rows' => 3,
                ),
            ),
            'location' => array(
                array(
                    array(
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'fpc_ingredient',
                    ),
                ),
            ),
            'menu_order' => 2,
            'position' => 'side',
            'style' => 'default',
        ));
    }

    /**
     * Vendor fields (logo + caption text, on fpc_vendor taxonomy terms)
     */
    private static function register_vendor_fields() {
        acf_add_local_field_group(array(
            'key'   => 'group_vendor_fields',
            'title' => 'Vendor Details',
            'fields' => array(
                array(
                    'key'           => 'field_vendor_logo',
                    'label'         => 'Vendor Logo',
                    'name'          => 'vendor_logo',
                    'type'          => 'image',
                    'instructions'  => 'Upload the vendor logo (transparent PNG or SVG preferred)',
                    'required'      => 0,
                    'return_format' => 'array',
                    'preview_size'  => 'thumbnail',
                    'library'       => 'all',
                ),
                array(
                    'key'          => 'field_vendor_text',
                    'label'        => 'Caption',
                    'name'         => 'vendor_text',
                    'type'         => 'text',
                    'instructions' => 'Short line of text displayed beside the logo (e.g. "Exclusive distributor")',
                    'required'     => 0,
                    'maxlength'    => 120,
                ),
            ),
            'location' => array(
                array(
                    array(
                        'param'    => 'taxonomy',
                        'operator' => '==',
                        'value'    => 'fpc_vendor',
                    ),
                ),
            ),
            'menu_order' => 0,
            'position'   => 'normal',
            'style'      => 'default',
        ));
    }

    /**
     * Category Hero fields (on fpc_category taxonomy terms)
     */
    private static function register_category_hero() {
        acf_add_local_field_group(array(
            'key'   => 'group_category_hero',
            'title' => 'Category Hero',
            'fields' => array(
                array(
                    'key'           => 'field_category_hero_image',
                    'label'         => 'Hero Background Image',
                    'name'          => 'category_hero_image',
                    'type'          => 'image',
                    'instructions'  => 'Upload a hero/banner image displayed as the background of the hero section (recommended: 1600×500px or wider).',
                    'required'      => 0,
                    'return_format' => 'array',
                    'preview_size'  => 'medium',
                    'library'       => 'all',
                    'mime_types'    => 'jpg,jpeg,png,webp',
                ),
                array(
                    'key'          => 'field_category_hero_title',
                    'label'        => 'Hero Title',
                    'name'         => 'category_hero_title',
                    'type'         => 'text',
                    'instructions' => 'Override the title shown in the hero section. Leave blank to use the category name.',
                    'required'     => 0,
                    'maxlength'    => 120,
                ),
                array(
                    'key'          => 'field_category_hero_subtitle',
                    'label'        => 'Hero Description',
                    'name'         => 'category_hero_subtitle',
                    'type'         => 'textarea',
                    'instructions' => 'Paragraph shown beneath the hero title on the category archive page.',
                    'required'     => 0,
                    'rows'         => 3,
                    'maxlength'    => 500,
                    'new_lines'    => '',
                ),
                array(
                    'key'           => 'field_category_icon_svg',
                    'label'         => 'Category Icon (SVG)',
                    'name'          => 'category_icon_svg',
                    'type'          => 'file',
                    'instructions'  => 'Upload an SVG icon representing this category. Displayed on the single ingredient page alongside the product benefits.',
                    'required'      => 0,
                    'return_format' => 'array',
                    'library'       => 'all',
                    'mime_types'    => 'svg',
                ),
                array(
                    'key'           => 'field_category_grid_icon',
                    'label'         => 'Category Grid Icon',
                    'name'          => 'category_grid_icon',
                    'type'          => 'image',
                    'instructions'  => 'Upload the icon displayed on this category\'s card in the ingredient browse grid (recommended: square image, WEBP or PNG with transparent background).',
                    'required'      => 0,
                    'return_format' => 'array',
                    'preview_size'  => 'thumbnail',
                    'library'       => 'all',
                    'mime_types'    => 'jpg,jpeg,png,webp,svg',
                ),
            ),
            'location' => array(
                array(
                    array(
                        'param'    => 'taxonomy',
                        'operator' => '==',
                        'value'    => 'fpc_category',
                    ),
                ),
            ),
            'menu_order' => 0,
            'position'   => 'normal',
            'style'      => 'default',
        ));
    }

    /**
     * Main Settings options page — tabbed: Archive Hero + Email Settings
     */
    private static function register_archive_main_settings() {
        acf_add_local_field_group(array(
            'key'    => 'group_archive_main_settings',
            'title'  => 'Main Settings',
            'fields' => array(

                // ── Tab: Archive Hero ─────────────────────────────────────────
                array(
                    'key'   => 'field_tab_archive_hero',
                    'label' => 'Archive Hero',
                    'name'  => '',
                    'type'  => 'tab',
                ),
                array(
                    'key'          => 'field_archive_hero_title',
                    'label'        => 'Hero Title',
                    'name'         => 'archive_hero_title',
                    'type'         => 'text',
                    'instructions' => 'Heading displayed in the hero on /ingredients/. Leave blank to use the default.',
                    'placeholder'  => 'Our Ingredients, Your Sourcing Simplified.',
                    'required'     => 0,
                ),
                array(
                    'key'          => 'field_archive_hero_description',
                    'label'        => 'Hero Description',
                    'name'         => 'archive_hero_description',
                    'type'         => 'textarea',
                    'instructions' => 'Subtext displayed below the hero heading. Leave blank to use the default.',
                    'placeholder'  => 'Whether you are looking for proteins, texturants, sweeteners...',
                    'rows'         => 3,
                    'required'     => 0,
                ),

                // ── Tab: Email Settings ───────────────────────────────────────
                array(
                    'key'   => 'field_tab_email_settings',
                    'label' => 'Email Settings',
                    'name'  => '',
                    'type'  => 'tab',
                ),
                array(
                    'key'          => 'field_email_default',
                    'label'        => 'Default Email Address',
                    'name'         => 'default_email',
                    'type'         => 'email',
                    'instructions' => 'Used when no representative code is assigned.',
                    'required'     => 0,
                ),
                array(
                    'key'          => 'field_email_cc',
                    'label'        => 'CC Email Addresses',
                    'name'         => 'cc_emails',
                    'type'         => 'textarea',
                    'instructions' => 'One email per line. These addresses will be CC\'d on all submissions.',
                    'rows'         => 3,
                    'required'     => 0,
                ),
                array(
                    'key'          => 'field_email_rep_mapping',
                    'label'        => 'Representative Email Mapping',
                    'name'         => 'rep_email_mapping',
                    'type'         => 'textarea',
                    'instructions' => "Format: code|email@example.com (one per line)\nExample: 101|john@farbest.com",
                    'rows'         => 10,
                    'required'     => 0,
                ),
            ),
            'location' => array(array(array(
                'param'    => 'options_page',
                'operator' => '==',
                'value'    => 'fpc-main-settings',
            ))),
            'active' => true,
        ));
    }
}
