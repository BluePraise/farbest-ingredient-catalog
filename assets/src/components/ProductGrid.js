/**
 * Ingredient Grid Component
 * Orchestrates filtering, sorting, view toggle, and pagination.
 */
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import ProductFilter from './ProductFilter';
import ProductSearch from './ProductSearch';
import { decodeHtmlEntities } from '../utils';

const EMPTY_SELECTED = { categories: [], claims: [], certifications: [], applications: [] };

/**
 * Show a full-page loader overlay before navigating away.
 * Appends a <div> directly to <body> so it sits above all page content.
 * The overlay cleans itself up automatically if the browser's back/forward
 * cache restores the page (pageshow event).
 */
function showPageLoader() {
    const overlay = document.createElement('div');
    overlay.id = 'fpc-page-loader';
    document.body.appendChild(overlay);

    // Remove on bfcache restore so the overlay doesn't persist if user hits Back
    window.addEventListener('pageshow', (e) => {
        if (e.persisted) overlay.remove();
    }, { once: true });
}

const IngredientGrid = ({ initialCategory = '', showSearch = true }) => {
    // Filter / sort state
    const [filters, setFilters] = useState({
        selected: initialCategory
            ? { categories: [initialCategory], claims: [], certifications: [], applications: [] }
            : { ...EMPTY_SELECTED },
        search: '',
        orderby: 'name',
        order: 'ASC',
        page: 1,
    });

    // Data state
    const [ingredients, setIngredients] = useState([]);
    const [pagination, setPagination] = useState({ total: 0, pages: 1 });
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    // Filter options (loaded once)
    const [filterOptions, setFilterOptions] = useState({ categories: [], parent_categories: [], claims: [], certifications: [] });
    const [optionsLoaded, setOptionsLoaded] = useState(false);

    // Disjunctive facet counts from the server (per facet: { slug: count }).
    // Drives both the number beside each option and whether it stays selectable.
    // null until the first fetch resolves.
    const [facetCounts, setFacetCounts] = useState(null);

    // Load filter options once on mount.
    useEffect(() => {
        apiFetch({ path: '/farbest/v1/filter-options' })
            .then((data) => {
                setFilterOptions(data);
                setOptionsLoaded(true);
            })
            .catch((err) => console.error('Error loading filter options:', err));
    }, []);

    // Fetch ingredients whenever filters change
    useEffect(() => {
        fetchIngredients();
    }, [filters]);

    const fetchIngredients = async () => {
        setLoading(true);
        setError(null);

        try {
            const params = new URLSearchParams();

            // Categories: send all selected as comma-separated (OR logic on the backend)
            if (filters.selected.categories.length > 0) {
                params.append('categories', filters.selected.categories.join(','));
            }
            if (filters.selected.claims.length > 0) {
                params.append('claims', filters.selected.claims.join(','));
            }
            if (filters.selected.certifications.length > 0) {
                params.append('certifications', filters.selected.certifications.join(','));
            }
            if (filters.selected.applications.length > 0) {
                params.append('applications', filters.selected.applications.join(','));
            }
            if (filters.search) {
                params.append('search', filters.search);
            }
            params.append('orderby', filters.orderby);
            params.append('order', filters.order);
            params.append('page', filters.page);
            params.append('per_page', -1);

            const response = await apiFetch({
                path: `/farbest/v1/ingredients?${params.toString()}`,
            });

            setIngredients(response.ingredients);
            setPagination({ total: response.total, pages: response.pages });
            setFacetCounts(response.facets || null);
        } catch (err) {
            setError(err.message);
            console.error('Error fetching ingredients:', err);
        } finally {
            setLoading(false);
        }
    };

    const handleFilterChange = (newSelected) => {
        setFilters((f) => ({ ...f, selected: newSelected, page: 1 }));
    };

    const handleSearchChange = (search) => {
        setFilters((f) => ({ ...f, search, page: 1 }));
    };

    const handleSortChange = (e) => {
        const [orderby, order] = e.target.value.split('-');
        setFilters((f) => ({ ...f, orderby, order: order.toUpperCase(), page: 1 }));
    };

    const handleReset = () => {
        // If we're on a taxonomy archive URL (e.g. /ingredient-category/gum-acacia/),
        // the PHP-rendered hero title won't update when we clear the filter — navigate
        // back to the base archive so the page title stays in sync.
        if (initialCategory && window.fpcData && window.fpcData.archiveUrl) {
            showPageLoader();
            window.location.href = window.fpcData.archiveUrl;
            return;
        }
        setFilters({
            selected: { ...EMPTY_SELECTED },
            search: '',
            orderby: 'name',
            order: 'ASC',
            page: 1,
        });
        setFacetCounts(null);
    };


    const hasActiveFilters =
        filters.selected.categories.length > 0 ||
        filters.selected.claims.length > 0 ||
        filters.selected.certifications.length > 0 ||
        filters.selected.applications.length > 0 ||
        filters.search !== '';

    // True when we've landed on a category archive (e.g. /ingredient-category/lecithins/)
    // that simply has no ingredients yet — the only "filter" is the archive's own
    // category and the visitor hasn't narrowed anything down. This is a "coming soon"
    // state, not a "your filters matched nothing" state, so it gets friendlier copy.
    const isEmptyCategoryArchive =
        !!initialCategory &&
        filters.search === '' &&
        filters.selected.categories.length === 1 &&
        filters.selected.categories[0] === initialCategory &&
        filters.selected.claims.length === 0 &&
        filters.selected.certifications.length === 0 &&
        filters.selected.applications.length === 0;

    const archiveUrl = (window.fpcData && window.fpcData.archiveUrl) || '';

    // Initial view: show parent category cards when no filters are active
    const showCategoryBrowse = !hasActiveFilters && optionsLoaded;

    const sortValue = `${filters.orderby}-${filters.order.toLowerCase()}`;

    if (error) {
        return (
            <div className="fpc-error">
                <p>{__('Error loading ingredients.', 'farbest-catalog')} {error}</p>
            </div>
        );
    }

    const handleRemovePill = (type, slug) => {
        // On a taxonomy archive page the hero is PHP-rendered and won't update via
        // React state alone — navigate to the base archive (same as Reset) when the
        // category filter is cleared so the page title stays in sync.
        if (type === 'categories' && initialCategory && window.fpcData && window.fpcData.archiveUrl) {
            showPageLoader();
            window.location.href = window.fpcData.archiveUrl;
            return;
        }
        const next = { ...filters.selected, [type]: filters.selected[type].filter((s) => s !== slug) };
        handleFilterChange(next);
    };

    return (
        <div className="fpc-ingredient-grid-wrapper">

            {/* Search — above filters. Hidden on category pages, where the
                results are already scoped to one category. */}
            {optionsLoaded && showSearch && (
                <ProductSearch
                    onSearch={handleSearchChange}
                    initialValue={filters.search}
                />
            )}

            {/* Filters bar */}
            <div className="fpc-filters-bar">
                {optionsLoaded && (
                    <ProductFilter
                        filterOptions={filterOptions}
                        selected={filters.selected}
                        onFilterChange={handleFilterChange}
                        facetCounts={facetCounts}
                        onReset={handleReset}
                    />
                )}
            </div>

            {/* Active filter pills */}
            {optionsLoaded && hasActiveFilters && (
                <FilterPills
                    selected={filters.selected}
                    filterOptions={filterOptions}
                    onRemove={handleRemovePill}
                    onReset={handleReset}
                />
            )}

            {/* Toolbar: results count — always visible */}
            {optionsLoaded && (
            <div className="fpc-toolbar">

                <div className="fpc-results-count">
                    {loading ? (
                        <span>{__('Loading…', 'farbest-catalog')}</span>
                    ) : (
                        <span>
                            <strong>{pagination.total}</strong>{' '}
                            {pagination.total === 1
                                ? __('ingredient found', 'farbest-catalog')
                                : __('ingredients found', 'farbest-catalog')}
                        </span>
                    )}
                </div>


            </div>
            )}

            {/* Initial view: category browse (no filters active) */}
            {showCategoryBrowse && (
                <CategoryGrid
                    categories={filterOptions.parent_categories}
                    onSelectCategory={(slug) => {
                        // Navigate to the category archive so the PHP-rendered hero
                        // (title, subtitle, background image) updates correctly.
                        const cat = (filterOptions.parent_categories || []).find((c) => c.slug === slug);
                        if (cat && cat.link) {
                            showPageLoader();
                            window.location.href = cat.link;
                        } else {
                            handleFilterChange({ ...EMPTY_SELECTED, categories: [slug] });
                        }
                    }}
                />
            )}

            {/* Filtered view: ingredient results */}
            {!showCategoryBrowse && (
                loading ? (
                    <div className="fpc-loading">
                        <span className="fpc-spinner" aria-hidden="true"></span>
                        <p>{__('Loading ingredients…', 'farbest-catalog')}</p>
                    </div>
                ) : ingredients.length === 0 ? (
                    <div className="fpc-no-results">
                        {isEmptyCategoryArchive ? (
                            <>
                                <p>{__('We haven’t added any ingredients to this category yet — check back soon.', 'farbest-catalog')}</p>
                                {archiveUrl && (
                                    <a className="fpc-reset-button" href={archiveUrl} onClick={showPageLoader}>
                                        {__('Browse all ingredients', 'farbest-catalog')}
                                    </a>
                                )}
                            </>
                        ) : (
                            <>
                                <p>{__('No ingredients found matching your criteria.', 'farbest-catalog')}</p>
                                {hasActiveFilters && (
                                    <button type="button" className="fpc-reset-button" onClick={handleReset}>
                                        {__('Reset Filters', 'farbest-catalog')}
                                    </button>
                                )}
                            </>
                        )}
                    </div>
                ) : (
                    <div className="fpc-ingredients-grid">
                        {ingredients.map((ingredient) => (
                            <IngredientCard key={ingredient.id} ingredient={ingredient} certOptions={filterOptions.certifications} />
                        ))}
                    </div>
                )
            )}
        </div>
    );
};

