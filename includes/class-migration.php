<?php
/**
 * WooCommerce to Custom Post Type Migration
 * WP-CLI Command: wp farbest migrate
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('WP_CLI') || !WP_CLI) {
    return;
}

class FPC_Migration {
    
    /**
     * Migrate WooCommerce products to custom post type
     *
     * ## OPTIONS
     *
     * [--dry-run]
     * : Run without making actual changes
     *
     * [--limit=<number>]
     * : Limit number of products to migrate
     *
     * ## EXAMPLES
     *
     *     wp farbest migrate --dry-run
     *     wp farbest migrate --limit=10
     *
     * @when after_wp_load
     */
    public function __invoke($args, $assoc_args) {
        $dry_run = isset($assoc_args['dry-run']);
        $limit = isset($assoc_args['limit']) ? intval($assoc_args['limit']) : -1;
        
        WP_CLI::log('Starting WooCommerce to Farbest Product Catalog migration...');
        
        if ($dry_run) {
            WP_CLI::warning('DRY RUN MODE - No changes will be made');
        }
        
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            WP_CLI::error('WooCommerce is not active. Cannot proceed with migration.');
        }
        
        // Get all WooCommerce products
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => $limit,
            'post_status' => 'any',
            'orderby' => 'ID',
            'order' => 'ASC',
        );
        
        $products = get_posts($args);
        $total = count($products);
        
        WP_CLI::log(sprintf('Found %d WooCommerce products to migrate', $total));
        
        $progress = \WP_CLI\Utils\make_progress_bar('Migrating products', $total);
        
        $migrated = 0;
        $skipped = 0;
        $errors = 0;
        
        foreach ($products as $product) {
            $result = $this->migrate_single_product($product, $dry_run);
            
            if ($result === true) {
                $migrated++;
            } elseif ($result === 'skipped') {
                $skipped++;
            } else {
                $errors++;
            }
            
            $progress->tick();
        }
        
        $progress->finish();
        
        WP_CLI::success(sprintf(
            'Migration complete: %d migrated, %d skipped, %d errors',
            $migrated,
            $skipped,
            $errors
        ));
        
        if (!$dry_run) {
            WP_CLI::log('Flushing rewrite rules...');
            flush_rewrite_rules();
        }
    }
    
    /**
     * Migrate a single product
     */
    private function migrate_single_product($wc_product_post, $dry_run = false) {
        $product_id = $wc_product_post->ID;
        
        // Check if already migrated
        $migrated_id = get_post_meta($product_id, '_fpc_migrated_to', true);
        if ($migrated_id && get_post($migrated_id)) {
            WP_CLI::log(sprintf('Product #%d already migrated to #%d', $product_id, $migrated_id));
            return 'skipped';
        }
        
        if ($dry_run) {
            WP_CLI::log(sprintf('[DRY RUN] Would migrate product #%d: %s', $product_id, $wc_product_post->post_title));
            return true;
        }
        
        try {
            // Create new product post
            $new_product_id = wp_insert_post(array(
                'post_title' => $wc_product_post->post_title,
                'post_content' => $wc_product_post->post_content,
                'post_excerpt' => $wc_product_post->post_excerpt,
                'post_status' => $wc_product_post->post_status,
                'post_type' => 'fpc_product',
                'post_author' => $wc_product_post->post_author,
                'post_date' => $wc_product_post->post_date,
                'menu_order' => $wc_product_post->menu_order,
            ), true);
            
            if (is_wp_error($new_product_id)) {
                WP_CLI::warning(sprintf('Failed to create product #%d: %s', $product_id, $new_product_id->get_error_message()));
                return false;
            }
            
            // Copy featured image
            $thumbnail_id = get_post_thumbnail_id($product_id);
            if ($thumbnail_id) {
                set_post_thumbnail($new_product_id, $thumbnail_id);
            }
            
            // Migrate categories
            $this->migrate_taxonomies($product_id, $new_product_id);
            
            // Migrate meta fields
            $this->migrate_meta_fields($product_id, $new_product_id);
            
            // Store migration reference
            update_post_meta($product_id, '_fpc_migrated_to', $new_product_id);
            update_post_meta($new_product_id, '_fpc_migrated_from', $product_id);
            
            WP_CLI::log(sprintf('✓ Migrated product #%d → #%d: %s', $product_id, $new_product_id, $wc_product_post->post_title));
            
            return true;
            
        } catch (Exception $e) {
            WP_CLI::warning(sprintf('Error migrating product #%d: %s', $product_id, $e->getMessage()));
            return false;
        }
    }
    
    /**
     * Migrate taxonomies
     */
    private function migrate_taxonomies($old_id, $new_id) {
        // Migrate product categories
        $categories = wp_get_post_terms($old_id, 'product_cat', array('fields' => 'names'));
        if (!empty($categories) && !is_wp_error($categories)) {
            wp_set_object_terms($new_id, $categories, 'fpc_category');
        }
        
        // Migrate product tags as claims (optional)
        $tags = wp_get_post_terms($old_id, 'product_tag', array('fields' => 'names'));
        if (!empty($tags) && !is_wp_error($tags)) {
            wp_set_object_terms($new_id, $tags, 'fpc_claim');
        }
    }
    
    /**
     * Migrate meta fields
     */
    private function migrate_meta_fields($old_id, $new_id) {
        // Get WooCommerce product
        $wc_product = wc_get_product($old_id);
        
        if (!$wc_product) {
            return;
        }
        
        // Migrate basic product data
        $description = $wc_product->get_description();
        if ($description) {
            update_field('product_description', $description, $new_id);
        }
        
        // Migrate custom meta (adjust field names as needed)
        $meta_mapping = array(
            '_product_sheet' => 'product_sheet',
            '_product_applications' => 'product_applications',
            '_product_packaging' => 'product_packaging',
            '_rep_code' => 'rep_code_primary',
        );
        
        foreach ($meta_mapping as $wc_meta => $acf_field) {
            $value = get_post_meta($old_id, $wc_meta, true);
            if ($value) {
                update_field($acf_field, $value, $new_id);
            }
        }
        
        // Copy all other meta (for backup)
        $all_meta = get_post_meta($old_id);
        foreach ($all_meta as $key => $value) {
            if (strpos($key, '_wc_') === 0 || strpos($key, '_product_') === 0) {
                update_post_meta($new_id, '_legacy_' . $key, maybe_unserialize($value[0]));
            }
        }
    }
}

