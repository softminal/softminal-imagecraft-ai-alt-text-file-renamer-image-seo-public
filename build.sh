#!/bin/bash

# Build script for ImageCraft Plugin
# This script builds production assets and creates a zip file ready for distribution

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Get the plugin directory
PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_NAME="imagecraft-ai-alt-text-file-renamer-image-seo"
BUILD_DIR="$PLUGIN_DIR/build"
ZIP_NAME="${PLUGIN_NAME}.zip"

echo -e "${GREEN}🚀 Starting build process for ${PLUGIN_NAME}...${NC}"

# Step 1: Check if yarn/npm is available
if command -v yarn &> /dev/null; then
    BUILD_CMD="yarn"
    BUILD_SCRIPT="prod"
elif command -v npm &> /dev/null; then
    BUILD_CMD="npm"
    BUILD_SCRIPT="run prod"
else
    echo -e "${RED}❌ Error: Neither yarn nor npm is installed!${NC}"
    exit 1
fi

# Step 2: Install dependencies if node_modules doesn't exist
if [ ! -d "$PLUGIN_DIR/node_modules" ]; then
    echo -e "${YELLOW}📦 Installing dependencies...${NC}"
    $BUILD_CMD install
fi

# Step 3: Build production assets
echo -e "${GREEN}🔨 Building production assets...${NC}"
$BUILD_CMD $BUILD_SCRIPT

if [ $? -ne 0 ]; then
    echo -e "${RED}❌ Build failed!${NC}"
    exit 1
fi

echo -e "${GREEN}✅ Production build completed!${NC}"

# Step 4: Create build directory
if [ -d "$BUILD_DIR" ]; then
    echo -e "${YELLOW}🧹 Cleaning previous build...${NC}"
    rm -rf "$BUILD_DIR"
fi

mkdir -p "$BUILD_DIR/$PLUGIN_NAME"

# Step 5: Copy required files and directories
echo -e "${GREEN}📦 Copying files...${NC}"

# Copy directories
if [ -d "$PLUGIN_DIR/assets" ]; then
    cp -r "$PLUGIN_DIR/assets" "$BUILD_DIR/$PLUGIN_NAME/"
    echo "  ✓ assets/"
fi

if [ -d "$PLUGIN_DIR/config" ]; then
    cp -r "$PLUGIN_DIR/config" "$BUILD_DIR/$PLUGIN_NAME/"
    echo "  ✓ config/"
fi

if [ -d "$PLUGIN_DIR/includes" ]; then
    cp -r "$PLUGIN_DIR/includes" "$BUILD_DIR/$PLUGIN_NAME/"
    echo "  ✓ includes/"
fi

if [ -d "$PLUGIN_DIR/vendor" ]; then
    cp -r "$PLUGIN_DIR/vendor" "$BUILD_DIR/$PLUGIN_NAME/"
    echo "  ✓ vendor/"
fi

if [ -d "$PLUGIN_DIR/languages" ]; then
    cp -r "$PLUGIN_DIR/languages" "$BUILD_DIR/$PLUGIN_NAME/"
    echo "  ✓ languages/"
fi

# resources/ — only copy static assets not processed by Vite (CSS, images)
# All JS files (main, gutenberg, kadence, media) are built into assets/ by Vite.
RESOURCES_DEST="$BUILD_DIR/$PLUGIN_NAME/resources"
mkdir -p "$RESOURCES_DEST/admin"
if [ -d "$PLUGIN_DIR/resources/admin/css" ]; then
    cp -r "$PLUGIN_DIR/resources/admin/css" "$RESOURCES_DEST/admin/"
fi
if [ -d "$PLUGIN_DIR/resources/images" ]; then
    cp -r "$PLUGIN_DIR/resources/images" "$RESOURCES_DEST/"
fi
# Add index.php for directory browsing protection
for dir in "$RESOURCES_DEST" "$RESOURCES_DEST/admin"; do
    if [ -f "$PLUGIN_DIR/index.php" ]; then
        cp "$PLUGIN_DIR/index.php" "$dir/"
    fi
done
echo "  ✓ resources/ (CSS and images only)"

# Copy files
if [ -f "$PLUGIN_DIR/composer.json" ]; then
    cp "$PLUGIN_DIR/composer.json" "$BUILD_DIR/$PLUGIN_NAME/"
    echo "  ✓ composer.json"
fi

# Dev config files (package.json, vite.config.js, tailwind.config.js, postcss.config.js)
# are intentionally excluded from the distribution ZIP

if [ -f "$PLUGIN_DIR/imagecraft-ai-alt-text-file-renamer-image-seo.php" ]; then
    cp "$PLUGIN_DIR/imagecraft-ai-alt-text-file-renamer-image-seo.php" "$BUILD_DIR/$PLUGIN_NAME/"
    echo "  ✓ imagecraft-ai-alt-text-file-renamer-image-seo.php"
fi

# composer.lock is intentionally excluded from the distribution ZIP

# Copy readme.txt for WordPress.org (not README.md which is for development)
if [ -f "$PLUGIN_DIR/readme.txt" ]; then
    cp "$PLUGIN_DIR/readme.txt" "$BUILD_DIR/$PLUGIN_NAME/"
    echo "  ✓ readme.txt"
fi

# Copy root index.php for directory browsing protection
if [ -f "$PLUGIN_DIR/index.php" ]; then
    cp "$PLUGIN_DIR/index.php" "$BUILD_DIR/$PLUGIN_NAME/"
    echo "  ✓ index.php"
fi

# Step 6: Ensure .dev sentinel file is NOT in the build (controls ICAIS_DEV_MODE)
echo -e "${GREEN}🔧 Ensuring dev mode is off for production...${NC}"
if [ -f "$BUILD_DIR/$PLUGIN_NAME/.dev" ]; then
    rm "$BUILD_DIR/$PLUGIN_NAME/.dev"
fi
echo "  ✓ .dev sentinel removed (ICAIS_DEV_MODE will be false)"

# Step 7: Create zip file
echo -e "${GREEN}📦 Creating zip file...${NC}"
cd "$BUILD_DIR"

# Remove old zip if exists (from root directory)
if [ -f "$PLUGIN_DIR/$ZIP_NAME" ]; then
    rm "$PLUGIN_DIR/$ZIP_NAME"
fi

# Create zip file
zip -r "$PLUGIN_DIR/$ZIP_NAME" "$PLUGIN_NAME" -q

if [ $? -eq 0 ]; then
    ZIP_PATH="$PLUGIN_DIR/$ZIP_NAME"
    ZIP_SIZE=$(du -h "$ZIP_PATH" | cut -f1)
    echo -e "${GREEN}✅ Zip file created successfully!${NC}"
    echo -e "${GREEN}📦 Location: $ZIP_PATH${NC}"
    echo -e "${GREEN}📊 Size: $ZIP_SIZE${NC}"
else
    echo -e "${RED}❌ Failed to create zip file!${NC}"
    exit 1
fi

# Step 8: Cleanup build directory (optional - comment out if you want to keep it)
# echo -e "${YELLOW}🧹 Cleaning up...${NC}"
# rm -rf "$BUILD_DIR/$PLUGIN_NAME"

echo -e "${GREEN}🎉 Build process completed successfully!${NC}"
