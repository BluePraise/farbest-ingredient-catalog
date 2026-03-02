/**
 * Ingredient Search Component
 */
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

const ProductSearch = ({ onSearch, initialValue = '' }) => {
    const [searchTerm, setSearchTerm] = useState(initialValue);
    const [debounceTimer, setDebounceTimer] = useState(null);

    useEffect(() => {
        // Cleanup timer on unmount
        return () => {
            if (debounceTimer) {
                clearTimeout(debounceTimer);
            }
        };
    }, [debounceTimer]);

    const handleInputChange = (e) => {
        const value = e.target.value;
        setSearchTerm(value);

        // Clear existing timer
        if (debounceTimer) {
            clearTimeout(debounceTimer);
        }

        // Set new timer for debounced search
        const timer = setTimeout(() => {
            onSearch(value);
        }, 500);

        setDebounceTimer(timer);
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        if (debounceTimer) {
            clearTimeout(debounceTimer);
        }
        onSearch(searchTerm);
    };

    const handleClear = () => {
        setSearchTerm('');
        onSearch('');
    };

    return (
        <form className="fpc-search" onSubmit={handleSubmit}>
            <label htmlFor="fpc-search-input" className="screen-reader-text">
                {__('Search ingredients', 'farbest-catalog')}
            </label>
            <div className="fpc-search-wrapper">
                <input
                    id="fpc-search-input"
                    type="search"
                    className="fpc-search-input"
                    placeholder={__('Search ingredients...', 'farbest-catalog')}
                    value={searchTerm}
                    onChange={handleInputChange}
                />
                {searchTerm && (
                    <button
                        type="button"
                        className="fpc-search-clear"
                        onClick={handleClear}
                        aria-label={__('Clear search', 'farbest-catalog')}
                    >
                        ×
                    </button>
                )}
                <button
                    type="submit"
                    className="fpc-search-button"
                    aria-label={__('Search', 'farbest-catalog')}
                >
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                        <path
                            d="M9 17A8 8 0 1 0 9 1a8 8 0 0 0 0 16zM19 19l-4.35-4.35"
                            stroke="currentColor"
                            strokeWidth="2"
                            strokeLinecap="round"
                            strokeLinejoin="round"
                        />
                    </svg>
                </button>
            </div>
        </form>
    );
};

export default ProductSearch;
