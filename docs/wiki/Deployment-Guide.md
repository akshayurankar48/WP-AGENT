# Deployment Guide

## Production Build

### 1. Build Frontend Assets

```bash
npm run build
```

This compiles all three entry points (editor, admin, drawer) into the `build/` directory with minified, hashed output and `.asset.php` dependency manifests.

### 2. Create Release ZIP

```bash
npx grunt release
```

Produces `jarvis-ai-{version}.zip` -- a clean distributable that excludes development files.

### Excluded from ZIP

The release ZIP excludes:

- `node_modules/`
- `src/` (source React files)
- `tests/`
- `.claude/`
- `docs/`
- `.github/`
- `.gitignore`
- `package.json` / `package-lock.json`
- `composer.json` / `composer.lock`
- `phpcs.xml.dist` / `phpunit.xml.dist`
- `Gruntfile.js`
- `.eslintrc` / `.prettierrc` / `.stylelintrc`

### Included in ZIP

- `jarvis-ai.php` (entry point)
- `plugin-loader.php`
- `actions/`, `ai/`, `core/`, `rest/`, `admin/`, `patterns/`, `integrations/`, `lib/`
- `build/` (compiled assets)
- `assets/` (animations, static files)
- `uninstall.php`
- `readme.txt`

## Version Bump

Update the version string in these locations before release:

| File | Location |
|------|----------|
| `jarvis-ai.php` | Plugin header `Version:` field |
| `jarvis-ai.php` | `JARVIS_AI_VER` constant |
| `readme.txt` | `Stable tag:` field |
| `package.json` | `version` field |

## Deployment Methods

### Manual Upload

1. Build the ZIP with `npx grunt release`
2. Go to **Plugins > Add New > Upload Plugin**
3. Upload and activate

### FTP / SFTP

1. Build with `npm run build`
2. Upload the entire `jarvis-ai/` directory (excluding dev files) to `wp-content/plugins/`
3. Activate via **Plugins** menu

### WP-CLI

```bash
wp plugin install jarvis-ai-1.0.0.zip --activate
```

## Post-Deployment

On activation, the plugin automatically:

1. Creates or updates the 6 custom database tables
2. Stores activation timestamp
3. Flushes rewrite rules

No manual migration steps are needed. The `Database::maybe_upgrade()` method runs on every `admin_init` and applies schema changes via `dbDelta()`.

## Rollback

To roll back to a previous version:

1. Deactivate the current version
2. Delete the plugin files
3. Upload the previous version's ZIP
4. Activate

Database tables are preserved across versions. The schema migration system only adds columns, never removes them.

## See Also

- [Environment Configuration](Environment-Configuration) -- Build commands
- [Testing Guide](Testing-Guide) -- Pre-release testing
- [Contributing Guide](Contributing-Guide) -- Development workflow
