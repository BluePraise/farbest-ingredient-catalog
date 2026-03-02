/**
 * Farbest Ingredient Catalog - React Entry Point
 */
import { render } from '@wordpress/element';
import IngredientGrid from './components/ProductGrid';
import './styles/main.scss';

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
