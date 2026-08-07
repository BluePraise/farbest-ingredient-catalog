# Farbest Product Catalog — Plugin

## Project Context
Custom WordPress plugin replacing WooCommerce for Farbest Brands' ingredient catalog.
Built for BeckerGuerry (BG) as a proof-of-concept filter system before a full theme overhaul.
The plugin is the **canonical** source for all ingredient data and filtering — the theme's older Bootstrap/AJAX system (`page-filter-demo.php` + `products` CPT) is deprecated in favor of this plugin.

## Architecture
- **CPT:** `fpc_ingredient` (slug: `/ingredients/`, has archive)
- **Frontend:** React app — `assets/src/index.js` mounts `IngredientGrid` to `#farbest-ingredient-grid`
- **Backend:** REST API at `/wp-json/farbest/v1/`
- **Build:** `npm run build` → `assets/build/index.js` (wp-scripts / webpack). JS only.
- **Styles:** plain CSS, enqueued directly, **no build step** — `assets/css/catalog.css`
  (all front-end styles; brand palette lives in `--fpc-*` custom properties at the top) and
  `assets/css/archive.css` (archive hero/layout). Versioned by file mtime. The former
  `assets/src/styles/main.scss` was converted to `catalog.css` and removed.
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

## ACF Fields

Field groups are **ACF Local JSON** in `acf-json/` — one `group_*.json` per group. They used to be
registered in PHP via `acf_add_local_field_group()`, but ACF hides PHP-registered groups from the
Custom Fields → Field Groups admin screen entirely, so they could not be seen or edited there.

`includes/class-acf-fields.php` now only wires ACF up: it adds `acf-json/` as a load point and
routes saves for groups it owns back into the plugin (the theme sets the global save point to its
own `acf-json/`, so without that an admin edit would land in the theme and the next deploy would
revert it). It still registers the `fpc-main-settings` options page.

On a fresh environment the groups appear under Custom Fields → Field Groups → **Sync available**.
They are already active before syncing; syncing only creates the DB copies that make them editable
in the admin. Do not re-add PHP registration alongside the JSON: ACF fires `acf/include_fields`
(where Local JSON is read) *before* `acf/init`, so a PHP group would overwrite the JSON one and
disappear from the admin again.

### Single-ingredient circle icon

The circle SVG above the "Get in Touch" button is resolved by `FPC_Icons::get_ingredient_icon()`
(`includes/class-icons.php`), first hit wins:

1. `category_icon_circle` on the ingredient's **most specific** `fpc_category` term
2. the same field on each **ancestor** of that term — inheritance
3. `{theme}/images/categories/{slug}.svg` — filesystem default (directory does not exist yet)
4. nothing

The icon is uploaded once on the category term. **There is deliberately no per-ingredient
field** — an earlier draft had one and it was removed as confusing. Products needing a different
icon are handled with a **subcategory**: a Soy child term under Plant Protein carrying its own
icon, with every ingredient under it inheriting. A child term left without an icon falls through
to its parent, so only the ones that genuinely differ need an upload.

This means the icon always follows the category tree, and there is exactly one place to set it.

`category_icon_svg` was the predecessor and has been **removed** — field and template code both.
Terms edited before the change may still carry orphaned `category_icon_svg` / `_category_icon_svg`
meta rows; they are inert and can be dropped whenever convenient. The SVG attachments those rows
pointed at are untouched and still in the media library.

Don't confuse any of this with `category_grid_icon`, which is the category's card image in the
browse grid and is still live.

**Product Details group** (on `fpc_ingredient`)**:**
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
