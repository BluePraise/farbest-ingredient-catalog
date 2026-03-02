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
        add_action('acf/init', array(__CLASS__, 'register_field_groups'));
    }
    
    /**
     * Register all field groups
     */
    public static function register_field_groups() {
        if (!function_exists('acf_add_local_field_group')) {
            return;
        }
        
        self::register_product_details();
        self::register_product_specifications();
        self::register_representative_codes();
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
                    'key' => 'field_product_applications',
                    'label' => 'Proven Applications',
                    'name' => 'product_applications',
                    'type' => 'textarea',
                    'instructions' => 'List proven applications (one per line or separated by |)',
                    'required' => 0,
                    'rows' => 4,
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
                        'value' => 'fpc_product',
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
     * Product Specifications field group
     */
    private static function register_product_specifications() {
        acf_add_local_field_group(array(
            'key' => 'group_product_specifications',
            'title' => 'Product Specifications',
            'fields' => array(
                array(
                    'key' => 'field_spec_protein_content',
                    'label' => 'Protein Content',
                    'name' => 'spec_protein_content',
                    'type' => 'text',
                    'instructions' => 'e.g., "80% min"',
                ),
                array(
                    'key' => 'field_spec_moisture',
                    'label' => 'Moisture',
                    'name' => 'spec_moisture',
                    'type' => 'text',
                    'instructions' => 'e.g., "5% max"',
                ),
                array(
                    'key' => 'field_spec_ph',
                    'label' => 'pH',
                    'name' => 'spec_ph',
                    'type' => 'text',
                    'instructions' => 'e.g., "6.5-7.5"',
                ),
                array(
                    'key' => 'field_spec_solubility',
                    'label' => 'Solubility',
                    'name' => 'spec_solubility',
                    'type' => 'text',
                ),
                array(
                    'key' => 'field_spec_additional',
                    'label' => 'Additional Specifications',
                    'name' => 'spec_additional',
                    'type' => 'repeater',
                    'instructions' => 'Add custom specification fields',
                    'button_label' => 'Add Specification',
                    'sub_fields' => array(
                        array(
                            'key' => 'field_spec_name',
                            'label' => 'Specification Name',
                            'name' => 'spec_name',
                            'type' => 'text',
                            'required' => 1,
                        ),
                        array(
                            'key' => 'field_spec_value',
                            'label' => 'Value',
                            'name' => 'spec_value',
                            'type' => 'text',
                            'required' => 1,
                        ),
                    ),
                ),
            ),
            'location' => array(
                array(
                    array(
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'fpc_product',
                    ),
                ),
            ),
            'menu_order' => 1,
            'position' => 'normal',
            'style' => 'default',
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
                        'value' => 'fpc_product',
                    ),
                ),
            ),
            'menu_order' => 2,
            'position' => 'side',
            'style' => 'default',
        ));
    }
}
