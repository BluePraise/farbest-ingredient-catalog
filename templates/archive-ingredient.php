<?php
/**
 * Ingredient Archive Template (plugin canonical)
 *
 * Covers both the main fpc_ingredient archive (/ingredients/) and
 * fpc_category taxonomy archives.
 *
 * Loaded via template_include (FPC_Template_Loader) on the classic theme.
 */

get_header();

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

// Which zone the client's linked content page renders in on this category.
// Defaults to below_hero; the client changes it on the category screen.
$content_zone = 'below_hero';
if ( $is_category_archive && $queried_object && ! is_wp_error( $queried_object ) && function_exists( 'get_field' ) ) {
    $chosen_zone = (string) get_field( 'category_content_position', 'fpc_category_' . $queried_object->term_id );
    if ( 'below_results' === $chosen_zone ) {
        $content_zone = 'below_results';
    }
}

$option_title       = function_exists( 'get_field' ) ? (string) get_field( 'archive_hero_title', 'option' ) : '';
$option_description = function_exists( 'get_field' ) ? (string) get_field( 'archive_hero_description', 'option' ) : '';

$archive_title = $is_category_archive
    ? ( ! empty( $hero_title_override ) ? sanitize_text_field( $hero_title_override ) : single_term_title( '', false ) )
    : ( ! empty( $option_title )
        ? sanitize_text_field( $option_title )
        : __( 'Our Ingredients, Your Sourcing Simplified.', 'farbest-catalog' ) );

$archive_description = $is_category_archive
    ? ( ! empty( $hero_subtitle_override ) ? wp_strip_all_tags( $hero_subtitle_override ) : wp_strip_all_tags( get_the_archive_description() ) )
    : ( ! empty( $option_description )
        ? wp_strip_all_tags( $option_description )
        : __( 'Whether you are looking for proteins, texturants, sweeteners, vitamins, natural colors, or something else, our selection of ingredients can solve your formulation needs.', 'farbest-catalog' ) );

?>

<div class="fpc-archive-page">

    <?php if ( ! empty( $hero_image['url'] ) ) : ?>
        <div class="fpc-category-hero-img" style="background-image: url('<?php echo esc_url( $hero_image['url'] ); ?>')">
            <div class="fpc-category-hero-wave" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 41" preserveAspectRatio="none">
                    <path d="M1200,0c-175.18,7.24-316.7,8.33-411,8.08-188.32-.5-283.09-6.58-487-5.28C171.25,3.63,65.6,7.06,0,9.63v31.37h1200V0Z" fill="#f2efe9"/>
                </svg>
            </div>
        </div>
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

    <?php
    if ( 'below_hero' === $content_zone && $is_category_archive && $queried_object && ! is_wp_error( $queried_object ) ) {
        fpc_render_category_zone( $queried_object->term_id, 'below_hero' );
    }
    ?>

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
                            <?php if ( $is_category_archive ) : ?>
                                <p><?php esc_html_e( 'We haven’t added any ingredients to this category yet — check back soon.', 'farbest-catalog' ); ?></p>
                                <p><a class="button" href="<?php echo esc_url( get_post_type_archive_link( 'fpc_ingredient' ) ); ?>"><?php esc_html_e( 'Browse all ingredients', 'farbest-catalog' ); ?></a></p>
                            <?php else : ?>
                                <p><?php esc_html_e( 'No ingredients found.', 'farbest-catalog' ); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <?php
    // Client-owned content zone: renders the block content of the Page linked on
    // this category term (FAQ accordion, callouts, any Kadence blocks). Editable
    // by the client without a developer. See fpc_render_category_zone().
    if ( 'below_results' === $content_zone && $is_category_archive && $queried_object && ! is_wp_error( $queried_object ) ) {
        fpc_render_category_zone( $queried_object->term_id, 'below_results' );
    }
    ?>

</div><!-- .fpc-archive-page -->

<?php
get_footer();
?>
