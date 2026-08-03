/**
 * Ingredient Filter Component
 * Multi-select dropdown filters for categories, claims, and certifications.
 * Receives filterOptions from parent and calls onFilterChange with selected values.
 */
import { useState, useRef, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { decodeHtmlEntities } from '../utils';

/**
 * A single multi-select dropdown (checkbox list).
 *
 * @param {string}   label          - Button label (e.g. "All Categories")
 * @param {Array}    options        - [{id, name, slug, count}]
 * @param {Array}    selected       - Array of selected slugs
 * @param {Function} onChange       - Called with new selected array
 * @param {Object}   counts - Map of slug -> match count for this facet; null = not loaded yet
 */
const MultiSelectDropdown = ({ label, options, selected, onChange, counts }) => {
    const [open, setOpen] = useState(false);
    const ref = useRef(null);

    // Close on outside click
    useEffect(() => {
        const handler = (e) => {
            if (ref.current && !ref.current.contains(e.target)) {
                setOpen(false);
            }
        };
        document.addEventListener('mousedown', handler);
        return () => document.removeEventListener('mousedown', handler);
    }, []);

    const toggle = (slug) => {
        const next = selected.includes(slug)
            ? selected.filter((s) => s !== slug)
            : [...selected, slug];
        onChange(next);
        setOpen(false);
    };

    const buttonLabel = selected.length === 0
        ? label
        : selected.length === 1
            ? __('1 Selected', 'farbest-catalog')
            : `${selected.length} ${__('Selected', 'farbest-catalog')}`;

    return (
        <div className="fpc-dropdown" ref={ref}>
            <button
                type="button"
                className={`fpc-dropdown-toggle${open ? ' open' : ''}`}
                onClick={() => setOpen((o) => !o)}
                aria-expanded={open}
            >
                <span>{buttonLabel}</span>
                <span className="fpc-dropdown-caret" aria-hidden="true">▾</span>
            </button>

            {open && (
                <div className="fpc-dropdown-menu" role="listbox" aria-multiselectable="true">
                    {options.length === 0 ? (
                        <div className="fpc-dropdown-empty">
                            {__('No options available', 'farbest-catalog')}
                        </div>
                    ) : (
                        options.map((opt) => {
                            const isChecked = selected.includes(opt.slug);
                            // Before the first fetch resolves (counts == null) fall back to the
                            // global term count; afterwards use the context-aware facet count.
                            const count = counts == null ? (opt.count || 0) : (counts[opt.slug] || 0);
                            const available = counts == null || isChecked || count > 0;
                            return (
                                <label
                                    key={opt.slug}
                                    className={`fpc-dropdown-item${!available ? ' disabled' : ''}`}
                                >
                                    <input
                                        type="checkbox"
                                        checked={isChecked}
                                        disabled={!available}
                                        onChange={() => toggle(opt.slug)}
                                    />
                                    <span className="fpc-dropdown-item-name">{decodeHtmlEntities(opt.name)}</span>
                                    <span className="fpc-dropdown-item-count">({count})</span>
                                </label>
                            );
                        })
                    )}
                </div>
            )}
        </div>
    );
};

/**
 * IngredientFilter — renders the four filter dropdowns.
 *
 * Props:
 *   filterOptions  - { categories, claims, certifications, applications } from /farbest/v1/filter-options
 *   selected       - { categories: [], claims: [], certifications: [], applications: [] }
 *   onFilterChange - called with updated selected object
 *   facetCounts    - { categories, claims, certifications, applications } each a slug->count map | null
 */
const ProductFilter = ({ filterOptions, selected, onFilterChange, facetCounts, onReset }) => {
    const update = (key, value) => {
        onFilterChange({ ...selected, [key]: value });
    };

    const handleReset = () => {
        onReset();
    };

    return (
        <div className="fpc-filter-dropdowns">
            <div className="fpc-filter-group">
                <span className="fpc-filter-label">
                    {__('Ingredients', 'farbest-catalog')}
                </span>
                <MultiSelectDropdown
                    label={__('All Ingredients', 'farbest-catalog')}
                    options={filterOptions.categories}
                    selected={selected.categories}
                    onChange={(val) => update('categories', val)}
                    counts={facetCounts ? facetCounts.categories : null}
                />
            </div>

            <div className="fpc-filter-group">
                <span className="fpc-filter-label">
                    {__('Application', 'farbest-catalog')}
                </span>
                <MultiSelectDropdown
                    label={__('All Applications', 'farbest-catalog')}
                    options={filterOptions.applications || []}
                    selected={selected.applications}
                    onChange={(val) => update('applications', val)}
                    counts={facetCounts ? facetCounts.applications : null}
                />
            </div>

            <div className="fpc-filter-group">
                <span className="fpc-filter-label">
                    {__('Certifications', 'farbest-catalog')}
                </span>
                <MultiSelectDropdown
                    label={__('All Certifications', 'farbest-catalog')}
                    options={filterOptions.certifications}
                    selected={selected.certifications}
                    onChange={(val) => update('certifications', val)}
                    counts={facetCounts ? facetCounts.certifications : null}
                />
            </div>

            <div className="fpc-filter-group">
                <span className="fpc-filter-label">
                    {__('Label Claims', 'farbest-catalog')}
                </span>
                <MultiSelectDropdown
                    label={__('All Claims', 'farbest-catalog')}
                    options={filterOptions.claims}
                    selected={selected.claims}
                    onChange={(val) => update('claims', val)}
                    counts={facetCounts ? facetCounts.claims : null}
                />
            </div>
            <button
                type="button"
                className="fpc-reset-button"
                onClick={handleReset}
            >
                {__('Reset Filters', 'farbest-catalog')}
            </button>
        </div>
    );
};

export default ProductFilter;
