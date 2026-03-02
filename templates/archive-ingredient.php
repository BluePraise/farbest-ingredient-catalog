<?php
/**
 * Template for displaying ingredient archive
 */

get_header();
?>

<div class="farbest-ingredient-archive">

    <header class="page-header">
        <h1 class="page-title"><?php esc_html_e('Ingredients', 'farbest-catalog'); ?></h1>

        <?php if (is_tax()) : ?>
            <div class="taxonomy-description">
                <?php the_archive_description(); ?>
            </div>
        <?php endif; ?>
    </header>

    <div class="archive-container">

        <!-- Ingredient Grid -->
        <main class="ingredient-content">

            <!-- React component mount point -->
            <div id="farbest-ingredient-grid"
                 data-initial-category="<?php echo is_tax('fpc_category') ? get_queried_object()->slug : ''; ?>">

                <!-- Fallback for non-JS -->
                <?php if (have_posts()) : ?>

                    <div class="ingredients-grid">
                        <?php
                        while (have_posts()) :
                            the_post();
                            ?>

                            <article id="ingredient-<?php the_ID(); ?>" <?php post_class('ingredient-card'); ?>>

                                <?php if (has_post_thumbnail()) : ?>
                                    <a href="<?php the_permalink(); ?>" class="ingredient-thumbnail">
                                        <?php the_post_thumbnail('medium'); ?>
                                    </a>
                                <?php endif; ?>

                                <div class="ingredient-card-content">
                                    <h2 class="ingredient-card-title">
                                        <a href="<?php the_permalink(); ?>">
                                            <?php the_title(); ?>
                                        </a>
                                    </h2>

                                    <?php
                                    $categories = get_the_terms(get_the_ID(), 'fpc_category');
                                    if ($categories && !is_wp_error($categories)) :
                                        ?>
                                        <div class="ingredient-card-categories">
                                            <?php
                                            foreach ($categories as $category) {
                                                echo '<span class="category-tag">' . esc_html($category->name) . '</span>';
                                            }
                                            ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (has_excerpt()) : ?>
                                        <div class="ingredient-card-excerpt">
                                            <?php the_excerpt(); ?>
                                        </div>
                                    <?php endif; ?>

                                    <a href="<?php the_permalink(); ?>" class="button ingredient-card-button">
                                        <?php esc_html_e('View Details', 'farbest-catalog'); ?>
                                    </a>
                                </div>

                            </article>

                        <?php endwhile; ?>
                    </div>

                    <?php
                    the_posts_pagination(array(
                        'mid_size' => 2,
                        'prev_text' => __('&laquo; Previous', 'farbest-catalog'),
                        'next_text' => __('Next &raquo;', 'farbest-catalog'),
                    ));
                    ?>

                <?php else : ?>

                    <div class="no-ingredients-found">
                        <p><?php esc_html_e('No ingredients found.', 'farbest-catalog'); ?></p>
                    </div>

                <?php endif; ?>

            </div>

        </main>

    </div>

</div>

<?php
get_footer();
