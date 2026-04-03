# SVN Release Guide — WordPress.org

## Prerequisites

- SVN client installed (`svn --version`)
- WordPress.org plugin account with commit access
- Plugin build ready (`./build.sh`)

## First Time Setup

```bash
# Checkout the SVN repository
svn checkout https://plugins.svn.wordpress.org/imagecraft-ai-alt-text-file-renamer-image-seo /path/to/svn/imagecraft-ai-alt-text-file-renamer-image-seo
```

## Release Steps

### 1. Build the plugin

```bash
cd /path/to/plugin/source
./build.sh
```

### 2. Replace trunk files

```bash
# Delete old trunk contents
rm -rf /path/to/svn/trunk/*

# Copy new build files to trunk
cp -r build/imagecraft-ai-alt-text-file-renamer-image-seo/* /path/to/svn/trunk/
```

### 3. Go to SVN directory

```bash
cd /path/to/svn/imagecraft-ai-alt-text-file-renamer-image-seo
```

### 4. Check what changed

```bash
svn status
```

### 5. Add new files

```bash
svn add --force trunk/
```

### 6. Remove deleted files

```bash
svn status | grep '^\!' | awk '{print $2}' | xargs svn delete --force
```

### 7. Create the version tag

```bash
svn cp trunk tags/1.3.0
```

### 8. Commit

```bash
svn commit -m "Release 1.3.0 - Kadence Blocks integration, wp-config.php API key support, daily usage tracking"
```

Enter your WordPress.org username and password when prompted.

## Verify

After commit, check:
- https://wordpress.org/plugins/imagecraft-ai-alt-text-file-renamer-image-seo/
- Version should update within 5-10 minutes
- Changelog should show 1.3.0 entries

## Rollback (if needed)

```bash
# Revert trunk to previous tag
svn rm trunk
svn cp tags/1.2.1 trunk
svn commit -m "Rollback to 1.2.1"
```

## Notes

- Always tag before committing — WordPress.org serves the tagged version, not trunk
- `Stable tag` in readme.txt must match the tag directory name
- Screenshots go in the `assets/` directory (not `trunk/assets/`)
- Banner and icon images also go in SVN `assets/` (e.g., `banner-772x250.png`, `icon-256x256.png`)
