# Farbest Product Catalog

Custom WordPress plugin replacing WooCommerce with a streamlined product catalog solution featuring advanced filtering, search, and automated email routing.

## Overview

- **Client**: Farbest (via BeckerGuerry)
- **Version**: 1.1.0
- **WordPress**: 6.0+
- **PHP**: 7.4+
- **Dependencies**: Advanced Custom Fields Pro

## Features

- Custom Post Type for products with comprehensive ACF field groups
- Hierarchical taxonomy system (categories, subcategories, claims, certifications)
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
4. Configure email routing in Products → Email Settings
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
│   ├── single-product.php
│   ├── archive-product.php
│   ├── taxonomy-product-category.php
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

### Products

Add products through WordPress admin under **Products → Add New**. Each product includes:

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

Configure representative email routing in **Products → Email Settings**:

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
│   ├── single-product.php
│   ├── archive-product.php
│   └── taxonomy-product-category.php
```

## REST API Endpoints

### Get Products

```
GET /wp-json/farbest/v1/products
```

Parameters:
- `category` - Filter by category slug
- `search` - Search term
- `page` - Page number
- `per_page` - Results per page (default: 12)

### Get Single Product

```
GET /wp-json/farbest/v1/products/{id}
```

### Submit Contact Form

```
POST /wp-json/farbest/v1/submit-contact
```

## Hooks & Filters

### Actions

- `fpc_before_product_content` - Before single product content
- `fpc_after_product_content` - After single product content
- `fpc_contact_form_submitted` - After successful form submission

### Filters

- `fpc_products_per_page` - Products per page (default: 12)
- `fpc_email_subject` - Customize email subject
- `fpc_email_message` - Customize email message

## Support

For support, contact BeckerGuerry development team.

## License

GPL v2 or later
