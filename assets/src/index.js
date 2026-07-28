/**
 * Farbest Ingredient Catalog - React Entry Point
 */
import { render } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import IngredientGrid from './components/ProductGrid';
// Styles are shipped as plain CSS (assets/css/catalog.css), enqueued directly
// by the plugin — they are no longer bundled through the JS build.

// Configure apiFetch with the REST nonce from localized data.
// (wp-api-fetch already sets the root URL via window.wpApiSettings)
if ( window.fpcData ) {
    apiFetch.use( apiFetch.createNonceMiddleware( window.fpcData.nonce ) );
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    const mountPoint = document.getElementById('farbest-ingredient-grid');

    if (mountPoint) {
        const initialCategory = mountPoint.dataset.initialCategory || '';

        render(
            <IngredientGrid initialCategory={initialCategory} />,
            mountPoint
        );
    }
});