/**
 * FilterPills — shows one removable pill per active filter selection.
 */
const FILTER_TYPE_LABELS = {
    categories:     __('Ingredient', 'farbest-catalog'),
    applications:   __('Application', 'farbest-catalog'),
    certifications: __('Certification', 'farbest-catalog'),
    claims:         __('Claim', 'farbest-catalog'),
};

const FilterPills = ({ selected, filterOptions, onRemove, onReset }) => {
    // Build a slug→name lookup for each type
    const lookup = {
        categories:     Object.fromEntries((filterOptions.categories     || []).map((o) => [o.slug, o.name])),
        applications:   Object.fromEntries((filterOptions.applications   || []).map((o) => [o.slug, o.name])),
        certifications: Object.fromEntries((filterOptions.certifications || []).map((o) => [o.slug, o.name])),
        claims:         Object.fromEntries((filterOptions.claims         || []).map((o) => [o.slug, o.name])),
    };

    const pills = [];
    ['categories', 'applications', 'certifications', 'claims'].forEach((type) => {
        (selected[type] || []).forEach((slug) => {
            pills.push({ type, slug, label: decodeHtmlEntities(lookup[type][slug] || slug), typeLabel: FILTER_TYPE_LABELS[type] });
        });
    });

    if (pills.length === 0) return null;

    return (
        <div className="fpc-active-selections" role="group" aria-label={__('Active filters', 'farbest-catalog')}>
            <span className="fpc-active-selections-label">{__('Active filters:', 'farbest-catalog')}</span>
            {pills.map(({ type, slug, label, typeLabel }) => (
                <button
                    key={`${type}-${slug}`}
                    type="button"
                    className={`fpc-selection fpc-selection--${type}`}
                    onClick={() => onRemove(type, slug)}
                    aria-label={`${__('Remove filter', 'farbest-catalog')}: ${typeLabel} – ${label}`}
                >
                    <span className="fpc-selection-type">{typeLabel}</span>
                    <span className="fpc-selection-name">{label}</span>
                    <span className="fpc-selection-remove" aria-hidden="true">×</span>
                </button>
            ))}
            {pills.length > 1 && (
                <button
                    type="button"
                    className="fpc-selection fpc-selection--clear-all"
                    onClick={onReset}
                >
                    {__('Clear all', 'farbest-catalog')}
                </button>
            )}
        </div>
    );
};

