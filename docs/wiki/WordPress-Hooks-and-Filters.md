# WordPress Hooks and Filters

JARVIS AI integrates with WordPress through standard hooks and filters. This page documents the key hooks the plugin uses.

## Initialization

### `plugins_loaded`

Runs the plugin autoloader and initializes core classes.

### `admin_init`

- **Database migration:** `Database::maybe_upgrade()` checks schema version and runs `dbDelta()` if needed
- **Settings registration:** Registers plugin settings with the Settings API

## Admin

### `admin_menu`

Registers the JARVIS AI top-level menu and all subpages:

- Dashboard, Settings, History, Schedules, Capabilities, Help, Usage

### `admin_enqueue_scripts`

Conditionally enqueues admin assets:

- **Admin bundle** (`build/index.js`) -- only on JARVIS AI admin pages
- **Drawer bundle** (`build/drawer.js`) -- on other admin pages
- **Admin styles** (`build/index.css`, `build/style-index.css`)
- Localizes `jarvisAiData` with nonce, REST URL, user info

## Editor

### `enqueue_block_editor_assets`

Enqueues the editor sidebar bundle:

- **Editor bundle** (`build/editor.js`) -- block editor chat sidebar
- **Editor styles** (`build/editor.css`)
- Localizes `jarvisAiData` with nonce, REST URL, post context

## REST API

### `rest_api_init`

Registers all REST routes under the `jarvis-ai/v1` namespace:

- Chat and stream controllers
- History controller (CRUD)
- Settings controller
- Action controller (execute, undo)
- Stats, Schedules, AI Pulse, A/B tracking controllers

See [REST API Reference](REST-API-Reference) for all 17 endpoints.

## Frontend

### `wp_enqueue_scripts`

Conditionally loads animation assets on the frontend:

```php
// Only loads when post content contains 'wpa-' class names
if ( strpos( $post->post_content, 'wpa-' ) !== false ) {
    wp_enqueue_style( 'jarvis-ai-animations' );
    wp_enqueue_script( 'jarvis-ai-animations' );
}
```

This keeps frontend performance impact minimal -- no assets load unless the page uses JARVIS-generated animations.

## Activation / Deactivation

### `register_activation_hook`

- Creates all 6 custom database tables via `Database::activate()`
- Stores activation timestamp (`jarvis_ai_activated_at`)
- Flushes rewrite rules

### `register_deactivation_hook`

- Cleans up all `jarvis_ai_*` transients
- Flushes rewrite rules

### Uninstall (`uninstall.php`)

- Drops all 6 custom tables
- Deletes all `jarvis_ai_*` options
- Full clean removal

## Cron

### `jarvis_ai_run_scheduled_task`

Custom cron hook for executing scheduled task chains. Fired by `wp_schedule_single_event()` based on each task's `next_run` time.

### `jarvis_ai_cleanup_exports`

Scheduled cleanup hook for temporary export files. Runs after a delay to allow the user to download the file.

## Custom Hooks

JARVIS AI does not currently expose public action/filter hooks for third-party extension. The plugin prefix is `jarvis_ai_` for all internal hooks and option names.

## See Also

- [Architecture Overview](Architecture-Overview) -- Plugin structure and flow
- [REST API Reference](REST-API-Reference) -- All registered endpoints
- [Security Model](Security-Model) -- Nonce and capability checks
