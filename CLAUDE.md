# Farbest Product Catalog — Plugin

## Project Context
Custom WordPress plugin replacing WooCommerce for Farbest Brands' ingredient catalog.
Built for BeckerGuerry (BG) as a proof-of-concept filter system before a full theme overhaul.
The plugin is the **canonical** source for all ingredient data and filtering — the theme's older Bootstrap/AJAX system (`page-filter-demo.php` + `products` CPT) is deprecated in favor of this plugin.

## Architecture
- **CPT:** `fpc_ingredient` (slug: `/ingredients/`, has archive)
- **Frontend:** React app — `assets/src/index.js` mounts `IngredientGrid` to `#farbest-ingredient-grid`
- **Backend:** REST API at `/wp-json/farbest/v1/`
- **Build:** `npm run build` → `assets/build/` (wp-scripts / webpack)
- **Requires:** Advanced Custom Fields Pro

## Taxonomies
| Taxonomy | Slug | Type | Purpose |
|---|---|---|---|
| `fpc_category` | `ingredient-category` | Hierarchical | Top-level ingredient groups (Gum Acacia, Dairy Proteins, etc.) |
| `fpc_subcategory` | `ingredient-subcategory` | Hierarchical | Sub-groups within categories |
| `fpc_claim` | `claim` | Flat | Label Claims (Gluten Free, Non-GMO, Vegan, etc.) |
| `fpc_certification` | `certification` | Flat | Certifications (USDA Organic, Halal, Kosher, etc.) |
| `fpc_application` | `application` | Flat | Application areas (Bakery, Dairy, Nutrition bars, etc.) |
| `fpc_vendor` | — (no rewrite) | Flat | Vendors; each term carries a logo image + caption text via ACF |

## REST API
Base: `/wp-json/farbest/v1/`

- `GET /ingredients` — Paginated, filterable list
  - Params: `categories`, `claims`, `certifications`, `applications` (comma-separated slugs), `search`, `orderby` (name|date), `order` (ASC|DESC), `page`, `per_page`
  - Response: `{ ingredients: [...], total, pages }`
  - Each ingredient includes: `id, title, excerpt, description, permalink, thumbnail, categories, claims, certifications, applications`
- `GET /ingredients/{id}` — Single ingredient with ACF fields
- `GET /filter-options` — All available filter terms with counts: `{ categories, claims, certifications, applications }`
- `POST /submit-contact` — Contact form submission

## React Components
- `ProductGrid.js` — Main orchestrator: manages filter state, fetches from REST API, renders grid/list, pagination
- `ProductFilter.js` — 4 multi-select dropdowns (Ingredients / Application / Certifications / Label Claims). Uses `MultiSelectDropdown` sub-component with smart graying of unavailable options.
- `ProductSearch.js` — Debounced search input (500ms), calls `onSearch()` callback
- Filter state shape: `{ selected: { categories[], claims[], certifications[], applications[] }, search, orderby, order, page }`

## ACF Fields (on `fpc_ingredient`)
**Product Details group:**
- `product_description` (wysiwyg)
- `product_sheet` (file/pdf)
- `product_applications` (taxonomy field → `fpc_application`, saves terms)
- `product_vendors` (taxonomy field → `fpc_vendor`, saves terms; `add_term: false` — manage vendors via Ingredients → Vendors)
- `product_packaging` (textarea)
- `display_order` (number)

**Sales Rep Routing group:**
- `rep_code_primary`, `rep_code_secondary`, `rep_notes`

**Vendor Details group** (on `fpc_vendor` taxonomy terms):
- `vendor_logo` (image field)
- `vendor_text` (text, max 120 chars — caption beside the logo)

## Contact / Email System
- Shortcode `[fpc_contact_form product_id="123"]` renders inline form
- Submissions stored in `wp_fpc_submissions` table
- Email routing via `class-email-routing.php`: maps rep codes → email addresses
- Settings page: Products → Email Settings in WP Admin

## Template Loading
The plugin targets the classic **`farbest-classic`** theme only (the FSE block theme was retired
2026-07-23). `class-template-loader.php` intercepts `template_include` and checks (in order):
1. Theme: `farbest-catalog/{template}.php`
2. Theme root: `{template}.php`
3. Plugin: `templates/{template}.php`

Both plugin templates call `get_header()` / `get_footer()` unconditionally. There is no
`wp_is_block_theme()` branching, no `render_single()` / `render_archive()` string renderers,
and no `farbest/ingredient-archive` block — all removed in 1.6.0. If the active theme ever
gains a `templates/index.html`, `wp_is_block_theme()` flips to true site-wide; that no longer
affects this plugin, but it would change how WordPress resolves theme templates.

## Brand Colors (Farbest)
```
Primary green:  #648c1c  (logo, links)
Dark teal:      #003e52  (headings, body)
CTA button:     #b5b800  (lime green)
Light beige bg: #f2efe9
Warm grey text: #383838
```

## Versioning
When bumping the plugin version, update it in **all three places** — they must stay in sync:
1. `package.json` → `"version"` field
2. `farbest-product-catalog.php` → `Version:` header comment
3. `farbest-product-catalog.php` → `FPC_VERSION` constant

The deploy zip is named `farbest-product-catalog-{version}.zip` (version read from `package.json` at build time).

## Dev Commands
```bash
npm run build   # production build
npm start       # watch mode
npm run deploy  # build + create versioned zip
```

## Key Notes
- The `product_applications` ACF field was previously a free-text textarea. It is now a taxonomy field. Existing pipe-delimited text data will need to be migrated to `fpc_application` terms if content existed before this change.
- The plugin's assets normally only load on `fpc_ingredient` archive, single, and taxonomy pages. The old theme demo page now redirects to the canonical archive instead of loading a separate catalog experience.
- SVGs for ingredient category icons are pending from the client (BG). Plan as ACF image fields on `fpc_category` terms or static assets keyed to term slug.
- `fpc_ingredient` archive page (`/ingredients/`) uses the React app directly via `archive-ingredient.php` plugin template.