// Register WP-CLI command
WP_CLI::add_command('farbest migrate', 'FPC_Migration');

/**
 * Seed fpc_category taxonomy from the legacy hardcoded ingredient category list.
 * Run once before executing the legacy migration.
 *
 * WP-CLI command: wp farbest seed-categories
 */
class FPC_Seed_Categories {

    /**
     * Seed fpc_category taxonomy terms from the hardcoded ingredient categories array.
     *
     * ## EXAMPLES
     *
     *     wp farbest seed-categories
     *
     * @when after_wp_load
     */
    public function __invoke($args, $assoc_args) {
        $categories = $this->get_ingredient_categories();
        $total = count($categories);

        WP_CLI::log("Seeding {$total} ingredient categories into fpc_category taxonomy...");

        $created  = 0;
        $skipped  = 0;
        $progress = \WP_CLI\Utils\make_progress_bar('Seeding categories', $total);

        foreach ($categories as $slug => $name) {
            $existing = get_term_by('slug', $slug, 'fpc_category');

            if ($existing) {
                WP_CLI::log("  Skipped (exists): {$name} [{$slug}]");
                $skipped++;
            } else {
                $result = wp_insert_term($name, 'fpc_category', array('slug' => $slug));

                if (is_wp_error($result)) {
                    WP_CLI::warning("  Failed to create term '{$name}': " . $result->get_error_message());
                } else {
                    WP_CLI::log("  Created: {$name} [{$slug}]");
                    $created++;
                }
            }

            $progress->tick();
        }

        $progress->finish();
        WP_CLI::success("Done. Created: {$created}, Skipped (already existed): {$skipped}");
    }

