#!/bin/bash

# Build script for WP AI Alt Text Plugin
# This script builds production assets and creates a zip file ready for distribution

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Get the plugin directory
PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_NAME="wp-ai-alt-text"
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

# Copy files
if [ -f "$PLUGIN_DIR/composer.json" ]; then
    cp "$PLUGIN_DIR/composer.json" "$BUILD_DIR/$PLUGIN_NAME/"
    echo "  ✓ composer.json"
fi

if [ -f "$PLUGIN_DIR/wp-ai-alt-text.php" ]; then
    cp "$PLUGIN_DIR/wp-ai-alt-text.php" "$BUILD_DIR/$PLUGIN_NAME/"
    echo "  ✓ wp-ai-alt-text.php"
fi

# Copy composer.lock if exists
if [ -f "$PLUGIN_DIR/composer.lock" ]; then
    cp "$PLUGIN_DIR/composer.lock" "$BUILD_DIR/$PLUGIN_NAME/"
    echo "  ✓ composer.lock"
fi

# Copy README if exists
if [ -f "$PLUGIN_DIR/README.md" ]; then
    cp "$PLUGIN_DIR/README.md" "$BUILD_DIR/$PLUGIN_NAME/"
    echo "  ✓ README.md"
fi

# Step 6: Create zip file
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

# Step 7: Cleanup build directory (optional - comment out if you want to keep it)
# echo -e "${YELLOW}🧹 Cleaning up...${NC}"
# rm -rf "$BUILD_DIR/$PLUGIN_NAME"

echo -e "${GREEN}🎉 Build process completed successfully!${NC}"
