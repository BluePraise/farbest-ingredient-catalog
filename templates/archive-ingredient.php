<?php
/**
 * Ingredient Archive Template (plugin canonical)
 *
 * Covers both the main fpc_ingredient archive (/ingredients/) and
 * fpc_category taxonomy archives.
 *
 * In classic themes: loaded via template_include (FPC_Template_Loader).
 *   get_header() / get_footer() are called normally.
 *
 * In block themes: rendered via FPC_Template_Loader::render_archive(), which
 *   is the render_callback for the farbest/ingredient-archive server-side block.
 *   The block theme's HTML template provides the page wrapper; this file outputs
 *   only the inner content (hero + React mount point).
 */

if ( ! wp_is_block_theme() ) {
    get_header();
}

$is_category_archive = is_tax( 'fpc_category' );
$queried_object      = $is_category_archive ? get_queried_object() : null;
$cta_url             = home_url( '/contact/' );
$initial_category    = ( $is_category_archive && $queried_object && ! is_wp_error( $queried_object ) )
    ? $queried_object->slug
    : '';

// ── Hero content ─────────────────────────────────────────────────────────────
// Category archives pull title, subtitle, and background image from ACF term
// meta. The main ingredient archive uses the default copy below.

$hero_image             = null;
$hero_title_override    = '';
$hero_subtitle_override = '';

if ( $is_category_archive && $queried_object && ! is_wp_error( $queried_object ) && function_exists( 'get_field' ) ) {
    $term_key               = 'fpc_category_' . $queried_object->term_id;
    $hero_image             = get_field( 'category_hero_image', $term_key );
    $hero_title_override    = (string) get_field( 'category_hero_title', $term_key );
    $hero_subtitle_override = (string) get_field( 'category_hero_subtitle', $term_key );
}

$archive_title = $is_category_archive
    ? ( ! empty( $hero_title_override ) ? sanitize_text_field( $hero_title_override ) : single_term_title( '', false ) )
    : __( 'Our Ingredients, Your Sourcing Simplified.', 'farbest-catalog' );

$archive_description = $is_category_archive
    ? ( ! empty( $hero_subtitle_override ) ? wp_strip_all_tags( $hero_subtitle_override ) : wp_strip_all_tags( get_the_archive_description() ) )
    : __( 'Whether you are looking for proteins, texturants, sweeteners, vitamins, natural colors, or something else, our selection of ingredients can solve your formulation needs.', 'farbest-catalog' );

?>

<div class="fpc-archive-page">

    <?php if ( ! empty( $hero_image['url'] ) ) : ?>
        <div class="fpc-category-hero-img" style="background-image: url('<?php echo esc_url( $hero_image['url'] ); ?>')"></div>
    <?php endif; ?>

    <section class="fbd-hero">
        <div class="content-wrapper container">
            <div class="fbd-hero-inner">

                <div class="fbd-hero-text">
                    <h1 class="fbd-hero-title"><?php echo esc_html( $archive_title ); ?></h1>

                    <?php if ( ! empty( $archive_description ) ) : ?>
                        <p class="fbd-hero-subtitle"><?php echo esc_html( $archive_description ); ?></p>
                    <?php endif; ?>
                </div>

                <div class="fbd-hero-cta">
                    <a href="<?php echo esc_url( $cta_url ); ?>" class="fbd-cta-button">
                        <?php esc_html_e( 'Get in Touch', 'farbest-catalog' ); ?>
                    </a>
                </div>

            </div>
        </div>
    </section>

    <div class="fbd-catalog-wrap">
        <div class="content-wrapper container">
            <main class="ingredient-content">
                <div id="farbest-ingredient-grid" data-initial-category="<?php echo esc_attr( $initial_category ); ?>">
                    <?php if ( have_posts() ) : ?>
                        <div class="ingredients-grid">
                            <?php while ( have_posts() ) : the_post(); ?>
                                <article id="ingredient-<?php the_ID(); ?>" <?php post_class( 'ingredient-card' ); ?>>

                                    <?php if ( has_post_thumbnail() ) : ?>
                                        <a href="<?php the_permalink(); ?>" class="ingredient-thumbnail">
                                            <?php the_post_thumbnail( 'medium' ); ?>
                                        </a>
                                    <?php endif; ?>

                                    <div class="ingredient-card-content">
                                        <h2 class="ingredient-card-title">
                                            <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                                        </h2>

                                        <?php
                                        $card_categories = get_the_terms( get_the_ID(), 'fpc_category' );
                                        if ( $card_categories && ! is_wp_error( $card_categories ) ) : ?>
                                            <div class="ingredient-card-categories">
                                                <?php foreach ( $card_categories as $cat ) : ?>
                                                    <span class="category-tag"><?php echo esc_html( $cat->name ); ?></span>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ( has_excerpt() ) : ?>
                                            <div class="ingredient-card-excerpt">
                                                <?php the_excerpt(); ?>
                                            </div>
                                        <?php endif; ?>

                                        <a href="<?php the_permalink(); ?>" class="button ingredient-card-button">
                                            <?php esc_html_e( 'View Details', 'farbest-catalog' ); ?>
                                        </a>
                                    </div>

                                </article>
                            <?php endwhile; ?>
                        </div>

                        <?php the_posts_pagination( array(
                            'mid_size'  => 2,
                            'prev_text' => __( '&laquo; Previous', 'farbest-catalog' ),
                            'next_text' => __( 'Next &raquo;', 'farbest-catalog' ),
                        ) ); ?>

                    <?php else : ?>
                        <div class="no-ingredients-found">
                            <p><?php esc_html_e( 'No ingredients found.', 'farbest-catalog' ); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

</div><!-- .fpc-archive-page -->

<?php
if ( ! wp_is_block_theme() ) {
    get_footer();
}
?>