    /**
     * The canonical ingredient category map (slug => display name).
     * Mirrors get_ingredient_categories() in the legacy theme functions.php.
     */
    private function get_ingredient_categories() {
        return array(
            'ascorbic_acid'              => 'Ascorbic Acid',
            'beta_carotene'              => 'Beta-carotene',
            'calcium_ascorbate'          => 'Calcium Ascorbate',
            'calcium_caseinate'          => 'Calcium Caseinate',
            'calcium_d_pantothenate'     => 'Calcium d-Pantothenate',
            'caseinonly'                 => 'Casein',
            'colostrum'                  => 'Colostrum',
            'crystalline_fructose'       => 'Crystalline Fructose',
            'cyanocobalamin'             => 'Cyanocobalamin',
            'd_biotin'                   => 'd-Biotin',
            'fibers'                     => 'Fibers - Organic',
            'food_ingredients'           => 'Food Ingredients - Organic',
            'folic_acid'                 => 'Folic Acid',
            'fruits_&_fruit_powders'     => 'Fruits & Fruit Powders',
            'gum_acaciaonly'             => 'Gum Acacia',
            'gum_acacia_organic'         => 'Gum Acacia - Organic',
            'hydrolysates'               => 'Hydrolysates',
            'juice_&_concentrates'       => 'Juice & Concentrates - Organic',
            'lactoferrin'                => 'Lactoferrin',
            'lactoperoxidase'            => 'Lactoperoxidase',
            'lutein'                     => 'Lutein',
            'lycopene'                   => 'Lycopene',
            'methylcobalamin'            => 'Methylcobalamin',
            'milk_protein'               => 'Milk Protein',
            'monk_fruit'                 => 'Monk Fruit',
            'niacinonly'                 => 'Niacin',
            'niacinamide'                => 'Niacinamide',
            'nutrient_premixes_&_blends' => 'Nutrient Premixes & Blends',
            'pea_protein'                => 'Pea Protein',
            'plant_proteins_organic'     => 'Plant Proteins - Organic',
            'polydextrose'               => 'Polydextrose',
            'pyridoxine'                 => 'Pyridoxine',
            'riboflavin'                 => 'Riboflavin',
            'rice_protein'               => 'Rice Protein',
            'sodium_ascorbate'           => 'Sodium Ascorbate',
            'sodium_caseinate'           => 'Sodium Caseinate',
            'soy_protein'                => 'Soy Protein',
            'specialty'                  => 'Specialty Caseinate',
            'supplements'                => 'Supplements',
            'sweeteners'                 => 'Sweeteners',
            'thiamine'                   => 'Thiamine',
            'vitamin_a'                  => 'Vitamin A',
            'vitamin_d'                  => 'Vitamin D',
            'vitamin_e'                  => 'Vitamin E',
            'vitamin_k'                  => 'Vitamin K',
            'whey_protein'               => 'Whey Protein',
            'weighting_agents'           => 'Weighting Agents',
            'pea_protein_ngpv'           => 'Pea Protein NGPV',
            'gum_acacia_ngpv'            => 'Gum Acacia NGPV',
            'soy_protein_ngpv'           => 'Soy Protein NGPV',
            'lecithins_ngpv'             => 'Lecithin NGPV',
            'sweeteners_ngpv'            => 'Sweeteners NGPV',
            'fiber_ngpv'                 => 'Fiber NGPV',
            'dairy_protein_ngpv'         => 'Dairy Protein NGPV',
            'omega_3_fish_oil'           => 'Omega-3 Fish Oil',
            'lecithin'                   => 'Lecithin',
            'sweeteners_organic'         => 'Sweeteners Organic',
        );
    }
}

WP_CLI::add_command('farbest seed-categories', 'FPC_Seed_Categories');

/**
 * Migrate products from the legacy 'products' CPT (theme-based system)
 * to the new 'fpc_ingredient' CPT (plugin-based system).
 *
 * WP-CLI command: wp farbest migrate-legacy
 *
 * Data mapping:
 *   post_title, post_content, post_excerpt, thumbnail → fpc_ingredient standard fields
 *   products_categories (meta array of slugs) → fpc_category taxonomy terms
 *   products_description (meta) → ACF product_description
 *   products_applications (meta) → ACF product_applications
 *   products_packaging (meta) → ACF product_packaging
 *   products_order (meta) → ACF display_order
 *   claim taxonomy → fpc_claim taxonomy (same term names)
 *   certification taxonomy → fpc_certification taxonomy (same term names)
 */
class FPC_Legacy_Migration {

