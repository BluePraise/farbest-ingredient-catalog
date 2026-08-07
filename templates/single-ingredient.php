<?php

/**
 * Single Ingredient Template (plugin canonical)
 *
 * This is the authoritative template for single fpc_ingredient pages.
 * Theme overrides are no longer used — all presentation logic lives here.
 *
 * Loaded via template_include (FPC_Template_Loader) on the classic theme.
 *
 * Plugin styles:    assets/css/catalog.css  (enqueued by plugin)
 * Tab behaviour:    assets/js/ingredient-tabs.js  (enqueued by plugin)
 * Layout styles:    css/ingredient-single.css  (enqueued by the theme)
 */

get_header();

the_post();

$ingredient_id = get_the_ID();

if ($ingredient_id) :

    // ── Taxonomy terms ───────────────────────────────────────────────────────

    $categories = wp_get_post_terms($ingredient_id, 'fpc_category');
    if (is_wp_error($categories) || ! is_array($categories)) {
        $categories = array();
    }

    $application_terms = wp_get_post_terms($ingredient_id, 'fpc_application', array('fields' => 'names'));
    if (is_wp_error($application_terms) || ! is_array($application_terms)) {
        $application_terms = array();
    }
    $app_list = array_values(array_filter(array_map('trim', $application_terms)));

    // ACF fallback: taxonomy field stores term IDs in post meta (return_format => 'id').
    // wp_get_post_terms() only has data after save_terms has run; get_field() always works.
    if (empty($app_list)) {
        $acf_app_value = get_field('product_applications');
        if (is_array($acf_app_value) && ! empty($acf_app_value)) {
            foreach ($acf_app_value as $term_id) {
                $term = get_term(intval($term_id), 'fpc_application');
                if ($term && ! is_wp_error($term)) {
                    $app_list[] = $term->name;
                }
            }
        } elseif (is_string($acf_app_value) && ! empty($acf_app_value)) {
            // Legacy: pipe-delimited text field before taxonomy migration.
            $app_list = array_values(array_filter(array_map('trim', explode('|', $acf_app_value))));
        }
    }

    $claim_terms = wp_get_post_terms($ingredient_id, 'fpc_claim', array('fields' => 'names'));
    if (is_wp_error($claim_terms) || ! is_array($claim_terms)) {
        $claim_terms = array();
    }

    $cert_terms = wp_get_post_terms($ingredient_id, 'fpc_certification');
    if (is_wp_error($cert_terms) || ! is_array($cert_terms)) {
        $cert_terms = array();
    }

    $vendor_terms = wp_get_post_terms($ingredient_id, 'fpc_vendor');
    if (is_wp_error($vendor_terms) || ! is_array($vendor_terms)) {
        $vendor_terms = array();
    }

    // ── ACF fields ───────────────────────────────────────────────────────────

    $packaging           = get_field('product_packaging');
    $product_sheet       = get_field('product_sheet');
    $product_description = get_field('product_description');
    // ── Vendor items: logo + caption text ───────────────────────────────────

    $vendor_items = array();
    foreach ($vendor_terms as $vt) {
        $logo = function_exists('get_field') ? get_field('vendor_logo', 'fpc_vendor_' . $vt->term_id) : null;
        $text = function_exists('get_field') ? get_field('vendor_text', 'fpc_vendor_' . $vt->term_id) : '';
        if (! empty($logo['url']) || ! empty($text)) {
            $vendor_items[] = array('logo' => $logo, 'text' => $text, 'name' => $vt->name);
        }
    }

    // ── Certification logos: detail page ─────────────────────────────────────

    $detail_logos = array();
    foreach ($cert_terms as $ct) {
        if (function_exists('get_field') && get_field('show_on_detail', 'fpc_certification_' . $ct->term_id)) {
            $dl = get_field('certification_logo', 'fpc_certification_' . $ct->term_id);
            if (! empty($dl['url'])) {
                $detail_logos[] = array('logo' => $dl, 'name' => $ct->name);
            }
        }
    }

    // ── Circle SVG icon ──────────────────────────────────────────────────────
    // Resolved ingredient override -> category -> ancestors -> theme default.
    // See FPC_Icons for the full order.

    $svg_content = FPC_Icons::get_ingredient_icon(get_the_ID());

    // ── Breadcrumb ───────────────────────────────────────────────────────────

    $breadcrumb_category = ! empty($categories) ? $categories[0] : null;
    $ingredients_url     = get_post_type_archive_link('fpc_ingredient');

