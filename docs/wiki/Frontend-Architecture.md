# Frontend Architecture

JARVIS AI's frontend is built with React 18 using `@wordpress/scripts` (webpack). It has three separate entry points compiled into independent bundles.

## Entry Points

| Entry | File | Hook | Purpose |
|-------|------|------|---------|
| Editor | `src/editor.js` | `enqueue_block_editor_assets` | Gutenberg PluginSidebar chat panel |
| Admin | `src/index.js` | `admin_enqueue_scripts` | Admin dashboard pages |
| Drawer | `src/drawer.js` | `admin_enqueue_scripts` | Floating chat drawer on non-editor admin pages |

### Editor (`src/editor.js`)

Registers a `PluginSidebar` via `@wordpress/plugins`. Renders the chat interface inside the block editor. Enqueued only on editor screens.

### Admin (`src/index.js`)

Renders the full admin dashboard (Dashboard, Settings, History, Schedules, etc.) into a `#jarvis-ai-admin` root element. Enqueued only on JARVIS AI admin pages.

### Drawer (`src/drawer.js`)

A floating chat drawer that appears on non-editor admin pages. Allows quick AI access without opening the block editor.

## Build System

- **Bundler:** `@wordpress/scripts` (wraps webpack)
- **Config:** Default wp-scripts config, no custom webpack overrides
- **Output:** `build/` directory with hashed filenames and `.asset.php` dependency files
- **Dev:** `npm start` for file watching with hot reload
- **Prod:** `npm run build` for minified, optimized bundles

## Directory Structure

```
src/
  editor.js           # Editor entry point
  index.js            # Admin entry point
  drawer.js           # Drawer entry point
  components/         # Shared React components
    ui/               # Reusable UI primitives (Force UI wrappers)
    PageLayout.jsx    # Admin page layout wrapper
    StatCard.jsx      # Dashboard stat cards
  data/               # Data utilities
  editor/             # Editor-specific components
  drawer/             # Drawer-specific components
  hooks/              # Custom React hooks
  pages/              # Admin page components (Dashboard, Settings, etc.)
  store/              # Redux store (actions, reducer, selectors)
  utils/              # Helper utilities
  style.css           # Global admin styles
```

## Styling Strategy

### Editor Sidebar: Emotion CSS

The editor sidebar uses `@emotion/css` for styling. Tailwind CSS does **not** work inside Gutenberg's PluginSidebar because it renders in a body-level portal where Tailwind's scoped styles do not apply. Emotion generates inline style tags that work regardless of DOM position.

### Admin Dashboard: Tailwind + Force UI

Admin pages use Tailwind CSS with Force UI component library. Force UI provides pre-built components styled with Tailwind utility classes. Installed with `npm install --legacy-peer-deps` due to peer dependency conflicts.

### Icons: Lucide React

All icons come from `lucide-react`. Consistent sizing and stroke width across the interface.

## WordPress Integration

- **State management:** `@wordpress/data` with `createReduxStore` (see [Redux Store](Redux-Store))
- **API calls:** `@wordpress/api-fetch` with automatic nonce handling
- **Plugins API:** `@wordpress/plugins` for PluginSidebar registration
- **Block editor:** `@wordpress/block-editor` for editor integration hooks
- **i18n:** `@wordpress/i18n` for translatable strings

## Asset Enqueuing

Assets are enqueued conditionally:

- Editor bundle: only on block editor pages (`enqueue_block_editor_assets`)
- Admin bundle: only on JARVIS AI admin pages (checked via `get_current_screen()`)
- Drawer bundle: on all admin pages except JARVIS AI pages and the editor
- Animation CSS: only when post content contains `wpa-` class names

## See Also

- [Redux Store](Redux-Store) -- State management details
- [Editor Sidebar](Editor-Sidebar) -- Chat panel components
- [Admin Dashboard](Admin-Dashboard) -- Admin page details