/**
 * Map category slugs to their SVG icon filenames.
 * Falls back to the green circle placeholder via CSS when no match.
 */
const CATEGORY_ICON_MAP = {
    'plant-protein':   'Pea_Protein_Icon.webp',
    'dairy-protein':   'Dairy_Protein_Icon.webp',
    'dietary-fiber':          'Fiber-Icon.webp',
    'carrot':          'Carrot_Circle_Icon.svg',
    'gum-acacia':      'Gum-Acacia-Icon.webp',
    'natural-colors':   'Natural_Colors-Icon.webp',
    'sweeteners':       'Sweetener-Icon.webp',
    'vitamins-and-nutrients': 'Vitamin_Icon.webp',
    'lecithins':        'Lecithin_Icon.webp',
};

const pluginUrl = window.fpcData ? window.fpcData.pluginUrl : '';

/**
 * CategoryGrid — initial view showing one card per ingredient category.
 * Clicking a card sets that category as the active filter.
 */
const CategoryGrid = ({ categories, onSelectCategory }) => {
    if (!categories || categories.length === 0) return null;
    return (
        <div className="fpc-category-grid">
            {categories.map((cat) => {
                const iconFile = CATEGORY_ICON_MAP[cat.slug];
                const iconSrc = cat.icon_url || (iconFile ? `${pluginUrl}assets/img/${iconFile}` : '');
                return (
                <button
                    key={cat.slug}
                    type="button"
                    className="fpc-category-card"
                    onClick={() => onSelectCategory(cat.slug)}
                >
                    <div className="fpc-category-card-icon" aria-hidden="true">
                        {iconSrc && (
                            <img
                                src={iconSrc}
                                alt=""
                                className="fpc-category-card-icon-img"
                            />
                        )}
                    </div>
                    <div className="fpc-category-card-content">
                        <h3 className="fpc-category-card-title">{decodeHtmlEntities(cat.name)}</h3>
                        {cat.tagline_lines && cat.tagline_lines.length > 0 && (
                            <p className="fpc-card-certifications">
                                {cat.tagline_lines.map((line, i) => (
                                    <span key={i}>{line}</span>
                                ))}
                            </p>
                        )}
                        <span className="fpc-button">{__('Product Details', 'farbest-catalog')}</span>
                    </div>
                </button>
                );
            })}
        </div>
    );
};

