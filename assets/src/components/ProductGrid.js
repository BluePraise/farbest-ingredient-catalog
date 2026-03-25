/**
 * Ingredient Grid Component
 * Orchestrates filtering, sorting, view toggle, and pagination.
 */
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import ProductFilter from './ProductFilter';
import ProductSearch from './ProductSearch';

const EMPTY_SELECTED = { categories: [], claims: [], certifications: [], applications: [] };

/**
 * Derive available filter slugs from a list of ingredients + the full filter options map.
 * Returns null if filterOptions isn't loaded yet.
 */
function computeAvailableSlugsFrom(ingredientList, options) {
    if (!options.categories.length && !options.claims.length && !options.certifications.length) {
        return null;
    }

    const categories = new Set();
    const claims = new Set();
    const certifications = new Set();

    if (options.categories.length) {
        const nameToSlug = {};
        options.categories.forEach((t) => { nameToSlug[t.name] = t.slug; });
        ingredientList.forEach((p) => {
            (p.categories || []).forEach((name) => {
                if (nameToSlug[name]) categories.add(nameToSlug[name]);
            });
        });
    }
    if (options.claims.length) {
        const nameToSlug = {};
        options.claims.forEach((t) => { nameToSlug[t.name] = t.slug; });
        ingredientList.forEach((p) => {
            (p.claims || []).forEach((name) => {
                if (nameToSlug[name]) claims.add(nameToSlug[name]);
            });
        });
    }
    if (options.certifications.length) {
        const nameToSlug = {};
        options.certifications.forEach((t) => { nameToSlug[t.name] = t.slug; });
        ingredientList.forEach((p) => {
            (p.certifications || []).forEach((name) => {
                if (nameToSlug[name]) certifications.add(nameToSlug[name]);
            });
        });
    }

    const applications = new Set();
    if (options.applications && options.applications.length) {
        const nameToSlug = {};
        options.applications.forEach((t) => { nameToSlug[t.name] = t.slug; });
        ingredientList.forEach((p) => {
            (p.applications || []).forEach((name) => {
                if (nameToSlug[name]) applications.add(nameToSlug[name]);
            });
        });
    }

    return { categories, claims, certifications, applications };
}

const IngredientGrid = ({ initialCategory = '' }) => {
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

    // Available slugs for smart filtering (updated after each fetch)
    const [availableSlugs, setAvailableSlugs] = useState(null);

    // Load filter options once on mount; re-compute available slugs once they arrive
    useEffect(() => {
        apiFetch({ path: '/farbest/v1/filter-options' })
            .then((data) => {
                setFilterOptions(data);
                setOptionsLoaded(true);
                // Re-compute now that we have the slug→name map
                setIngredients((current) => {
                    if (current.length > 0) {
                        setAvailableSlugs(computeAvailableSlugsFrom(current, data));
                    }
                    return current;
                });
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

            // Compute available slugs from returned ingredients for smart filtering
            setAvailableSlugs(computeAvailableSlugs(response.ingredients));
        } catch (err) {
            setError(err.message);
            console.error('Error fetching ingredients:', err);
        } finally {
            setLoading(false);
        }
    };

    /**
     * Build Sets of slugs that appear in the current result set.
     * Used to gray out filter options with 0 matches.
     */
    const computeAvailableSlugs = (ingredientList) => {
        return computeAvailableSlugsFrom(ingredientList, filterOptions);
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
        setFilters({
            selected: { ...EMPTY_SELECTED },
            search: '',
            orderby: 'name',
            order: 'ASC',
            page: 1,
        });
        setAvailableSlugs(null);
    };


    const hasActiveFilters =
        filters.selected.categories.length > 0 ||
        filters.selected.claims.length > 0 ||
        filters.selected.certifications.length > 0 ||
        filters.selected.applications.length > 0 ||
        filters.search !== '';

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

    return (
        <div className="fpc-ingredient-grid-wrapper">

            {/* Filters bar */}
            <div className="fpc-filters-bar">
                {optionsLoaded && (
                    <ProductFilter
                        filterOptions={filterOptions}
                        selected={filters.selected}
                        onFilterChange={handleFilterChange}
                        availableSlugs={availableSlugs}
                        onReset={handleReset}
                    />
                )}
            </div>

            {/* Toolbar: search + results count + sort — always visible */}
            {optionsLoaded && (
            <div className="fpc-toolbar">
                <ProductSearch
                    onSearch={handleSearchChange}
                    initialValue={filters.search}
                />

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

                <div className="fpc-toolbar-right">
                    <label className="fpc-sort-label" htmlFor="fpc-sort-select">
                        {__('Sort:', 'farbest-catalog')}
                    </label>
                    <select
                        id="fpc-sort-select"
                        className="fpc-sort-select"
                        value={sortValue}
                        onChange={handleSortChange}
                    >
                        <option value="name-asc">{__('Name (A-Z)', 'farbest-catalog')}</option>
                        <option value="name-desc">{__('Name (Z-A)', 'farbest-catalog')}</option>
                        <option value="date-desc">{__('Newest First', 'farbest-catalog')}</option>
                        <option value="date-asc">{__('Oldest First', 'farbest-catalog')}</option>
                    </select>
                </div>
            </div>
            )}

            {/* Initial view: category browse (no filters active) */}
            {showCategoryBrowse && (
                <CategoryGrid
                    categories={filterOptions.parent_categories}
                    onSelectCategory={(slug) =>
                        handleFilterChange({ ...EMPTY_SELECTED, categories: [slug] })
                    }
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
                        <p>{__('No ingredients found matching your criteria.', 'farbest-catalog')}</p>
                        {hasActiveFilters && (
                            <button type="button" className="fpc-reset-button" onClick={handleReset}>
                                {__('Reset Filters', 'farbest-catalog')}
                            </button>
                        )}
                    </div>
                ) : (
                    <div className="fpc-ingredients-grid">
                        {ingredients.map((ingredient) => (
                            <IngredientCard key={ingredient.id} ingredient={ingredient} />
                        ))}
                    </div>
                )
            )}
        </div>
    );
};

/**
 * CategoryGrid — initial view showing one card per ingredient category.
 * Clicking a card sets that category as the active filter.
 */
const CategoryGrid = ({ categories, onSelectCategory }) => {
    if (!categories || categories.length === 0) return null;
    return (
        <div className="fpc-category-grid">
            {categories.map((cat) => (
                <button
                    key={cat.slug}
                    type="button"
                    className="fpc-category-card"
                    onClick={() => onSelectCategory(cat.slug)}
                >
                    <div className="fpc-category-card-icon" aria-hidden="true" />
                    <div className="fpc-category-card-content">
                        <h3 className="fpc-category-card-title">{cat.name}</h3>
                        <span className="fpc-button">{__('Product Details', 'farbest-catalog')}</span>
                    </div>
                </button>
            ))}
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

const IngredientCard = ({ ingredient }) => {
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

                {ingredient.excerpt && (
                    <div
                        className="fpc-ingredient-excerpt"
                        dangerouslySetInnerHTML={{ __html: ingredient.excerpt }}
                    />
                )}

                <a href={ingredient.permalink} className="fpc-button">
                    {__('View Details', 'farbest-catalog')}
                </a>
            </div>
        </article>
    );
};


export default IngredientGrid;
