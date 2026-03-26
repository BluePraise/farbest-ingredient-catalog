<?php
/**
 * Template for displaying ingredient archive
 */

get_header();

$is_category_archive = is_tax('fpc_category');
$archive_title = $is_category_archive ? single_term_title('', false) : __('Our Ingredients, Your Sourcing Simplified.', 'farbest-catalog');
$archive_description = $is_category_archive
    ? wp_strip_all_tags(get_the_archive_description())
    : __('Whether you are looking for proteins, texturants, sweeteners, vitamins, natural colors, or something else, our selection of ingredients can solve your formulation needs.', 'farbest-catalog');
$cta_url = home_url('/contact/');
$initial_category = $is_category_archive ? get_queried_object()->slug : '';
?>

<div class="fpc-archive-page">
    <section class="fbd-hero">
        <div class="content-wrapper container">
            <div class="fbd-hero-inner">
                <div class="fbd-hero-text">
                    <h1 class="fbd-hero-title"><?php echo esc_html($archive_title); ?></h1>

                    <?php if (!empty($archive_description)) : ?>
                        <p class="fbd-hero-subtitle"><?php echo esc_html($archive_description); ?></p>
                    <?php endif; ?>
                </div>

                <div class="fbd-hero-cta">
                    <a href="<?php echo esc_url($cta_url); ?>" class="fbd-cta-button">
                        <?php esc_html_e('Get in Touch', 'farbest-catalog'); ?>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <div class="fbd-catalog-wrap">
        <div class="content-wrapper container">
            <main class="ingredient-content">
                <div id="farbest-ingredient-grid" data-initial-category="<?php echo esc_attr($initial_category); ?>">
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
</div>

<?php
get_footer();