    /**
     * Migrate legacy 'products' CPT posts to 'fpc_product'.
     *
     * ## OPTIONS
     *
     * [--dry-run]
     * : Preview what would be migrated without making changes.
     *
     * [--limit=<number>]
     * : Limit to first N products (useful for testing).
     *
     * [--skip-existing]
     * : Skip products already marked as migrated (default: true).
     *
     * ## EXAMPLES
     *
     *     wp farbest migrate-legacy --dry-run
     *     wp farbest migrate-legacy --limit=10
     *     wp farbest migrate-legacy
     *
     * @when after_wp_load
     */
    public function __invoke($args, $assoc_args) {
        $dry_run = isset($assoc_args['dry-run']);
        $limit   = isset($assoc_args['limit']) ? intval($assoc_args['limit']) : -1;

        if ($dry_run) {
            WP_CLI::warning('DRY RUN MODE — No changes will be made.');
        }

        // Verify fpc_category taxonomy exists (run seed-categories first)
        if (!taxonomy_exists('fpc_category')) {
            WP_CLI::error('fpc_category taxonomy not found. Is the Farbest Product Catalog plugin active?');
        }

        $query_args = array(
            'post_type'      => 'products',
            'posts_per_page' => $limit,
            'post_status'    => array('publish', 'draft'),
            'orderby'        => 'ID',
            'order'          => 'ASC',
        );

        $products = get_posts($query_args);
        $total    = count($products);

        WP_CLI::log("Found {$total} legacy products to migrate.");

        $progress  = \WP_CLI\Utils\make_progress_bar('Migrating', $total);
        $migrated  = 0;
        $skipped   = 0;
        $errors    = 0;

        foreach ($products as $product) {
            $result = $this->migrate_product($product, $dry_run);

            if ($result === 'migrated')  { $migrated++; }
            elseif ($result === 'skipped') { $skipped++; }
            else                           { $errors++; }

            $progress->tick();
        }

        $progress->finish();

        WP_CLI::success(sprintf(
            'Migration complete — Migrated: %d, Skipped: %d, Errors: %d',
            $migrated, $skipped, $errors
        ));

        if (!$dry_run) {
            flush_rewrite_rules();
            WP_CLI::log('Rewrite rules flushed.');
        }
    }

    /**
     * Migrate a single legacy product post.
     *
     * @return string 'migrated' | 'skipped' | 'error'
     */
    private function migrate_product($post, $dry_run) {
        $old_id = $post->ID;

        // Skip if already migrated
        $existing_new_id = get_post_meta($old_id, '_fpc_migrated_to', true);
        if ($existing_new_id && get_post($existing_new_id)) {
            WP_CLI::log("  Skip #{$old_id} '{$post->post_title}' (already migrated → #{$existing_new_id})");
            return 'skipped';
        }

        if ($dry_run) {
            WP_CLI::log("  [DRY RUN] Would migrate #{$old_id}: {$post->post_title}");
            return 'migrated';
        }

        try {
            // 1. Create fpc_ingredient post
            $new_id = wp_insert_post(array(
                'post_type'    => 'fpc_ingredient',
                'post_title'   => $post->post_title,
                'post_content' => $post->post_content,
                'post_excerpt' => $post->post_excerpt,
                'post_status'  => $post->post_status,
                'post_author'  => $post->post_author,
                'post_date'    => $post->post_date,
                'menu_order'   => $post->menu_order,
            ), true);

            if (is_wp_error($new_id)) {
                WP_CLI::warning("  Error creating ingredient #{$old_id}: " . $new_id->get_error_message());
                return 'error';
            }

            // 2. Featured image
            $thumbnail_id = get_post_thumbnail_id($old_id);
            if ($thumbnail_id) {
                set_post_thumbnail($new_id, $thumbnail_id);
            }

            // 3. Map products_categories meta → fpc_category taxonomy terms
            $this->migrate_categories($old_id, $new_id);

            // 4. Map claim / certification taxonomies
            $this->migrate_taxonomy($old_id, $new_id, 'claim',         'fpc_claim');
            $this->migrate_taxonomy($old_id, $new_id, 'certification', 'fpc_certification');

            // 5. Map ACF meta fields
            $this->migrate_acf_fields($old_id, $new_id);

            // 6. Store migration references
            update_post_meta($old_id, '_fpc_migrated_to',   $new_id);
            update_post_meta($new_id, '_fpc_migrated_from', $old_id);

            WP_CLI::log("  ✓ #{$old_id} → #{$new_id}: {$post->post_title}");
            return 'migrated';

        } catch (Exception $e) {
            WP_CLI::warning("  Exception migrating #{$old_id}: " . $e->getMessage());
            return 'error';
        }
    }

    /**
     * Map products_categories post meta (array of slugs) → fpc_category taxonomy.
     * Looks up each slug in fpc_category; if not found, creates the term on the fly.
     */
    private function migrate_categories($old_id, $new_id) {
        $slugs = get_post_meta($old_id, 'products_categories', true);
        if (empty($slugs) || !is_array($slugs)) {
            return;
        }

        $term_ids = array();
        foreach ($slugs as $slug) {
            $slug = sanitize_title($slug);
            $term = get_term_by('slug', $slug, 'fpc_category');

            if (!$term) {
                // Term not seeded yet — insert it with a placeholder name
                $result = wp_insert_term(ucwords(str_replace('_', ' ', $slug)), 'fpc_category', array('slug' => $slug));
                if (!is_wp_error($result)) {
                    $term_ids[] = $result['term_id'];
                    WP_CLI::log("    Auto-created fpc_category term '{$slug}'");
                }
            } else {
                $term_ids[] = $term->term_id;
            }
        }

        if (!empty($term_ids)) {
            wp_set_object_terms($new_id, $term_ids, 'fpc_category');
        }
    }

