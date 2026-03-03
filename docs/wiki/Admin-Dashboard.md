# Admin Dashboard

The JARVIS AI admin dashboard provides configuration, monitoring, and management pages outside the block editor.

## Menu Location

**JARVIS AI** appears as a top-level menu item in the WordPress admin sidebar with the following subpages:

1. Dashboard
2. Settings
3. History
4. Schedules
5. Capabilities
6. Help
7. Usage

## Pages

### Dashboard

The main overview page. Displays:

- **Stat cards** -- Total actions, conversations, actions executed, active schedules
- **Recent activity** -- Last 10 executed actions with status
- **Quick actions** -- Shortcuts to common tasks
- **AI Pulse** -- Aggregated AI news feed (cached 12 hours)

### Settings

Plugin configuration page. Sections:

- **AI Backend** -- Choose OpenRouter or Direct Providers
- **API Keys** -- Enter/update provider API keys (encrypted on save)
- **Default Model** -- Select default model from available options
- **Allowed Roles** -- Choose which WordPress roles can use JARVIS
- **Brand Presets** -- Configure brand name, tagline, colors, tone, font preference
- **Rate Limits** -- Set per-minute and daily request limits

### History

Paginated list of all conversations for the current user:

- Search and filter conversations
- View full conversation with messages
- Rename conversations
- Delete individual or bulk delete
- Conversation metadata (model, tokens, timestamps)

### Schedules

Manage automated task chains:

- View all scheduled tasks with next run time
- Pause, resume, or delete tasks
- View action chain details
- Monitor last run status

### Capabilities

Reference page listing all 77 actions with their required WordPress capabilities. Helps administrators understand what each role can do.

### Help

Getting started guide, FAQ, and links to documentation.

### Usage

Token usage statistics and cost estimates per provider and model.

## UI Framework

### Force UI + Tailwind CSS

Admin pages use Force UI components styled with Tailwind CSS utility classes:

- **Force UI** provides pre-built React components (buttons, cards, inputs, modals, etc.)
- **Tailwind CSS** provides utility classes for layout and custom styling
- Installed with `npm install --legacy-peer-deps` due to peer dependency conflicts

### PageLayout

All admin pages are wrapped in the `PageLayout` component which provides:

- Consistent page header with title
- Navigation tabs for switching between subpages
- Content area with proper padding and max-width
- Responsive layout

### StatCard

Reusable dashboard card component displaying:

- Icon (Lucide React)
- Label and value
- Accent color coding
- Trend indicator (optional)

## Asset Loading

Admin assets are enqueued only on JARVIS AI admin pages:

```php
add_action( 'admin_enqueue_scripts', function( $hook ) {
    if ( strpos( $hook, 'jarvis-ai' ) === false ) {
        return;
    }
    // Enqueue admin bundle
} );
```

The `jarvisAiData` script localization provides:

- REST API nonce
- REST API base URL
- Current user info
- Plugin version
- Feature flags

## See Also

- [Frontend Architecture](Frontend-Architecture) -- Build system and styling
- [REST API Reference](REST-API-Reference) -- Endpoints the dashboard consumes
- [Environment Configuration](Environment-Configuration) -- Settings details
