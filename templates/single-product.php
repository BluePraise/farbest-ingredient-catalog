<?php
/**
 * Template for displaying single product
 */

get_header();

while (have_posts()) :
    the_post();
    $product_id = get_the_ID();
    ?>
    
    <article id="product-<?php the_ID(); ?>" <?php post_class('farbest-product-single'); ?>>
        
        <div class="product-container">
            
            <!-- Product Header -->
            <header class="product-header">
                <h1 class="product-title"><?php the_title(); ?></h1>
                
                <?php
                $categories = get_the_terms($product_id, 'fpc_category');
                if ($categories && !is_wp_error($categories)) :
                    ?>
                    <div class="product-categories">
                        <?php
                        foreach ($categories as $category) {
                            echo '<span class="product-category">' . esc_html($category->name) . '</span>';
                        }
                        ?>
                    </div>
                <?php endif; ?>
            </header>
            
            <!-- Product Main Content -->
            <div class="product-main">
                
                <!-- Product Image -->
                <?php if (has_post_thumbnail()) : ?>
                    <div class="product-image">
                        <?php the_post_thumbnail('large'); ?>
                    </div>
                <?php endif; ?>
                
                <!-- Product Info -->
                <div class="product-info">
                    
                    <?php if (get_the_excerpt()) : ?>
                        <div class="product-excerpt">
                            <?php the_excerpt(); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php
                    $product_description = get_field('product_description');
                    if ($product_description) :
                        ?>
                        <div class="product-description">
                            <?php echo wp_kses_post($product_description); ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Product Sheet Download -->
                    <?php
                    $product_sheet = get_field('product_sheet');
                    if ($product_sheet) :
                        ?>
                        <div class="product-sheet">
                            <a href="<?php echo esc_url($product_sheet['url']); ?>" 
                               class="button product-sheet-button" 
                               download>
                                <span class="dashicons dashicons-pdf"></span>
                                <?php esc_html_e('Download Product Sheet', 'farbest-catalog'); ?>
                            </a>
                        </div>
                    <?php endif; ?>
                    
                </div>
                
            </div>
            
            <!-- Product Specifications -->
            <?php
            $show_specs = false;
            $spec_fields = array(
                'spec_protein_content' => __('Protein Content', 'farbest-catalog'),
                'spec_moisture' => __('Moisture', 'farbest-catalog'),
                'spec_ph' => __('pH', 'farbest-catalog'),
                'spec_solubility' => __('Solubility', 'farbest-catalog'),
            );
            
            foreach ($spec_fields as $field => $label) {
                if (get_field($field)) {
                    $show_specs = true;
                    break;
                }
            }
            
            if ($show_specs) :
                ?>
                <div class="product-specifications">
                    <h2><?php esc_html_e('Specifications', 'farbest-catalog'); ?></h2>
                    <table class="specs-table">
                        <?php
                        foreach ($spec_fields as $field => $label) {
                            $value = get_field($field);
                            if ($value) {
                                echo '<tr>';
                                echo '<th>' . esc_html($label) . '</th>';
                                echo '<td>' . esc_html($value) . '</td>';
                                echo '</tr>';
                            }
                        }
                        
                        // Additional specifications
                        if (have_rows('spec_additional')) {
                            while (have_rows('spec_additional')) {
                                the_row();
                                echo '<tr>';
                                echo '<th>' . esc_html(get_sub_field('spec_name')) . '</th>';
                                echo '<td>' . esc_html(get_sub_field('spec_value')) . '</td>';
                                echo '</tr>';
                            }
                        }
                        ?>
                    </table>
                </div>
            <?php endif; ?>
            
            <!-- Applications -->
            <?php
            $applications = get_field('product_applications');
            if ($applications) :
                $app_list = array_filter(array_map('trim', explode('|', $applications)));
                if (!empty($app_list)) :
                    ?>
                    <div class="product-applications">
                        <h2><?php esc_html_e('Proven Applications', 'farbest-catalog'); ?></h2>
                        <ul>
                            <?php foreach ($app_list as $app) : ?>
                                <li><?php echo esc_html($app); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <!-- Packaging -->
            <?php
            $packaging = get_field('product_packaging');
            if ($packaging) :
                ?>
                <div class="product-packaging">
                    <h2><?php esc_html_e('Packaging', 'farbest-catalog'); ?></h2>
                    <p><?php echo esc_html($packaging); ?></p>
                </div>
            <?php endif; ?>
            
            <!-- Contact Form -->
            <div class="product-contact-form">
                <h2><?php esc_html_e('Request More Information', 'farbest-catalog'); ?></h2>
                <?php echo do_shortcode('[fpc_contact_form product_id="' . $product_id . '"]'); ?>
            </div>
            
        </div>
        
    </article>
    
    <?php
endwhile;

get_footer();
