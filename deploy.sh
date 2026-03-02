#!/bin/bash
# Creates a production-ready zip for WordPress deployment.
# Excludes node_modules, source files, and dev config.

PLUGIN_SLUG="farbest-product-catalog"
PLUGIN_DIR="$(cd "$(dirname "$0")" && pwd)"
PARENT_DIR="$(dirname "$PLUGIN_DIR")"
ZIP_PATH="$PLUGIN_DIR/$PLUGIN_SLUG.zip"

# Remove old zip if it exists
[ -f "$ZIP_PATH" ] && rm "$ZIP_PATH"

# Zip from parent so the archive contains farbest-product-catalog/ at the top level
cd "$PARENT_DIR"

zip -r "$ZIP_PATH" "$PLUGIN_SLUG" \
  --exclude "$PLUGIN_SLUG/node_modules/*" \
  --exclude "$PLUGIN_SLUG/assets/src/*" \
  --exclude "$PLUGIN_SLUG/package.json" \
  --exclude "$PLUGIN_SLUG/package-lock.json" \
  --exclude "$PLUGIN_SLUG/deploy.sh" \
  --exclude "$PLUGIN_SLUG/README.md" \
  --exclude "$PLUGIN_SLUG/*.zip" \
  --exclude "$PLUGIN_SLUG/.git/*" \
  --exclude "$PLUGIN_SLUG/.gitignore"

echo ""
echo "Deploy zip ready: $ZIP_PATH"
echo "Upload to WordPress staging via Plugins > Add New > Upload Plugin."
