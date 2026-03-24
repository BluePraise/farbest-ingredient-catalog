<?php
/**
 * Register Custom Post Types
 */

if (!defined('ABSPATH')) {
    exit;
}

class FPC_Post_Types {

    /**
     * Register the ingredient post type
     */
    public static function register() {
        $labels = array(
            'name'                  => _x('Ingredients', 'Post Type General Name', 'farbest-catalog'),
            'singular_name'         => _x('Ingredient', 'Post Type Singular Name', 'farbest-catalog'),
            'menu_name'             => __('Ingredients', 'farbest-catalog'),
            'name_admin_bar'        => __('Ingredient', 'farbest-catalog'),
            'archives'              => __('Ingredient Archives', 'farbest-catalog'),
            'attributes'            => __('Ingredient Attributes', 'farbest-catalog'),
            'parent_item_colon'     => __('Parent Ingredient:', 'farbest-catalog'),
            'all_items'             => __('All Ingredients', 'farbest-catalog'),
            'add_new_item'          => __('Add New Ingredient', 'farbest-catalog'),
            'add_new'               => __('Add New', 'farbest-catalog'),
            'new_item'              => __('New Ingredient', 'farbest-catalog'),
            'edit_item'             => __('Edit Ingredient', 'farbest-catalog'),
            'update_item'           => __('Update Ingredient', 'farbest-catalog'),
            'view_item'             => __('View Ingredient', 'farbest-catalog'),
            'view_items'            => __('View Ingredients', 'farbest-catalog'),
            'search_items'          => __('Search Ingredients', 'farbest-catalog'),
            'not_found'             => __('Not found', 'farbest-catalog'),
            'not_found_in_trash'    => __('Not found in Trash', 'farbest-catalog'),
            'featured_image'        => __('Featured Image', 'farbest-catalog'),
            'set_featured_image'    => __('Set featured image', 'farbest-catalog'),
            'remove_featured_image' => __('Remove featured image', 'farbest-catalog'),
            'use_featured_image'    => __('Use as featured image', 'farbest-catalog'),
            'insert_into_item'      => __('Insert into ingredient', 'farbest-catalog'),
            'uploaded_to_this_item' => __('Uploaded to this ingredient', 'farbest-catalog'),
            'items_list'            => __('Ingredients list', 'farbest-catalog'),
            'items_list_navigation' => __('Ingredients list navigation', 'farbest-catalog'),
            'filter_items_list'     => __('Filter ingredients list', 'farbest-catalog'),
        );

        $args = array(
            'label'               => __('Ingredient', 'farbest-catalog'),
            'description'         => __('Farbest Ingredient Catalog', 'farbest-catalog'),
            'labels'              => $labels,
            'supports'            => array('title', 'editor', 'thumbnail', 'excerpt', 'revisions'),
            'taxonomies'          => array('fpc_category'),
            'hierarchical'        => false,
            'public'              => true,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'menu_position'       => 20,
            'menu_icon'           => 'dashicons-products',
            'show_in_admin_bar'   => true,
            'show_in_nav_menus'   => true,
            'can_export'          => true,
            'has_archive'         => true,
            'exclude_from_search' => false,
            'publicly_queryable'  => true,
            'capability_type'     => 'post',
            'show_in_rest'        => true,
            'rest_base'           => 'ingredients',
            'rewrite'             => array(
                'slug'       => 'ingredients',
                'with_front' => false,
            ),
        );

        register_post_type('fpc_ingredient', $args);
    }
}
