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
            'rewrite'           => array(
                'slug'       => 'application',
                'with_front' => false,
            ),
        );

        register_taxonomy('fpc_application', array('fpc_ingredient'), $args);
    }
}