?>

    <article id="ingredient-<?php the_ID(); ?>" <?php post_class('farbest-ingredient-single'); ?>>

        <div class="ingredient-container">

            <!-- Breadcrumb -->
            <nav class="ingredient-breadcrumb" aria-label="<?php esc_attr_e('Breadcrumb', 'farbest-catalog'); ?>">
                <a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home', 'farbest-catalog'); ?></a>
                <span class="ingredient-breadcrumb__sep" aria-hidden="true"> | </span>
                <a href="<?php echo esc_url($ingredients_url); ?>"><?php esc_html_e('Ingredients', 'farbest-catalog'); ?></a>
                <?php if ($breadcrumb_category) : ?>
                    <span class="ingredient-breadcrumb__sep" aria-hidden="true"> | </span>
                    <a href="<?php echo esc_url(get_term_link($breadcrumb_category)); ?>">
                        <?php echo esc_html($breadcrumb_category->name); ?>
                    </a>
                <?php endif; ?>
                <span class="ingredient-breadcrumb__sep" aria-hidden="true"> | </span>
                <span class="ingredient-breadcrumb__current" aria-current="page"><?php the_title(); ?></span>
            </nav>

            <!-- Header row: title + benefits (left) / category SVG + CTA (right) -->
            <div class="ingredient-header-row">
                <header class="ingredient-header">
                    <h1 class="ingredient-title"><?php the_title(); ?></h1>
                </header>
                <div class="ingredient-header-left">
                    <?php the_content(); ?>

                    <?php
                    // Benefits columns — ACF repeater, one column per group.
                    // Replaces the block theme's farbest/benefits-columns pattern,
                    // which has no equivalent in a classic theme.
                    $benefits_columns = get_field( 'benefits_columns', $ingredient_id );

                    if ( ! empty( $benefits_columns ) && is_array( $benefits_columns ) ) : ?>
                        <div class="farbest-benefits-columns">
                            <?php foreach ( $benefits_columns as $column ) :
                                $col_label = ! empty( $column['column_label'] ) ? $column['column_label'] : '';
                                $col_items = ! empty( $column['column_items'] ) ? $column['column_items'] : array();

                                if ( '' === $col_label && empty( $col_items ) ) {
                                    continue;
                                }
                                ?>
                                <div class="farbest-benefits-columns__col">
                                    <?php if ( '' !== $col_label ) : ?>
                                        <h3 class="farbest-benefits-columns__heading"><?php echo esc_html( $col_label ); ?></h3>
                                    <?php endif; ?>

                                    <?php if ( ! empty( $col_items ) ) : ?>
                                        <ul class="farbest-benefits-columns__list">
                                            <?php foreach ( $col_items as $item ) : ?>
                                                <?php if ( ! empty( $item['item_text'] ) ) : ?>
                                                    <li><?php echo esc_html( $item['item_text'] ); ?></li>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div><!-- .ingredient-header-left -->

                <!-- Right column: circle SVG + CTA -->
                <div class="ingredient-header-aside">

                    <?php // Class name kept for continuity; the icon may now come from the ingredient itself. ?>
                    <?php if (! empty($svg_content)) : ?>
                        <div class="ingredient-category-icon">
                            <?php echo $svg_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                            ?>
                        </div>
                    <?php endif; ?>

                    <a href="#" class="fbd-cta-button">
                        <?php esc_html_e('Get in Touch', 'farbest-catalog'); ?>
                    </a>

                </div><!-- .ingredient-header-aside -->

            </div><!-- .ingredient-header-row -->

            <!-- Certification logos (detail page only, filtered by show_on_detail ACF flag) -->
            <?php if (! empty($detail_logos)) : ?>
                <div class="ingredient-certifications__list">
                    <?php foreach ($detail_logos as $dl) : ?>
                        <img
                            src="<?php echo esc_url($dl['logo']['url']); ?>"
                            alt="<?php echo esc_attr(! empty($dl['logo']['alt']) ? $dl['logo']['alt'] : $dl['name']); ?>"
                            class="ingredient-certifications__logo"
                            loading="lazy">
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Main content: featured image + product sheet download -->
            <div class="ingredient-main">

                <?php if (has_post_thumbnail()) : ?>
                    <div class="ingredient-image">
                        <?php the_post_thumbnail('large'); ?>
                    </div>
                <?php endif; ?>

                <div class="ingredient-info">

                    <?php if ($product_sheet) : ?>
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

            </div><!-- .ingredient-main -->

            <!-- Tabbed content: Product Description / Product Details -->
            <div class="ingredient-tabs" id="ingredient-tabs-<?php the_ID(); ?>">

                <nav class="ingredient-tabs__nav" role="tablist">
                    <button
                        class="ingredient-tabs__tab ingredient-tabs__tab--active"
                        role="tab"
                        aria-selected="true"
                        aria-controls="tab-description-<?php the_ID(); ?>"
                        id="tab-btn-description-<?php the_ID(); ?>"
                        data-tab="description">
                        <?php esc_html_e('Product Description', 'farbest-catalog'); ?>
                    </button>
                    <button
                        class="ingredient-tabs__tab"
                        role="tab"
                        aria-selected="false"
                        aria-controls="tab-details-<?php the_ID(); ?>"
                        id="tab-btn-details-<?php the_ID(); ?>"
                        data-tab="details">
                        <?php esc_html_e('Product Details', 'farbest-catalog'); ?>
                    </button>
                </nav>

                <!-- Tab: Product Description -->
                <div
                    class="ingredient-tabs__panel ingredient-tabs__panel--active"
                    role="tabpanel"
                    id="tab-description-<?php the_ID(); ?>"
                    aria-labelledby="tab-btn-description-<?php the_ID(); ?>">
                    <?php if ($product_description) : ?>
                        <div class="ingredient-description">
                            <?php echo wp_kses_post($product_description); ?>
                        </div>
                    <?php else : ?>
                        <p class="ingredient-tabs__empty"><?php esc_html_e('No description available.', 'farbest-catalog'); ?></p>
                    <?php endif; ?>
                </div>

                <!-- Tab: Product Details -->
                <div
                    class="ingredient-tabs__panel"
                    role="tabpanel"
                    id="tab-details-<?php the_ID(); ?>"
                    aria-labelledby="tab-btn-details-<?php the_ID(); ?>"
                    hidden>
                    <table class="ingredient-details-table">
                        <tbody>

                            <?php if (! empty($app_list)) : ?>
                                <tr>
                                    <th><?php esc_html_e('Applications', 'farbest-catalog'); ?></th>
                                    <td>
                                        <?php foreach ($app_list as $app) : ?>
                                            <span><?php echo esc_html($app); ?></span><?php if ($app !== end($app_list)) : ?> | <?php endif; ?>
                                        <?php endforeach; ?>
                                    </td>
                                </tr>
                            <?php endif; ?>

                            <?php if (! empty($claim_terms)) : ?>
                                <tr>
                                    <th><?php esc_html_e('Label Claims', 'farbest-catalog'); ?></th>
                                    <td><?php echo esc_html(implode(' | ', $claim_terms)); ?></td>
                                </tr>
                            <?php endif; ?>

                            <?php if (! empty($cert_terms)) : ?>
                                <tr>
                                    <th><?php esc_html_e('Certifications', 'farbest-catalog'); ?></th>
                                    <td><?php echo esc_html(implode(' | ', wp_list_pluck($cert_terms, 'name'))); ?></td>
                                </tr>
                            <?php endif; ?>

                            <?php if ($packaging) : ?>
                                <tr>
                                    <th><?php esc_html_e('Packaging', 'farbest-catalog'); ?></th>
                                    <td><?php echo esc_html($packaging); ?></td>
                                </tr>
                            <?php endif; ?>

                        </tbody>
                    </table>
                </div>

            </div><!-- .ingredient-tabs -->

            <!-- Vendors: logo + caption text -->

            <?php if (! empty($vendor_items)) : ?>
                <div class="ingredient-vendors">
                    <?php foreach ($vendor_items as $vi) : ?>
                        <div class="ingredient-vendors__item">
                            <?php if (! empty($vi['logo']['url'])) : ?>
                                <img
                                    src="<?php echo esc_url($vi['logo']['url']); ?>"
                                    alt="<?php echo esc_attr(! empty($vi['logo']['alt']) ? $vi['logo']['alt'] : $vi['name']); ?>"
                                    class="ingredient-vendors__logo"
                                    loading="lazy">
                            <?php endif; ?>
                            <?php if (! empty($vi['text'])) : ?>
                                <span class="ingredient-vendors__text"><?php echo esc_html($vi['text']); ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Related products: same category, excludes current, show_on_card cert logos -->
            <?php
            $related_category = ! empty($categories) ? $categories[0] : null;

            if ($related_category) :
                $related_query = new WP_Query(array(
                    'post_type'      => 'fpc_ingredient',
                    'posts_per_page' => 4,
                    'post__not_in'   => array($ingredient_id),
                    'orderby'        => 'rand',
                    'tax_query'      => array(
                        array(
                            'taxonomy' => 'fpc_category',
                            'field'    => 'term_id',
                            'terms'    => $related_category->term_id,
                        ),
                    ),
                ));
            ?>
                <?php if ($related_query->have_posts()) : ?>
                    <div class="ingredient-related">
                        <h2 class="fpc-filter-label ingredient-related__heading">
                            <?php echo esc_html(
                                sprintf(
                                    /* translators: %s: category name */
                                    __('More %s Products', 'farbest-catalog'),
                                    $related_category->name
                                )
                            ); ?>
                        </h2>
                        <div class="fpc-ingredients-grid">
                            <?php while ($related_query->have_posts()) : $related_query->the_post(); ?>
                                <?php
                                $rel_id    = get_the_ID();
                                $rel_certs = wp_get_post_terms($rel_id, 'fpc_certification');
                                $rel_certs    = (! is_wp_error($rel_certs) && is_array($rel_certs)) ? $rel_certs : array();
                                ?>
                                <article class="fpc-ingredient-card">
                                    <div class="fpc-ingredient-card-content">
                                        <h3 class="fpc-ingredient-title"><?php the_title(); ?></h3>

                                        <?php
                                        $rel_benefits = fpc_extract_benefits($rel_id);
                                        if (! empty($rel_benefits)) : ?>
                                            <div class="fpc-ingredient-benefits">
                                                <?php foreach ($rel_benefits as $group) : ?>
                                                    <div class="fpc-ingredient-benefits__group">
                                                        <?php if (! empty($group['heading'])) : ?>
                                                            <p class="fpc-ingredient-benefits__heading"><?php echo esc_html($group['heading']); ?></p>
                                                        <?php endif; ?>
                                                        <?php if (! empty($group['items'])) : ?>
                                                            <ul class="fpc-ingredient-benefits__list">
                                                                <?php foreach ($group['items'] as $item) : ?>
                                                                    <li><?php echo esc_html($item); ?></li>
                                                                <?php endforeach; ?>
                                                            </ul>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (! empty($rel_certs)) :
                                            $rel_card_logos = array();
                                            foreach ($rel_certs as $rel_cert) {
                                                if (function_exists('get_field') && get_field('show_on_card', 'fpc_certification_' . $rel_cert->term_id)) {
                                                    $rel_logo = get_field('certification_logo', 'fpc_certification_' . $rel_cert->term_id);
                                                    if (! empty($rel_logo['url'])) {
                                                        $rel_card_logos[] = array('logo' => $rel_logo, 'name' => $rel_cert->name);
                                                    }
                                                }
                                            }
                                            if (! empty($rel_card_logos)) : ?>
                                                <div class="fpc-cert-logos">
                                                    <?php foreach ($rel_card_logos as $rcl) : ?>
                                                        <img
                                                            src="<?php echo esc_url($rcl['logo']['url']); ?>"
                                                            alt="<?php echo esc_attr(! empty($rcl['logo']['alt']) ? $rcl['logo']['alt'] : $rcl['name']); ?>"
                                                            class="fpc-cert-logo"
                                                            loading="lazy">
                                                    <?php endforeach; ?>
                                                </div>
                                        <?php endif;
                                        endif; ?>

                                        <a href="<?php the_permalink(); ?>" class="fpc-button">
                                            <?php esc_html_e('Product Details', 'farbest-catalog'); ?>
                                        </a>
                                    </div>
                                </article>
                            <?php endwhile;
                            wp_reset_postdata(); ?>
                        </div>
                        <div class="ingredient-related__footer" style="text-align:center;">
                            <a href="<?php echo esc_url(get_term_link($related_category)); ?>" class="fbd-cta-button">
                                <?php esc_html_e('See More Products', 'farbest-catalog'); ?>
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

        </div><!-- .ingredient-container -->

    </article>

<?php
endif;

get_footer();
?>