const CategoryBadges = ({ subcategories }) => {
    if (!subcategories || subcategories.length === 0) return null;
    const visible = subcategories.slice(0, 3);
    const extra = subcategories.length - 3;
    return (
        <div className="fpc-ingredient-terms">
            {visible.map((name, i) => (
                <span key={i} className="fpc-term-badge fpc-term-badge--category">{name}</span>
            ))}
            {extra > 0 && (
                <span className="fpc-term-badge fpc-term-badge--more">+{extra} more</span>
            )}
        </div>
    );
};

/**
 * BenefitsGroups — renders benefit headings + bullet lists parsed from post content.
 * Each group corresponds to one column in the benefits-columns block pattern.
 */
const BenefitsGroups = ({ benefits }) => {
    if (!benefits || benefits.length === 0) return null;
    return (
        <div className="fpc-ingredient-benefits">
            {benefits.map((group, i) => (
                <div key={i} className="fpc-ingredient-benefits__group">
                    {group.heading && (
                        <p className="fpc-ingredient-benefits__heading">{group.heading}</p>
                    )}
                    {group.items && group.items.length > 0 && (
                        <ul className="fpc-ingredient-benefits__list">
                            {group.items.map((item, j) => (
                                <li key={j}>{item}</li>
                            ))}
                        </ul>
                    )}
                </div>
            ))}
        </div>
    );
};

const IngredientCard = ({ ingredient, certOptions = [] }) => {
    // Build name → cert options lookup for logo data
    const certMap = {};
    certOptions.forEach((c) => { certMap[c.name] = c; });

    const visibleLogos = (ingredient.certifications || [])
        .map((name) => certMap[name])
        .filter((c) => c && c.show_on_card && c.logo_url);

    return (
        <article className="fpc-ingredient-card">
            {ingredient.thumbnail && (
                <a href={ingredient.permalink} className="fpc-ingredient-thumbnail">
                    <img src={ingredient.thumbnail} alt={ingredient.title} loading="lazy" />
                </a>
            )}
            <div className="fpc-ingredient-card-content">
                <h3 className="fpc-ingredient-title">
                    <a href={ingredient.permalink}>{ingredient.title}</a>
                </h3>

                <CategoryBadges subcategories={ingredient.subcategories} />

                <BenefitsGroups benefits={ingredient.benefits} />

                {(!ingredient.benefits || ingredient.benefits.length === 0) && ingredient.excerpt && (
                    <div
                        className="fpc-ingredient-excerpt"
                        dangerouslySetInnerHTML={{ __html: ingredient.excerpt }}
                    />
                )}

                {visibleLogos.length > 0 && (
                    <div className="fpc-cert-logos">
                        {visibleLogos.map((c) => (
                            <img
                                key={c.name}
                                src={c.logo_url}
                                alt={c.logo_alt || c.name}
                                className="fpc-cert-logo"
                                loading="lazy"
                            />
                        ))}
                    </div>
                )}

                <a href={ingredient.permalink} className="fpc-button">
                    {__('View Details', 'farbest-catalog')}
                </a>
            </div>
        </article>
    );
};


export default IngredientGrid;
