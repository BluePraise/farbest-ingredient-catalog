<?php
/**
 * URL routing for the ingredient catalog.
 *
 * Everything lives under /ingredients/:
 *
 *   /ingredients/                      CPT archive (all ingredients)
 *   /ingredients/gum-acacia/           fpc_category term archive
 *   /ingredients/gum-acacia/organic/   child term (subcategory) archive
 *   /ingredients/beyond-acacia-339/    single ingredient (flat, never nested)
 *
 * Ingredient permalinks stay flat on purpose: they do not change when an
 * ingredient is re-categorised, and it keeps the second path segment free for
 * the category hierarchy (a nested ingredient URL would collide with a
 * subcategory of the same depth).
 *
 * Because a single segment under /ingredients/ is ambiguous — it could be a
 * category or an ingredient — WordPress's own generated rules cannot
 * disambiguate reliably (whichever permastruct is registered first wins). So we
 * capture the whole path ourselves and resolve it: category first, then
 * ingredient, else 404. Category slugs must therefore not collide with
 * ingredient slugs; the category wins if they ever do.
 *
 * Rewrite rules are flushed on plugin activation. If these rules are changed,
 * flush again (wp rewrite flush) or the old routes stay cached.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FPC_Rewrites {

    /** Base path shared by the archive, categories and single ingredients. */
    const BASE = 'ingredients';

    /** Query var holding the raw path captured under the base. */
    const PATH_VAR = 'fpc_path';

    public static function init() {
        add_action( 'init', array( __CLASS__, 'add_rules' ), 20 );
        add_filter( 'query_vars', array( __CLASS__, 'add_query_var' ) );
        add_filter( 'request', array( __CLASS__, 'resolve_request' ) );
    }

    /**
     * Register the catch-all routes.
     *
     * add_rewrite_rule( ..., 'top' ) prepends, so these are added in reverse
     * priority order — the last one added is matched first.
     */
    public static function add_rules() {
        $base = self::BASE;

        // Lowest priority: any path under the base (category, subcategory, or ingredient).
        add_rewrite_rule( '^' . $base . '/(.+?)/?$', 'index.php?' . self::PATH_VAR . '=$matches[1]', 'top' );

        // Paginated term archive: /ingredients/gum-acacia/page/2/
        add_rewrite_rule(
            '^' . $base . '/(.+?)/page/([0-9]{1,})/?$',
            'index.php?' . self::PATH_VAR . '=$matches[1]&paged=$matches[2]',
            'top'
        );

        // Highest priority: paginated main archive — /ingredients/page/2/ must not
        // be read as a term path of "page/2".
        add_rewrite_rule(
            '^' . $base . '/page/([0-9]{1,})/?$',
            'index.php?post_type=fpc_ingredient&paged=$matches[1]',
            'top'
        );
    }

    /**
     * @param array $vars Public query vars.
     * @return array
     */
    public static function add_query_var( $vars ) {
        $vars[] = self::PATH_VAR;
        return $vars;
    }

    /**
     * Turn the captured path into a real query: category archive, single
     * ingredient, or 404.
     *
     * @param array $query_vars Parsed query vars.
     * @return array
     */
    public static function resolve_request( $query_vars ) {
        if ( empty( $query_vars[ self::PATH_VAR ] ) ) {
            return $query_vars;
        }

        $path = trim( (string) $query_vars[ self::PATH_VAR ], '/' );
        unset( $query_vars[ self::PATH_VAR ] );

        if ( '' === $path ) {
            return $query_vars;
        }

        $segments  = array_filter( explode( '/', $path ) );
        $last_slug = (string) end( $segments );

        // 1. Category or subcategory? The final segment identifies the term; the
        //    hierarchy above it is descriptive, so /a/b/ and /b/ both resolve to b.
        $term = get_term_by( 'slug', $last_slug, 'fpc_category' );
        if ( $term && ! is_wp_error( $term ) ) {
            $query_vars['fpc_category'] = $last_slug;
            return $query_vars;
        }

        // 2. Single ingredient — flat only, so exactly one segment.
        if ( 1 === count( $segments ) ) {
            $query_vars['post_type']      = 'fpc_ingredient';
            $query_vars['fpc_ingredient'] = $last_slug;
            $query_vars['name']           = $last_slug;
            return $query_vars;
        }

        // 3. Neither — let WordPress 404 rather than silently showing the archive.
        $query_vars['error'] = '404';
        return $query_vars;
    }
}
