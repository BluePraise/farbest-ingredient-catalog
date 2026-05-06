/**
 * Ingredient Tabs
 *
 * Handles tab switching on single ingredient pages.
 * Manages aria-selected, aria-controls, and hidden panel state
 * so the tabbed interface is keyboard and screen-reader accessible.
 *
 * Enqueued by the plugin on is_singular('fpc_ingredient').
 * No dependencies — vanilla JS only.
 */

function initIngredientTabs( wrapper ) {
    const tabs   = Array.from( wrapper.querySelectorAll( '.ingredient-tabs__tab' ) );
    const panels = Array.from( wrapper.querySelectorAll( '.ingredient-tabs__panel' ) );

    tabs.forEach( function ( tab ) {
        tab.addEventListener( 'click', function () {
            // Deactivate all tabs and panels scoped to this wrapper.
            tabs.forEach( function ( t ) {
                t.setAttribute( 'aria-selected', 'false' );
                t.classList.remove( 'ingredient-tabs__tab--active' );
            } );
            panels.forEach( function ( p ) {
                p.hidden = true;
                p.classList.remove( 'ingredient-tabs__panel--active' );
            } );

            // Activate the clicked tab and its associated panel.
            tab.setAttribute( 'aria-selected', 'true' );
            tab.classList.add( 'ingredient-tabs__tab--active' );

            const targetPanel = wrapper.querySelector( '#' + tab.getAttribute( 'aria-controls' ) );
            if ( targetPanel ) {
                targetPanel.hidden = false;
                targetPanel.classList.add( 'ingredient-tabs__panel--active' );
            }
        } );
    } );
}

document.addEventListener( 'DOMContentLoaded', function () {
    document.querySelectorAll( '.ingredient-tabs' ).forEach( initIngredientTabs );
} );