    /**
     * Copy terms from one taxonomy to another by matching names.
     * Creates target terms if they don't exist yet.
     */
    private function migrate_taxonomy($old_id, $new_id, $source_tax, $target_tax) {
        $terms = get_the_terms($old_id, $source_tax);
        if (empty($terms) || is_wp_error($terms)) {
            return;
        }

        $target_term_ids = array();
        foreach ($terms as $term) {
            $existing = get_term_by('name', $term->name, $target_tax);

            if (!$existing) {
                $result = wp_insert_term($term->name, $target_tax, array('slug' => $term->slug));
                if (!is_wp_error($result)) {
                    $target_term_ids[] = $result['term_id'];
                }
            } else {
                $target_term_ids[] = $existing->term_id;
            }
        }

        if (!empty($target_term_ids)) {
            wp_set_object_terms($new_id, $target_term_ids, $target_tax);
        }
    }

    /**
     * Map legacy post meta fields to ACF fields on the new product.
     */
    private function migrate_acf_fields($old_id, $new_id) {
        $meta_to_acf = array(
            'products_description'  => 'product_description',
            'products_applications' => 'product_applications',
            'products_packaging'    => 'product_packaging',
            'products_order'        => 'display_order',
        );

        foreach ($meta_to_acf as $meta_key => $acf_key) {
            $value = get_post_meta($old_id, $meta_key, true);
            if ($value !== '' && $value !== false && $value !== null) {
                if (function_exists('update_field')) {
                    update_field($acf_key, $value, $new_id);
                } else {
                    update_post_meta($new_id, $acf_key, $value);
                }
            }
        }

        // Rep code — may be stored under various meta keys; check common ones
        $rep_keys = array('products_rep_code', 'rep_code', '_rep_code');
        foreach ($rep_keys as $key) {
            $rep_code = get_post_meta($old_id, $key, true);
            if (!empty($rep_code)) {
                if (function_exists('update_field')) {
                    update_field('rep_code_primary', $rep_code, $new_id);
                } else {
                    update_post_meta($new_id, 'rep_code_primary', $rep_code);
                }
                break;
            }
        }
    }
}

WP_CLI::add_command('farbest migrate-legacy', 'FPC_Legacy_Migration');

/**
 * Rename existing fpc_product posts to fpc_ingredient in the database.
 * Run this once if you already have migrated data stored as fpc_product.
 *
 * WP-CLI command: wp farbest rename-posttype
 */
class FPC_Rename_PostType {

    /**
     * Rename all fpc_product posts to fpc_ingredient.
     *
     * ## OPTIONS
     *
     * [--dry-run]
     * : Preview the count without making changes.
     *
     * ## EXAMPLES
     *
     *     wp farbest rename-posttype --dry-run
     *     wp farbest rename-posttype
     *
     * @when after_wp_load
     */
    public function __invoke($args, $assoc_args) {
        global $wpdb;

        $dry_run = isset($assoc_args['dry-run']);

        $count = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'fpc_product'"
        );

        if ($count === 0) {
            WP_CLI::success('No fpc_product posts found. Nothing to rename.');
            return;
        }

        WP_CLI::log("Found {$count} posts with post_type 'fpc_product'.");

        if ($dry_run) {
            WP_CLI::warning("DRY RUN — Would rename {$count} posts to 'fpc_ingredient'. No changes made.");
            return;
        }

        $updated = $wpdb->update(
            $wpdb->posts,
            array('post_type' => 'fpc_ingredient'),
            array('post_type' => 'fpc_product'),
            array('%s'),
            array('%s')
        );

        if ($updated === false) {
            WP_CLI::error('Database update failed: ' . $wpdb->last_error);
        }

        flush_rewrite_rules();
        WP_CLI::success("Renamed {$updated} posts from 'fpc_product' to 'fpc_ingredient'. Rewrite rules flushed.");
    }
}

WP_CLI::add_command('farbest rename-posttype', 'FPC_Rename_PostType');
