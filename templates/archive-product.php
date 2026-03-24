<?php
/**
 * Template for displaying product archive
 */

get_header();
?>

<div class="farbest-product-archive">

    <header class="page-header">
        <h1 class="page-title"><?php esc_html_e('Products', 'farbest-catalog'); ?></h1>

        <?php if (is_tax()) : ?>
            <div class="taxonomy-description">
                <?php the_archive_description(); ?>
            </div>
        <?php endif; ?>
    </header>

    <div class="archive-container">

        <!-- Sidebar with categories -->
        <aside class="product-sidebar">
            <div class="product-filters">

                <h3><?php esc_html_e('Categories', 'farbest-catalog'); ?></h3>
                <?php
                $categories = get_terms(array(
                    'taxonomy' => 'fpc_category',
                    'hide_empty' => true,
                ));

                if ($categories && !is_wp_error($categories)) :
                    ?>
                    <ul class="category-list">
                        <?php foreach ($categories as $category) : ?>
                            <li>
                                <a href="<?php echo esc_url(get_term_link($category)); ?>">
                                    <?php echo esc_html($category->name); ?>
                                    <span class="count">(<?php echo $category->count; ?>)</span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

            </div>
        </aside>

        <!-- Product Grid -->
        <main class="product-content">

            <!-- React component mount point -->
            <div id="farbest-product-grid"
                 data-initial-category="<?php echo is_tax('fpc_category') ? get_queried_object()->slug : ''; ?>">

                <!-- Fallback for non-JS -->
                <?php if (have_posts()) : ?>

                    <div class="products-grid">
                        <?php
                        while (have_posts()) :
                            the_post();
                            ?>

                            <article id="product-<?php the_ID(); ?>" <?php post_class('product-card'); ?>>

                                <?php if (has_post_thumbnail()) : ?>
                                    <a href="<?php the_permalink(); ?>" class="product-thumbnail">
                                        <?php the_post_thumbnail('medium'); ?>
                                    </a>
                                <?php endif; ?>

                                <div class="product-card-content">
                                    <h2 class="product-card-title">
                                        <a href="<?php the_permalink(); ?>">
                                            <?php the_title(); ?>
                                        </a>
                                    </h2>

                                    <?php
                                    $categories = get_the_terms(get_the_ID(), 'fpc_category');
                                    if ($categories && !is_wp_error($categories)) :
                                        ?>
                                        <div class="product-card-categories">
                                            <?php
                                            foreach ($categories as $category) {
                                                echo '<span class="category-tag">' . esc_html($category->name) . '</span>';
                                            }
                                            ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (has_excerpt()) : ?>
                                        <div class="product-card-excerpt">
                                            <?php the_excerpt(); ?>
                                        </div>
                                    <?php endif; ?>

                                    <a href="<?php the_permalink(); ?>" class="button product-card-button">
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

                    <div class="no-products-found">
                        <p><?php esc_html_e('No products found.', 'farbest-catalog'); ?></p>
                    </div>

                <?php endif; ?>

            </div>

        </main>

    </div>

</div>

<?php
get_footer();
