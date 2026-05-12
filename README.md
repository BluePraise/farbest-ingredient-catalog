# Farbest Product Catalog

Custom WordPress plugin replacing WooCommerce with a streamlined product catalog solution featuring advanced filtering, search, and automated email routing.

## Overview

- **Client**: Farbest (via BeckerGuerry)
- **Version**: 1.5.0
- **WordPress**: 6.0+
- **PHP**: 7.4+
- **Dependencies**: Advanced Custom Fields Pro

## Features

- Custom Post Type for ingredients with comprehensive ACF field groups
- Taxonomy system for ingredient categories, claims, certifications, and applications
- React-powered filtering and search interface
- Automated email routing based on sales representative codes
- Contact form with product sheet and quote requests
- REST API endpoints for frontend integration
- Canonical ingredient archive at `/ingredients/` with plugin-owned hero/layout
- Template override system for theme customization
- WP-CLI migration tool for WooCommerce products

## Installation

1. Upload the plugin to `/wp-content/plugins/farbest-product-catalog/`
2. Install Advanced Custom Fields Pro if not already installed
3. Activate the plugin through WordPress admin
4. Configure email routing in Ingredients → Email Settings
5. Run `npm install` and `npm run build` to compile React assets

## Development

### Build Assets

```bash
# Install dependencies
npm install

# Development mode (watch)
npm start

# Production build
npm run build
```

### File Structure

```
farbest-product-catalog/
├── farbest-product-catalog.php    # Main plugin file
├── includes/                       # PHP classes
│   ├── class-post-types.php
│   ├── class-taxonomies.php
│   ├── class-acf-fields.php
│   ├── class-contact-form.php
│   ├── class-email-routing.php
│   ├── class-template-loader.php
│   └── class-migration.php
├── templates/                      # Template files
│   ├── single-ingredient.php
│   ├── archive-ingredient.php
│   └── contact-form.php
├── assets/
│   ├── src/                       # React source
│   │   ├── index.js
│   │   ├── components/
│   │   └── styles/
│   ├── build/                     # Compiled assets
│   ├── css/                       # Admin CSS
│   └── js/                        # Admin JS
└── package.json
```

## Usage

### Ingredients

Add ingredients through WordPress admin under **Ingredients → Add New**. Each entry includes:

- Basic information (title, description, featured image)
- Product details (applications, packaging, product sheet PDF)
- Specifications (protein content, moisture, pH, etc.)
- Sales representative routing codes

### Contact Form

Insert contact form using shortcode:

```php
[fpc_contact_form product_id="123"]
```

Or use in templates:

```php
<?php echo do_shortcode('[fpc_contact_form product_id="' . get_the_ID() . '"]'); ?>
```

### Email Routing

Configure representative email routing in **Ingredients → Email Settings**:

```
101|john@farbest.com
102|jane@farbest.com
```

### Migration

Migrate WooCommerce products using WP-CLI:

```bash
# Dry run (no changes)
wp farbest migrate --dry-run

# Migrate all products
wp farbest migrate

# Migrate limited number
wp farbest migrate --limit=50
```

## Template Customization

Override templates by copying them to your theme:

```
your-theme/
├── farbest-catalog/
│   ├── single-ingredient.php
│   ├── archive-ingredient.php
│   └── contact-form.php
```

## REST API Endpoints

### Get Ingredients

```
GET /wp-json/farbest/v1/ingredients
```

Parameters:
- `categories` - Filter by ingredient category slug(s)
- `claims` - Filter by claim slug(s)
- `certifications` - Filter by certification slug(s)
- `applications` - Filter by application slug(s)
- `search` - Search term
- `orderby` - Sort field (`name` or `date`)
- `order` - Sort direction (`ASC` or `DESC`)
- `page` - Page number
- `per_page` - Results per page (default: 12)

### Get Single Ingredient

```
GET /wp-json/farbest/v1/ingredients/{id}
```

### Get Filter Options

```
GET /wp-json/farbest/v1/filter-options
```

### Submit Contact Form

```
POST /wp-json/farbest/v1/submit-contact
```

## Hooks & Filters

### Filters

- `fpc_email_subject` - Customize email subject
- `fpc_email_message` - Customize email message

## Support

For support, contact BeckerGuerry development team.

## License

GPL v2 or later
