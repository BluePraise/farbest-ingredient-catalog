/**
 * Ingredient Grid Component
 * Orchestrates filtering, sorting, view toggle, and pagination.
 */
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import ProductFilter from './ProductFilter';
import ProductSearch from './ProductSearch';

const EMPTY_SELECTED = { categories: [], claims: [], certifications: [] };

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

    return { categories, claims, certifications };
}

const IngredientGrid = ({ initialCategory = '' }) => {
    // Filter / sort state
    const [filters, setFilters] = useState({
        selected: initialCategory
            ? { categories: [initialCategory], claims: [], certifications: [] }
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
    const [filterOptions, setFilterOptions] = useState({ categories: [], claims: [], certifications: [] });
    const [optionsLoaded, setOptionsLoaded] = useState(false);

    // Available slugs for smart filtering (updated after each fetch)
    const [availableSlugs, setAvailableSlugs] = useState(null);

    // View: 'grid' | 'list'
    const [view, setView] = useState('grid');

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
            if (filters.search) {
                params.append('search', filters.search);
            }
            params.append('orderby', filters.orderby);
            params.append('order', filters.order);
            params.append('page', filters.page);
            params.append('per_page', 12);

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

    const handlePageChange = (page) => {
        setFilters((f) => ({ ...f, page }));
        window.scrollTo({ top: 0, behavior: 'smooth' });
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
        filters.search !== '';

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
                <ProductSearch
                    onSearch={handleSearchChange}
                    initialValue={filters.search}
                />
                {optionsLoaded && (
                    <ProductFilter
                        filterOptions={filterOptions}
                        selected={filters.selected}
                        onFilterChange={handleFilterChange}
                        availableSlugs={availableSlugs}
                    />
                )}
            </div>

            {/* Toolbar: results count + sort + view toggle */}
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

                <div className="fpc-toolbar-right">
                    {hasActiveFilters && (
                        <button
                            type="button"
                            className="fpc-reset-button"
                            onClick={handleReset}
                        >
                            {__('Reset Filters', 'farbest-catalog')}
                        </button>
                    )}

                    <label className="fpc-sort-label" htmlFor="fpc-sort-select">
                        {__('Sort:', 'farbest-catalog')}
                    </label>
                    <select
                        id="fpc-sort-select"
                        className="fpc-sort-select"
                        value={sortValue}
                        onChange={handleSortChange}
                    >
                        <option value="name-asc">{__('Name (A–Z)', 'farbest-catalog')}</option>
                        <option value="name-desc">{__('Name (Z–A)', 'farbest-catalog')}</option>
                        <option value="date-desc">{__('Newest First', 'farbest-catalog')}</option>
                        <option value="date-asc">{__('Oldest First', 'farbest-catalog')}</option>
                    </select>

                    <div className="fpc-view-toggle" role="group" aria-label={__('View', 'farbest-catalog')}>
                        <button
                            type="button"
                            className={`fpc-view-btn${view === 'grid' ? ' active' : ''}`}
                            onClick={() => setView('grid')}
                            aria-pressed={view === 'grid'}
                            title={__('Grid view', 'farbest-catalog')}
                        >
                            <svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true">
                                <rect x="0" y="0" width="7" height="7" /><rect x="9" y="0" width="7" height="7" />
                                <rect x="0" y="9" width="7" height="7" /><rect x="9" y="9" width="7" height="7" />
                            </svg>
                        </button>
                        <button
                            type="button"
                            className={`fpc-view-btn${view === 'list' ? ' active' : ''}`}
                            onClick={() => setView('list')}
                            aria-pressed={view === 'list'}
                            title={__('List view', 'farbest-catalog')}
                        >
                            <svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true">
                                <rect x="0" y="1" width="16" height="3" /><rect x="0" y="7" width="16" height="3" />
                                <rect x="0" y="13" width="16" height="3" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            {/* Ingredient grid / list */}
            {loading ? (
                <div className="fpc-loading">
                    <span className="fpc-spinner" aria-hidden="true"></span>
                    <p>{__('Loading ingredients…', 'farbest-catalog')}</p>
                </div>
            ) : ingredients.length === 0 ? (
                <div className="fpc-no-results">
                    <p>{__('No ingredients found matching your criteria.', 'farbest-catalog')}</p>
                    {hasActiveFilters && (
                        <button type="button" className="fpc-reset-button" onClick={handleReset}>
                            {__('Clear all filters', 'farbest-catalog')}
                        </button>
                    )}
                </div>
            ) : (
                <>
                    <div className={`fpc-ingredients-grid${view === 'list' ? ' fpc-ingredients-list' : ''}`}>
                        {ingredients.map((ingredient) => (
                            <IngredientCard key={ingredient.id} ingredient={ingredient} view={view} />
                        ))}
                    </div>

                    {pagination.pages > 1 && (
                        <Pagination
                            currentPage={filters.page}
                            totalPages={pagination.pages}
                            onPageChange={handlePageChange}
                        />
                    )}
                </>
            )}
        </div>
    );
};

const CategoryBadges = ({ categories }) => {
    if (!categories || categories.length === 0) return null;
    const visible = categories.slice(0, 3);
    const extra = categories.length - 3;
    return (
        <div className="fpc-ingredient-terms">
            {visible.map((cat, i) => (
                <span key={i} className="fpc-term-badge fpc-term-badge--category">{cat}</span>
            ))}
            {extra > 0 && (
                <span className="fpc-term-badge fpc-term-badge--more">+{extra} more</span>
            )}
        </div>
    );
};

const truncate = (str, max) => {
    if (!str) return '';
    const plain = str.replace(/<[^>]+>/g, '');
    return plain.length > max ? plain.slice(0, max) + '…' : plain;
};

const IngredientCard = ({ ingredient, view }) => {
    if (view === 'list') {
        return (
            <article className="fpc-ingredient-card fpc-ingredient-card--list">
                {ingredient.thumbnail && (
                    <a href={ingredient.permalink} className="fpc-ingredient-thumbnail">
                        <img src={ingredient.thumbnail} alt={ingredient.title} loading="lazy" />
                    </a>
                )}
                <div className="fpc-ingredient-card-content">
                    <div className="fpc-ingredient-card-body">
                        <h3 className="fpc-ingredient-title">
                            <a href={ingredient.permalink}>{ingredient.title}</a>
                        </h3>

                        {ingredient.description && (
                            <p className="fpc-ingredient-description">
                                {truncate(ingredient.description, 250)}
                            </p>
                        )}

                        <CategoryBadges categories={ingredient.categories} />

                        {ingredient.claims && ingredient.claims.length > 0 && (
                            <p className="fpc-ingredient-claims-text">
                                <strong>{__('Claims:', 'farbest-catalog')}</strong>{' '}
                                {ingredient.claims.slice(0, 5).join(', ')}
                                {ingredient.claims.length > 5 ? '…' : ''}
                            </p>
                        )}
                    </div>

                    <a href={ingredient.permalink} className="fpc-button fpc-button--list">
                        {__('View Details', 'farbest-catalog')}
                    </a>
                </div>
            </article>
        );
    }

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

                <CategoryBadges categories={ingredient.categories} />

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

const Pagination = ({ currentPage, totalPages, onPageChange }) => {
    // Show at most 7 page numbers around current page
    const delta = 2;
    const range = [];
    for (
        let i = Math.max(1, currentPage - delta);
        i <= Math.min(totalPages, currentPage + delta);
        i++
    ) {
        range.push(i);
    }

    return (
        <nav className="fpc-pagination" aria-label={__('Ingredients pagination', 'farbest-catalog')}>
            <button
                onClick={() => onPageChange(currentPage - 1)}
                disabled={currentPage === 1}
                className="fpc-pagination-button"
            >
                {__('« Previous', 'farbest-catalog')}
            </button>

            <div className="fpc-pagination-numbers">
                {range[0] > 1 && (
                    <>
                        <button className="fpc-pagination-number" onClick={() => onPageChange(1)}>1</button>
                        {range[0] > 2 && <span className="fpc-pagination-ellipsis">…</span>}
                    </>
                )}
                {range.map((page) => (
                    <button
                        key={page}
                        onClick={() => onPageChange(page)}
                        className={`fpc-pagination-number${page === currentPage ? ' active' : ''}`}
                        aria-current={page === currentPage ? 'page' : undefined}
                    >
                        {page}
                    </button>
                ))}
                {range[range.length - 1] < totalPages && (
                    <>
                        {range[range.length - 1] < totalPages - 1 && <span className="fpc-pagination-ellipsis">…</span>}
                        <button className="fpc-pagination-number" onClick={() => onPageChange(totalPages)}>{totalPages}</button>
                    </>
                )}
            </div>

            <button
                onClick={() => onPageChange(currentPage + 1)}
                disabled={currentPage === totalPages}
                className="fpc-pagination-button"
            >
                {__('Next »', 'farbest-catalog')}
            </button>
        </nav>
    );
};

export default IngredientGrid;
