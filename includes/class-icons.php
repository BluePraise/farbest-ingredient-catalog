<?php
/**
 * Circle icon resolution for the single ingredient page.
 *
 * Nearly every ingredient shares one icon with the rest of its category, so the
 * icon is uploaded once on the category term. Products that need a different
 * one are handled with a subcategory — a Soy child term under Plant Protein
 * carrying its own icon — not with a per-ingredient field. There is
 * deliberately no way to set an icon on an individual ingredient: one editing
 * surface, and the icon always follows the category tree.
 *
 * Resolution order, first hit wins:
 *
 *   1. `category_icon_circle` on the ingredient's most specific category term
 *   2. the same field on each ancestor of that term        — inheritance
 *   3. {theme}/images/categories/{slug}.svg                — filesystem default
 *   4. '' (caller reserves the space and renders nothing)
 *
 * Step 2 is what makes subcategories cheap: a child term without its own icon
 * falls through to its parent, so only the ones that genuinely differ need an
 * upload.
 *
 * Note step 3's directory does not exist in the theme yet, so it never fires
 * today. It is kept as the zero-admin way to ship defaults with the theme.
 *
 * @package farbest-product-catalog
 */

if (!defined('ABSPATH')) {
    exit;
}

class FPC_Icons {

    /** Taxonomy the category-level icon hangs off. */
    const TAXONOMY = 'fpc_category';

    /** ACF field on an `fpc_category` term. */
    const CATEGORY_FIELD = 'category_icon_circle';

    /**
     * Inline SVG markup for an ingredient's circle icon.
     *
     * Returned as markup rather than a URL so the icon inherits colour and
     * sizing from the page's CSS.
     *
     * @param int|null $post_id Ingredient to resolve for. Defaults to the current post.
     * @return string SVG markup, or '' when nothing resolves.
     */
    public static function get_ingredient_icon($post_id = null) {
        $post_id = $post_id ? (int) $post_id : (int) get_the_ID();
        if (!$post_id) {
            return '';
        }

        $term = self::most_specific_category($post_id);

        // 1. + 2. The term itself, then each ancestor in turn.
        for ($t = $term; $t instanceof WP_Term; $t = self::parent_term($t)) {
            $svg = self::read_attachment_svg(self::field(self::CATEGORY_FIELD, self::TAXONOMY . '_' . $t->term_id));
            if ('' !== $svg) {
                return $svg;
            }
        }

        // 3. Theme-provided default, keyed to the category slug.
        return $term instanceof WP_Term ? self::read_theme_svg($term->slug) : '';
    }

    /**
     * The deepest `fpc_category` term assigned to an ingredient.
     *
     * Ingredients can carry several categories. Preferring the most deeply
     * nested one means a subcategory icon wins over its parent's, and it also
     * makes the choice deterministic rather than "whichever term came back
     * first", which is how the previous implementation picked.
     *
     * @param int $post_id Ingredient ID.
     * @return WP_Term|null
     */
    private static function most_specific_category($post_id) {
        $terms = get_the_terms($post_id, self::TAXONOMY);
        if (empty($terms) || is_wp_error($terms)) {
            return null;
        }

        $best       = null;
        $best_depth = -1;

        foreach ($terms as $term) {
            $depth = count(get_ancestors($term->term_id, self::TAXONOMY, 'taxonomy'));
            if ($depth > $best_depth) {
                $best       = $term;
                $best_depth = $depth;
            }
        }

        return $best;
    }

    /**
     * A term's parent, or null at the top of the tree.
     *
     * @param WP_Term $term Term to walk up from.
     * @return WP_Term|null
     */
    private static function parent_term(WP_Term $term) {
        if (empty($term->parent)) {
            return null;
        }

        $parent = get_term($term->parent, self::TAXONOMY);
        return ($parent instanceof WP_Term) ? $parent : null;
    }

    /**
     * Read an ACF field, tolerating ACF being inactive.
     *
     * @param string     $name   Field name.
     * @param int|string $object Post ID, or an ACF object identifier such as `fpc_category_14`.
     * @return mixed
     */
    private static function field($name, $object) {
        return function_exists('get_field') ? get_field($name, $object) : null;
    }

    /**
     * Markup of an SVG attachment selected through an ACF file field.
     *
     * @param mixed $value ACF file field value (array return format).
     * @return string
     */
    private static function read_attachment_svg($value) {
        if (empty($value['ID'])) {
            return '';
        }

        $file = get_attached_file((int) $value['ID']);
        if (!$file || !is_readable($file)) {
            return '';
        }

        // The ACF field restricts uploads to SVG, but the attachment ID could
        // have been set before that restriction existed.
        if ('svg' !== strtolower(pathinfo($file, PATHINFO_EXTENSION))) {
            return '';
        }

        return self::read_file($file);
    }

    /**
     * Markup of a theme-provided default icon for a category slug.
     *
     * @param string $slug Category slug.
     * @return string
     */
    private static function read_theme_svg($slug) {
        $path = get_template_directory() . '/images/categories/' . sanitize_file_name($slug) . '.svg';
        return file_exists($path) ? self::read_file($path) : '';
    }

    /**
     * @param string $path Absolute path to an SVG file.
     * @return string
     */
    private static function read_file($path) {
        $markup = file_get_contents($path); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        return is_string($markup) ? trim($markup) : '';
    }
}
