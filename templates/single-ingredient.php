<?php
/**
 * Template for displaying single ingredient
 */

get_header();

while (have_posts()) :
    the_post();
    $ingredient_id = get_the_ID();
    ?>

    <article id="ingredient-<?php the_ID(); ?>" <?php post_class('farbest-ingredient-single'); ?>>

        <div class="ingredient-container">

            <!-- Ingredient Header -->
            <header class="ingredient-header">
                <h1 class="ingredient-title"><?php the_title(); ?></h1>

                <?php
                $categories = get_the_terms($ingredient_id, 'fpc_category');
                if ($categories && !is_wp_error($categories)) :
                    ?>
                    <div class="ingredient-categories">
                        <?php
                        foreach ($categories as $category) {
                            echo '<span class="ingredient-category">' . esc_html($category->name) . '</span>';
                        }
                        ?>
                    </div>
                <?php endif; ?>
            </header>

            <!-- Ingredient Main Content -->
            <div class="ingredient-main">

                <!-- Ingredient Image -->
                <?php if (has_post_thumbnail()) : ?>
                    <div class="ingredient-image">
                        <?php the_post_thumbnail('large'); ?>
                    </div>
                <?php endif; ?>

                <!-- Ingredient Info -->
                <div class="ingredient-info">

                    <?php if (get_the_excerpt()) : ?>
                        <div class="ingredient-excerpt">
                            <?php the_excerpt(); ?>
                        </div>
                    <?php endif; ?>

                    <?php
                    $product_description = get_field('product_description');
                    if ($product_description) :
                        ?>
                        <div class="ingredient-description">
                            <?php echo wp_kses_post($product_description); ?>
                        </div>
                    <?php endif; ?>

                    <!-- Product Sheet Download -->
                    <?php
                    $product_sheet = get_field('product_sheet');
                    if ($product_sheet) :
                        ?>
                        <div class="ingredient-sheet">
                            <a href="<?php echo esc_url($product_sheet['url']); ?>"
                               class="button ingredient-sheet-button"
                               download>
                                <span class="dashicons dashicons-pdf"></span>
                                <?php esc_html_e('Download Product Sheet', 'farbest-catalog'); ?>
                            </a>
                        </div>
                    <?php endif; ?>

                </div>

            </div>

            <!-- Specifications -->
            <?php
            $show_specs = false;
            $spec_fields = array(
                'spec_protein_content' => __('Protein Content', 'farbest-catalog'),
                'spec_moisture'        => __('Moisture', 'farbest-catalog'),
                'spec_ph'              => __('pH', 'farbest-catalog'),
                'spec_solubility'      => __('Solubility', 'farbest-catalog'),
            );

            foreach ($spec_fields as $field => $label) {
                if (get_field($field)) {
                    $show_specs = true;
                    break;
                }
            }

            if ($show_specs) :
                ?>
                <div class="ingredient-specifications">
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
                    <div class="ingredient-applications">
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
                <div class="ingredient-packaging">
                    <h2><?php esc_html_e('Packaging', 'farbest-catalog'); ?></h2>
                    <p><?php echo esc_html($packaging); ?></p>
                </div>
            <?php endif; ?>

            <!-- Contact Form -->
            <div class="ingredient-contact-form">
                <h2><?php esc_html_e('Request More Information', 'farbest-catalog'); ?></h2>
                <?php echo do_shortcode('[fpc_contact_form product_id="' . $ingredient_id . '"]'); ?>
            </div>

        </div>

    </article>

    <?php
endwhile;

get_footer();
