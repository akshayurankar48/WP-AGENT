# WP Agent - Phase 1 Implementation Plan

> **Status**: APPROVED - Final Decisions Locked
> **Repo**: https://github.com/akshayurankar48/WP-AGENT
> **Base**: BSF plugin boilerplate (`wp-plugin-base`) - single commit `6dab8e6`
> **Date**: 2026-02-28

---

## What We're Building

An AI-powered WordPress admin assistant that lives natively in the **Gutenberg editor sidebar**. Users interact through natural language, and the AI can read/insert/modify Gutenberg blocks, create posts, update settings, and more. The chatbot communicates with **OpenRouter** for multi-model AI (GPT-4o, Claude Sonnet, Gemini Flash).

---

## Final Design Decisions (Interview Outcomes)

| Area | Phase 1 Decision | Phase 2 Deferred |
|------|-----------------|------------------|
| **Chat UI** | PluginSidebar only (Gutenberg editor) | Centered modal command bar (Cmd/Ctrl+K) on all admin pages |
| **API key model** | BYOK (user provides own OpenRouter key) | BSF shared key via proxy |
| **Actions** | 12 core actions across 4 categories | 20+ actions, full admin coverage |
| **AI autonomy** | Always confirm (Plan-Confirm-Execute) | Configurable per-action auto-execute |
| **Block manipulation** | Client-side via JSON instructions (React dispatch, no reload) | - |
| **Tool calling** | OpenRouter function/tool calling API | - |
| **Tool execution** | Split: block ops client-side (React), server ops server-side (PHP) | - |
| **Multi-action UX** | Cherry-pick individual actions (checkboxes) | - |
| **Editor awareness** | Basic: selected block clientId + content | Full: cursor position, highlighted text, selection range |
| **Chat visual style** | ChatGPT style (full-width, alternating bg) + compact variant for sidebar | - |
| **Conversation scope** | Both per-post and global (user chooses) | Shared state between sidebar + modal |
| **State management** | `@wordpress/data` (createReduxStore) | - |
| **Streaming fallback** | cURL WRITEFUNCTION primary, `wp_remote_post` non-streaming fallback | - |
| **Output buffering** | Aggressively flush all buffers | - |
| **Context depth** | Standard (~1500 tokens: site info + plugins + theme + capabilities + current post blocks) | Rich context with recent posts, settings, menus |
| **API key encryption** | `openssl_encrypt` + `wp_salt('auth')`. Accept tradeoff: key invalid if salts change. | - |
| **Undo behavior** | Move to trash (safer). Per-action appropriate undo. | - |
| **Responsive** | Desktop-only sidebar (>782px). No mobile sidebar. | Modal full-screen on mobile |
| **Data retention** | Auto-cleanup conversations after 30 days via WP cron | Configurable retention |
| **System prompt** | Hardcoded in PHP (not user-editable) | Filterable via PHP hook |
| **Error recovery** | Simple undo button (30s visible, then available in history) | AI self-correction |
| **Usage display** | No usage display. Silent rate limit enforcement. | Usage dashboard for admins |
| **Onboarding** | Inline setup wizard in sidebar (API key + model selector) | Admin notice + full settings page |
| **Multisite** | Single site only | Basic multisite support |
| **Quality focus** | AI accuracy is critical. Invest in prompt engineering + validation. | - |
| **Timeline** | No rush, do it right. Production-quality code per commit. | - |

### Phase 1 Actions (12 Core)

| # | Action | Category | Capability |
|---|--------|----------|------------|
| 1 | `create_post` | Content | `edit_posts` |
| 2 | `edit_post` | Content | `edit_posts` |
| 3 | `delete_post` | Content | `delete_posts` |
| 4 | `insert_blocks` | Content | `edit_posts` |
| 5 | `read_blocks` | Content | `read` |
| 6 | `update_settings` | Settings | `manage_options` |
| 7 | `manage_permalinks` | Settings | `manage_options` |
| 8 | `install_plugin` | Plugins | `install_plugins` |
| 9 | `activate_plugin` | Plugins | `activate_plugins` |
| 10 | `deactivate_plugin` | Plugins | `activate_plugins` |
| 11 | `create_user` | Users | `create_users` |
| 12 | `site_health` | System | `view_site_health_checks` |

### Phase 2 Additional Actions (Deferred)

`modify_blocks`, `upload_media`, `list_users`, `switch_theme`, `manage_menus`, `manage_widgets`, `update_plugin`, `delete_plugin`, `manage_categories`, `export_content`, `manage_roles`, `configure_reading`, `configure_writing`

---

## Boilerplate Current State

| Item | Current (Boilerplate) | Target (WP Agent) |
|------|----------------------|-------------------|
| Plugin slug | `wp-plugin-base` | `wp-agent` |
| Main file | `wp-plugin-base.php` | `wp-agent.php` |
| Namespace | `WPB` | `WPAgent` |
| Constant prefix | `WPB_` | `WP_AGENT_` |
| Text domain | `wp-plugin-base` | `wp-agent` |
| Filter prefix | `wpb_` | `wp_agent_` |
| Version | `0.0.1` | `1.0.0-alpha` |
| @wordpress/scripts | `^21.0.1` | `^27.0.0` |
| WPCS | `^2.2` | `^3.0` |
| PHP minimum | 5.6 | 7.4 |
| Plugin header | Generic boilerplate | WP Agent branded |

**Autoloader convention** (preserved from boilerplate):
- Class `WPAgent\AI\Open_Router_Client` resolves to `ai/open-router-client.php`
- Namespace separators become directory separators
- Underscores and CamelCase become hyphens, lowercased

---

## Commit Strategy (14 Atomic Commits)

Each commit produces a **working, non-breaking** plugin state. No commit depends on a future commit.

| # | Commit | Scope |
|---|--------|-------|
| 1 | `feat: adapt boilerplate to WP Agent scaffold` | Rename namespace/constants/text-domain, activation/deactivation hooks |
| 2 | `feat: database schema and migration system` | 4 custom tables + dbDelta migration |
| 3 | `feat: OpenRouter AI client with streaming` | cURL SSE streaming, prompt builder, model router, rate limiter |
| 4a | `feat: action system interface, registry, and content actions` | Interface, registry, 5 content actions |
| 4b | `feat: settings and plugin management actions` | 4 settings/plugin actions |
| 4c | `feat: user, system, and remaining actions` | 3 final actions (12 total) |
| 5 | `feat: core orchestrator and context collector` | Main brain, context gathering, checkpoint manager |
| 6 | `feat: REST API endpoints for chat and streaming` | 5 controllers with SSE streaming |
| 7 | `feat: admin menu, settings page, and asset enqueuing` | WP admin integration |
| 8 | `feat: build pipeline with Tailwind CSS and Force UI` | webpack, tailwind, postcss, Force UI |
| 9 | `feat: Gutenberg sidebar chatbot UI with Force UI` | React chat interface - store, components, hooks |
| 10 | `feat: admin settings page React app` | API key form, model selector, role permissions |
| 11 | `feat: CI workflow for code analysis` | GitHub Actions updates |
| 12 | `docs: README and inline documentation` | Installation, config, developer docs |

### Commit 1: `feat: adapt boilerplate to WP Agent scaffold`

**Scope**: Rename everything from boilerplate identity to WP Agent identity.

**Files modified:**
- `wp-plugin-base.php` -> `wp-agent.php` (rename + rewrite header & constants)
- `plugin-loader.php` (namespace `WPB` -> `WPAgent`, text domain, filter names)
- `uninstall.php` (new - clean uninstall handler)
- `package.json` (name, version, description, repository URL, scripts text-domain refs)
- `composer.json` (name, description, stubs script refs)
- `phpcs.xml` (text_domain element -> `wp-agent`)
- `phpstan.neon` (paths -> `wp-agent.php`, bootstrap refs)
- `.eslintrc.js` (allowedTextDomain -> `wp-agent`)
- `Gruntfile.js` (dest/archive refs -> `wp-agent`)
- `tests/php/stubs/wpb-constants.php` -> `tests/php/stubs/wp-agent-constants.php`

**Specific changes:**
```
Constants:   WPB_FILE -> WP_AGENT_FILE
             WPB_BASE -> WP_AGENT_BASE
             WPB_DIR  -> WP_AGENT_DIR
             WPB_URL  -> WP_AGENT_URL
             WPB_VER  -> WP_AGENT_VER

Namespace:   WPB\    -> WPAgent\
Filters:     wpb_*   -> wp_agent_*
Text domain: wp-plugin-base -> wp-agent
```

**Additions:**
- `register_activation_hook()` in main file (calls `WPAgent\Core\Database::activate()`)
- `register_deactivation_hook()` in main file (cleanup transients)
- `defined( 'ABSPATH' ) || exit;` guard in every PHP file
- Version constant `WP_AGENT_DB_VER` for migration tracking

**Verification:** Plugin activates in WP admin without errors. No PHP notices.

---

### Commit 2: `feat: database schema and migration system`

**Scope**: 4 custom DB tables + dbDelta migration system.

**New files:**
- `core/database.php` - `WPAgent\Core\Database` class

**Tables created on activation:**

```sql
-- {prefix}agent_conversations
CREATE TABLE {prefix}agent_conversations (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id         BIGINT UNSIGNED NOT NULL,
  post_id         BIGINT UNSIGNED DEFAULT NULL,
  title           VARCHAR(255)    NOT NULL DEFAULT '',
  status          VARCHAR(20)     NOT NULL DEFAULT 'active',
  model           VARCHAR(100)    NOT NULL DEFAULT '',
  tokens_used     INT UNSIGNED    NOT NULL DEFAULT 0,
  created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_user_id (user_id),
  KEY idx_post_id (post_id),
  KEY idx_status  (status)
) {charset_collate};

-- {prefix}agent_messages
CREATE TABLE {prefix}agent_messages (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  conversation_id BIGINT UNSIGNED NOT NULL,
  role            VARCHAR(20)     NOT NULL DEFAULT 'user',
  content         LONGTEXT        NOT NULL,
  metadata        LONGTEXT        DEFAULT NULL,
  tokens          INT UNSIGNED    NOT NULL DEFAULT 0,
  model           VARCHAR(100)    NOT NULL DEFAULT '',
  created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_conversation_id (conversation_id),
  KEY idx_role (role)
) {charset_collate};

-- {prefix}agent_checkpoints
CREATE TABLE {prefix}agent_checkpoints (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  conversation_id BIGINT UNSIGNED NOT NULL,
  message_id      BIGINT UNSIGNED NOT NULL,
  action_type     VARCHAR(100)    NOT NULL DEFAULT '',
  snapshot_before LONGTEXT        NOT NULL,
  snapshot_after  LONGTEXT        DEFAULT NULL,
  entity_type     VARCHAR(50)     NOT NULL DEFAULT '',
  entity_id       BIGINT UNSIGNED DEFAULT NULL,
  is_restored     TINYINT(1)      NOT NULL DEFAULT 0,
  created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_conversation_id (conversation_id),
  KEY idx_message_id (message_id)
) {charset_collate};

-- {prefix}agent_history
CREATE TABLE {prefix}agent_history (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id         BIGINT UNSIGNED NOT NULL,
  conversation_id BIGINT UNSIGNED DEFAULT NULL,
  action_type     VARCHAR(100)    NOT NULL DEFAULT '',
  action_data     LONGTEXT        DEFAULT NULL,
  result_status   VARCHAR(20)     NOT NULL DEFAULT '',
  result_message  TEXT            NOT NULL DEFAULT '',
  created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_user_id (user_id),
  KEY idx_conversation_id (conversation_id),
  KEY idx_action_type (action_type)
) {charset_collate};
```

**Implementation details:**
- Uses `dbDelta()` from `wp-admin/includes/upgrade.php`
- Version stored in `wp_options` as `wp_agent_db_version`
- `activate()` static method called from activation hook
- `maybe_upgrade()` checks version on `admin_init` for seamless upgrades
- All queries use `$wpdb->prepare()` with parameterized placeholders

**Note on ENUM**: Using `VARCHAR(20)` instead of `ENUM` for the `role` column. WordPress `dbDelta()` has known issues with ENUM parsing, and VARCHAR is more portable.

**Verification:** `SHOW TABLES LIKE '%agent%'` returns 4 tables. Deactivate/reactivate preserves data.

---

### Commit 3: `feat: OpenRouter AI client with streaming`

**Scope**: cURL-based SSE streaming client for OpenRouter API.

**New files:**
- `ai/open-router-client.php` - `WPAgent\AI\Open_Router_Client`
- `ai/prompt-builder.php` - `WPAgent\AI\Prompt_Builder`
- `ai/model-router.php` - `WPAgent\AI\Model_Router`
- `ai/rate-limiter.php` - `WPAgent\AI\Rate_Limiter`

**Open_Router_Client details:**
- `stream( $messages, $model, $callback )` - cURL with `CURLOPT_WRITEFUNCTION`
- `chat( $messages, $model )` - Non-streaming via `wp_remote_post()`
- Endpoint: `https://openrouter.ai/api/v1/chat/completions`
- Headers: `Authorization: Bearer {key}`, `HTTP-Referer`, `X-Title: WP Agent`
- API key stored in `wp_options` (encrypted with `wp_salt()` + openssl)
- Timeout handling: cURL `CURLOPT_TIMEOUT => 120`

**Prompt_Builder details:**
- `build_system_prompt( $context )` - Assembles system prompt with site context + available tools
- `build_messages( $history, $user_message )` - Formats for OpenRouter API
- Tool/function definitions in OpenRouter format for action execution

**Model_Router details:**
- Three tiers: `fast` (Gemini Flash), `balanced` (GPT-4o-mini), `powerful` (Claude Sonnet)
- Selection based on: message complexity (length, keywords), user preference, conversation history
- Fallback chain if primary model fails

**Rate_Limiter details:**
- Per-user limits via transients: `wp_agent_rate_{user_id}`
- Default: 30 requests/minute, 500 requests/day
- Admin-configurable via settings

**Verification:** PHP file loads without errors. No external calls until API key configured.

---

### Commit 4a: `feat: action system interface, registry, and content actions`

**Scope**: Action interface + registry + 5 content actions.

**New files:**
- `actions/action-interface.php` - `WPAgent\Actions\Action_Interface`
- `actions/action-registry.php` - `WPAgent\Actions\Action_Registry`
- `actions/create-post.php` - `WPAgent\Actions\Create_Post`
- `actions/edit-post.php` - `WPAgent\Actions\Edit_Post`
- `actions/delete-post.php` - `WPAgent\Actions\Delete_Post`
- `actions/insert-blocks.php` - `WPAgent\Actions\Insert_Blocks`
- `actions/read-blocks.php` - `WPAgent\Actions\Read_Blocks`

**Action_Interface contract:**
```php
interface Action_Interface {
    public function get_name(): string;          // 'create_post'
    public function get_description(): string;   // Human-readable
    public function get_schema(): array;         // JSON Schema for params (OpenRouter tool format)
    public function validate( array $params ): bool|WP_Error;
    public function execute( array $params ): array; // ['success' => bool, 'data' => mixed]
    public function get_required_capability(): string; // 'edit_posts'
    public function get_execution_context(): string;   // 'server' or 'client'
}
```

**Action_Registry:**
- Singleton with `register( Action_Interface $action )`
- `get( string $name )` returns action instance
- `get_all()` returns all registered actions
- `get_tool_definitions()` returns OpenRouter function/tool calling definitions
- Hook: `wp_agent_register_actions` for third-party extensions

**Content actions:**
- `create_post` - `wp_insert_post()` with blocks. Server-side. Capability: `edit_posts`
- `edit_post` - `wp_update_post()` with selective field updates. Server-side. Capability: `edit_posts`
- `delete_post` - `wp_trash_post()` (moves to trash, not permanent delete). Server-side. Capability: `delete_posts`
- `insert_blocks` - Returns block instructions for client-side execution. Client-side. Capability: `edit_posts`
- `read_blocks` - Returns simplified block tree from post_content. Server-side. Capability: `read`

**Verification:** Registry returns 5 actions with valid tool definitions.

---

### Commit 4b: `feat: settings and plugin management actions`

**Scope**: 4 more actions for settings and plugin management.

**New files:**
- `actions/update-settings.php` - `WPAgent\Actions\Update_Settings`
- `actions/manage-permalinks.php` - `WPAgent\Actions\Manage_Permalinks`
- `actions/install-plugin.php` - `WPAgent\Actions\Install_Plugin`
- `actions/activate-plugin.php` - `WPAgent\Actions\Activate_Plugin`

**Actions:**
- `update_settings` - Whitelist of allowed options. Server-side. Capability: `manage_options`
- `manage_permalinks` - Update permalink structure. Server-side. Capability: `manage_options`
- `install_plugin` - Uses Plugin_Upgrader class. Server-side. Capability: `install_plugins`
- `activate_plugin` - `activate_plugin()` core function. Server-side. Capability: `activate_plugins`

**Verification:** Registry returns 9 actions total.

---

### Commit 4c: `feat: user, system, and remaining actions`

**Scope**: Final 3 actions to complete the core 12.

**New files:**
- `actions/deactivate-plugin.php` - `WPAgent\Actions\Deactivate_Plugin`
- `actions/create-user.php` - `WPAgent\Actions\Create_User`
- `actions/site-health.php` - `WPAgent\Actions\Site_Health`

**Actions:**
- `deactivate_plugin` - `deactivate_plugins()` core function. Server-side. Capability: `activate_plugins`
- `create_user` - `wp_insert_user()` with role assignment. Server-side. Capability: `create_users`
- `site_health` - Runs WP Site Health checks, returns status summary. Server-side. Capability: `view_site_health_checks`

**Verification:** Registry returns all 12 core actions. `get_tool_definitions()` returns valid OpenRouter format.

---

### Commit 5: `feat: core orchestrator and context collector`

**Scope**: Main AI brain that coordinates requests, plus site context gathering.

**New files:**
- `core/orchestrator.php` - `WPAgent\Core\Orchestrator`
- `core/context-collector.php` - `WPAgent\Core\Context_Collector`
- `core/checkpoint-manager.php` - `WPAgent\Core\Checkpoint_Manager`
- `core/knowledge-base.php` - `WPAgent\Core\Knowledge_Base`

**Orchestrator flow:**
1. Receive user message + conversation context
2. Collect site context via `Context_Collector`
3. Build prompt via `Prompt_Builder` (system + context + history + tools)
4. Select model via `Model_Router`
5. Call `Open_Router_Client` (streaming or non-streaming)
6. Parse AI response for action plans
7. Store messages in DB
8. Return response (or stream chunks via callback)

**Context_Collector gathers:**
- Site info: name, URL, WP version, PHP version, theme
- Active plugins list (name + version)
- Current post context (if editing): title, content blocks (simplified), post type
- User role and capabilities
- Recent conversation history (last 10 messages)
- Available actions (from registry)

**Checkpoint_Manager:**
- `create_checkpoint( $conversation_id, $message_id, $action_type, $entity_type, $entity_id )` - Snapshots before state
- `complete_checkpoint( $checkpoint_id, $snapshot_after )` - Records after state
- `restore_checkpoint( $checkpoint_id )` - Reverts to before state
- Handles: posts (full post data), options (option value), blocks (serialized blocks)

**Knowledge_Base:**
- Persistent site knowledge stored in `wp_options` as `wp_agent_knowledge`
- Auto-learns: frequently used post types, common settings changed, user preferences
- Provides context hints to prompt builder

**Verification:** Orchestrator class instantiates. Context collector returns valid site data array.

---

### Commit 6: `feat: REST API endpoints for chat and streaming`

**Scope**: 5 REST controllers under `/wp-agent/v1/` namespace.

**New files:**
- `rest/chat-controller.php` - `WPAgent\REST\Chat_Controller`
- `rest/stream-controller.php` - `WPAgent\REST\Stream_Controller`
- `rest/history-controller.php` - `WPAgent\REST\History_Controller`
- `rest/settings-controller.php` - `WPAgent\REST\Settings_Controller`
- `rest/action-controller.php` - `WPAgent\REST\Action_Controller`

**Endpoints:**

| Method | Route | Controller | Capability |
|--------|-------|------------|------------|
| POST | `/wp-agent/v1/chat` | Chat_Controller | `edit_posts` |
| POST | `/wp-agent/v1/stream` | Stream_Controller | `edit_posts` |
| GET | `/wp-agent/v1/history` | History_Controller | `edit_posts` |
| GET | `/wp-agent/v1/history/{id}` | History_Controller | `edit_posts` |
| GET | `/wp-agent/v1/settings` | Settings_Controller | `manage_options` |
| POST | `/wp-agent/v1/settings` | Settings_Controller | `manage_options` |
| POST | `/wp-agent/v1/action/execute` | Action_Controller | per-action |
| POST | `/wp-agent/v1/action/undo` | Action_Controller | per-action |

**Stream_Controller (SSE) critical path:**
```php
public function stream( $request ) {
    // 1. Set SSE headers
    header( 'Content-Type: text/event-stream' );
    header( 'Cache-Control: no-cache' );
    header( 'X-Accel-Buffering: no' );

    // 2. Disable output buffering
    while ( ob_get_level() > 0 ) {
        ob_end_flush();
    }

    // 3. Remove time limit for streaming duration
    set_time_limit( 0 );

    // 4. Stream callback - sends SSE events + resets timer per chunk
    $callback = function( $chunk ) {
        echo "data: " . wp_json_encode( $chunk ) . "\n\n";
        flush();
        set_time_limit( 30 ); // Reset PHP timeout per chunk
    };

    // 5. Run orchestrator with streaming
    $orchestrator->stream( $message, $conversation_id, $callback );

    // 6. Send done event
    echo "data: [DONE]\n\n";
    flush();
    exit;
}
```

**Security on every endpoint:**
- `permission_callback` checks `current_user_can()`
- Nonce verified automatically by WP REST (via `X-WP-Nonce` header)
- All input sanitized (`sanitize_text_field`, `absint`, etc.)
- All DB queries use `$wpdb->prepare()`

**Registration:** All controllers register routes on `rest_api_init` hook via plugin-loader.

**Verification:** `curl /wp-json/wp-agent/v1/settings` returns 401 without auth, 200 with valid nonce.

---

### Commit 7: `feat: admin menu, settings page, and asset enqueuing`

**Scope**: WP admin integration - menu page, script/style loading.

**New files:**
- `admin/admin-menu.php` - `WPAgent\Admin\Admin_Menu`
- `admin/settings-page.php` - `WPAgent\Admin\Settings_Page`
- `admin/assets-manager.php` - `WPAgent\Admin\Assets_Manager`

**Admin_Menu:**
- Top-level menu: "WP Agent" with dashicon `dashicons-format-chat`
- Position: 30 (below Comments)
- Capability: `manage_options`
- Submenu: "Settings" (default), "History"

**Assets_Manager:**
- `enqueue_block_editor_assets` hook -> loads sidebar chat UI (from `build/index.js`)
- `admin_enqueue_scripts` hook -> loads settings page assets (from `build/admin/settings/index.js`)
- `wp_localize_script()` passes:
  ```php
  wp_localize_script( 'wp-agent-sidebar', 'wpAgentData', [
      'restUrl'    => rest_url( 'wp-agent/v1/' ),
      'nonce'      => wp_create_nonce( 'wp_rest' ),
      'hasApiKey'  => ! empty( get_option( 'wp_agent_api_key' ) ),
      'userId'     => get_current_user_id(),
      'userName'   => wp_get_current_user()->display_name,
      'userAvatar' => get_avatar_url( get_current_user_id() ),
      'postId'     => get_the_ID(),
      'models'     => Model_Router::get_available_models(),
      'version'    => WP_AGENT_VER,
  ] );
  ```
- **API key never exposed to client** - only boolean `hasApiKey`

**Settings_Page:**
- Renders React mount point: `<div id="wp-agent-settings"></div>`

**Verification:** "WP Agent" appears in admin menu. Opening Gutenberg editor loads sidebar script (console shows no errors).

---

### Commit 8: `feat: build pipeline with Tailwind CSS and Force UI`

**Scope**: webpack, Tailwind, PostCSS config + Force UI integration.

**Files modified:**
- `package.json` - Add dependencies (force-ui, tailwindcss, lucide-react, clsx, tailwind-merge)

**New files:**
- `webpack.config.js` - Extends `@wordpress/scripts` with 2 entry points
- `tailwind.config.js` - Force UI `withTW()` wrapper, `preflight: false`
- `postcss.config.js` - tailwindcss + autoprefixer
- `src/index.js` - Gutenberg sidebar entry (placeholder `registerPlugin`)
- `src/style.css` - Tailwind directives
- `src/admin/settings/index.js` - Settings page entry (placeholder React mount)

**webpack.config.js:**
```js
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
    ...defaultConfig,
    entry: {
        index: './src/index.js',            // Gutenberg sidebar
        'admin/settings/index': './src/admin/settings/index.js', // Settings page
    },
};
```

**tailwind.config.js:**
```js
const { withTW } = require( '@bsf/force-ui/dist/utils/withTW' );

module.exports = withTW( {
    content: [ './src/**/*.{js,jsx}' ],
    corePlugins: {
        preflight: false, // Don't reset WP admin styles
    },
} );
```

**package.json dependencies added:**
```json
{
  "dependencies": {
    "@bsf/force-ui": "git+https://github.com/brainstormforce/force-ui.git#1.7.9",
    "lucide-react": "^0.417.0",
    "clsx": "^2.1.1",
    "tailwind-merge": "^2.4.0"
  },
  "devDependencies": {
    "@wordpress/scripts": "^27.0.0",
    "tailwindcss": "^3.4.10",
    "autoprefixer": "^10.4.19"
  }
}
```

**WordPress externals** (NOT bundled, provided by WP runtime):
- `@wordpress/blocks`, `@wordpress/data`, `@wordpress/element`, `@wordpress/editor`
- `@wordpress/plugins`, `@wordpress/api-fetch`, `@wordpress/components`

**Verification:** `npm install && npm run build` succeeds. `build/index.js` and `build/admin/settings/index.js` generated.

---

### Commit 9: `feat: Gutenberg sidebar chatbot UI with Force UI`

**Scope**: Full React chat interface for the Gutenberg editor sidebar.

**New/modified files:**
```
src/
  index.js                    # registerPlugin with PluginSidebar
  style.css                   # Tailwind directives + sidebar-specific styles
  store/
    index.js                  # createReduxStore registration
    constants.js              # STORE_NAME = 'wp-agent', ACTION_TYPES
    reducer.js                # Chat state: messages, conversations, streaming, etc.
    actions.js                # Action creators (sendMessage, setStreaming, etc.)
    selectors.js              # getMessages, isStreaming, getCurrentConversation, etc.
  components/
    ChatPanel.jsx             # Root sidebar container
    MessageList.jsx           # Scrollable message list with auto-scroll
    MessageBubble.jsx         # User/assistant message with Avatar
    InputArea.jsx             # Textarea + Send/Stop button (Shift+Enter for newline)
    PlanViewer.jsx            # Plan-Confirm-Execute UI with action cards
    ActionCard.jsx            # Individual action display with status
    ActionHistory.jsx         # Past actions + undo buttons
    WelcomeScreen.jsx         # Inline setup wizard (API key + model selector, no redirect needed)
    StreamingText.jsx         # Animated streaming text with blinking cursor
    CheckpointBanner.jsx      # Undo notification after action execution
  hooks/
    useStreaming.js            # fetch() + ReadableStream SSE parsing
    useChat.js                # Send/receive orchestration + store dispatch
    useBlockEditor.js         # Gutenberg block manipulation (insert/modify/delete/read)
    useCheckpoint.js          # Create/restore checkpoints
  utils/
    blocks.js                 # Block manipulation helpers (simplify, serialize)
    api.js                    # apiFetch wrappers for REST endpoints
    markdown.js               # Markdown-to-React rendering for AI responses
    constants.js              # Route constants, STORE_NAME re-export
```

**PluginSidebar registration (src/index.js):**
```jsx
import { registerPlugin } from '@wordpress/plugins';
import { PluginSidebar } from '@wordpress/editor'; // NOT edit-post (deprecated WP 6.6+)
import { ChatPanel } from './components/ChatPanel';

registerPlugin( 'wp-agent', {
    render: () => (
        <PluginSidebar
            name="wp-agent-sidebar"
            title="WP Agent"
            icon={ /* Bot icon */ }
        >
            <ChatPanel />
        </PluginSidebar>
    ),
} );
```

**useStreaming hook (fetch + ReadableStream, NOT EventSource):**
```js
// EventSource only supports GET. We need POST for message body.
// fetch() + ReadableStream allows POST with SSE parsing.
const response = await fetch( restUrl + 'stream', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': nonce,
    },
    body: JSON.stringify( { message, conversation_id, post_id } ),
} );

const reader = response.body.getReader();
const decoder = new TextDecoder();
// Parse SSE "data: {...}\n\n" format in a loop
```

**useBlockEditor hook (Gutenberg integration):**
```js
import { useSelect, useDispatch } from '@wordpress/data';
import { createBlock } from '@wordpress/blocks';

// Read current blocks as simplified JSON
const readBlocks = () => { /* select('core/block-editor').getBlocks() */ };

// Insert blocks at position
const addBlocks = ( blockDefs, index ) => {
    const blocks = blockDefs.map( def => createBlock( def.name, def.attributes ) );
    dispatch('core/block-editor').insertBlocks( blocks, index );
};

// Modify block attributes
const modifyBlock = ( clientId, attributes ) => {
    dispatch('core/block-editor').updateBlockAttributes( clientId, attributes );
};

// Delete / Replace blocks
const deleteBlock = ( clientId ) => { /* removeBlock */ };
const replaceBlock = ( clientId, blockDefs ) => { /* replaceBlocks */ };
```

**Force UI components used:**
- `Button` - Send, stop, confirm, cancel, undo
- `Textarea` - Chat input (auto-resize)
- `Avatar` - User/bot avatars in bubbles
- `Badge` - Action count, status
- `Text`, `Title` - Message content, headers
- `Container` - Layout wrapper
- `Loader`, `Skeleton` - Loading/streaming states
- `Toaster` - Success/error notifications
- `Tooltip` - Button tooltips
- Icons from `lucide-react`: Send, Square, Bot, User, Undo2, Settings, ChevronDown, etc.

**Verification:** Open any post in Gutenberg -> WP Agent icon in toolbar -> sidebar opens -> type message -> streaming response appears.

---

### Commit 10: `feat: admin settings page React app`

**Scope**: Settings page at WP Admin > WP Agent.

**New/modified files:**
```
src/admin/settings/
  index.js                    # ReactDOM.createRoot mount
  App.jsx                     # Settings root with Tabs
  APIKeyForm.jsx              # OpenRouter API key config + validation
  ModelSelector.jsx           # Default model picker (fast/balanced/powerful)
  RolePermissions.jsx         # Role-based access control toggles
```

**Note:** Usage dashboard deferred to Phase 2. No usage display in Phase 1 (silent rate limiting).

**Settings stored in `wp_options`:**
- `wp_agent_api_key` - Encrypted OpenRouter API key
- `wp_agent_default_model` - Default model tier (fast/balanced/powerful)
- `wp_agent_rate_limit` - Requests per minute per user
- `wp_agent_daily_limit` - Requests per day per user
- `wp_agent_allowed_roles` - Array of WP roles that can use the agent

**API key flow:**
1. User enters key in settings form
2. POST to `/wp-agent/v1/settings` with key
3. PHP encrypts with `openssl_encrypt()` using `wp_salt('auth')` as key
4. Stored encrypted in `wp_options`
5. Client JS only receives `hasApiKey: true/false` (never the actual key)
6. Validation: test call to OpenRouter `/models` endpoint

**Verification:** Navigate to WP Admin > WP Agent > enter API key > see validation success > select model > save.

---

### Commit 11: `feat: CI workflow for code analysis`

**Scope**: Update GitHub Actions for WP Agent specifics.

**Files modified:**
- `.github/workflows/code-analysis.yml`

**Updates:**
- Node version: 14.17 -> 18.x (required for @wordpress/scripts ^27)
- PHP version: 7.4 -> 8.0 (test matrix: 7.4, 8.0, 8.1, 8.2)
- Add `npm run build` step (verify build succeeds)
- Update PHPCS/PHPStan paths for new file structure
- Add `composer validate` step

**Verification:** Push to branch -> GitHub Actions runs all checks -> green.

---

### Commit 12: `docs: README and inline documentation`

**Scope**: User-facing README + developer documentation.

**Files modified:**
- `README.md` - Full rewrite

**README sections:**
- Description & features
- Requirements (WP 6.4+, PHP 7.4+)
- Installation (download zip, or clone + npm install + npm run build)
- Configuration (API key setup, model selection)
- Usage guide (open sidebar, type message, confirm actions)
- Available actions (create_post, update_settings, site_health)
- Extending (custom actions via `wp_agent_register_actions` hook)
- Development setup
- Architecture overview (brief)
- FAQ
- License (GPL v2)

**Verification:** README renders correctly on GitHub. All links work.

---

## Architecture Diagram

```
WordPress Admin / Gutenberg Editor
  |
  PluginSidebar (React Chat UI - Force UI components)
  |
  fetch() POST with ReadableStream (SSE parsing)
  |
  REST API: POST /wp-agent/v1/stream
  |
  Stream_Controller (PHP)
    -> set SSE headers, disable buffering, set_time_limit(0)
    -> Context_Collector (site info, post blocks, plugins, theme)
    -> Prompt_Builder (system prompt + context + history + tools)
    -> Model_Router (select model by complexity)
    -> Open_Router_Client (cURL WRITEFUNCTION streaming)
    -> Forward each chunk as SSE event to browser
    -> Parse action plans from response
    -> Store messages in DB
    -> Reset set_time_limit(30) per chunk (30s timeout workaround)
```

---

## Key Technical Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| SSE transport | `fetch()` + ReadableStream POST | EventSource only supports GET; POST is more secure (no data in URL) |
| OpenRouter streaming | cURL `CURLOPT_WRITEFUNCTION` | `wp_remote_post()` doesn't support streaming callbacks |
| PHP timeout | `set_time_limit(0)` + reset per chunk | Avoids 30s PHP timeout on long AI responses |
| PluginSidebar import | `@wordpress/editor` | `@wordpress/edit-post` deprecated for this since WP 6.6 |
| Tailwind preflight | `false` | Prevents resetting WP admin styles |
| DB approach | Custom tables | Chat data is high-volume relational; post meta would pollute |
| ENUM vs VARCHAR | VARCHAR(20) for role column | `dbDelta()` has known ENUM parsing bugs |
| API key storage | `openssl_encrypt` with `wp_salt()` | Never expose raw key to client JS |
| WPCS version | ^3.0 | Current standard; boilerplate had ^2.2 (outdated) |
| @wordpress/scripts | ^27.0.0 | Needed for WP 6.4+ compatibility and React 18 |

---

## Security Checklist

- [ ] Every REST endpoint has `permission_callback` checking `current_user_can()`
- [ ] Nonce verification via `X-WP-Nonce` header (automatic with WP REST)
- [ ] All input sanitized: `sanitize_text_field()`, `sanitize_textarea_field()`, `absint()`
- [ ] All output escaped: `esc_html()`, `esc_attr()`, `esc_url()`, `wp_json_encode()`
- [ ] Per-action capability checks (`Action_Interface::get_required_capability()`)
- [ ] API key never exposed to client JS (only boolean `hasApiKey`)
- [ ] All DB queries use `$wpdb->prepare()` with parameterized queries
- [ ] Rate limiting per-user via transients
- [ ] `defined('ABSPATH') || exit;` in every PHP file
- [ ] Content sanitization before prompt inclusion (prevent prompt injection)
- [ ] Encrypted API key storage (`openssl_encrypt` + `wp_salt()`)

---

## Package Dependencies (Final)

### composer.json (require-dev)
```json
{
    "squizlabs/php_codesniffer": "^3.5",
    "wp-coding-standards/wpcs": "^3.0",
    "phpstan/phpstan": "^1.9",
    "szepeviktor/phpstan-wordpress": "^1.1",
    "php-stubs/wordpress-stubs": "^6.1"
}
```

### package.json
```json
{
    "dependencies": {
        "@bsf/force-ui": "git+https://github.com/brainstormforce/force-ui.git#1.7.9",
        "lucide-react": "^0.417.0",
        "clsx": "^2.1.1",
        "tailwind-merge": "^2.4.0"
    },
    "devDependencies": {
        "@wordpress/scripts": "^27.0.0",
        "tailwindcss": "^3.4.10",
        "autoprefixer": "^10.4.19",
        "grunt": "^1.4.1",
        "grunt-cli": "^1.4.3",
        "grunt-contrib-clean": "^2.0.0",
        "grunt-contrib-compress": "^2.0.0",
        "grunt-contrib-copy": "^1.0.0"
    }
}
```

### WordPress Externals (NOT bundled)
`@wordpress/blocks`, `@wordpress/data`, `@wordpress/element`, `@wordpress/editor`, `@wordpress/plugins`, `@wordpress/api-fetch`, `@wordpress/components`

---

## Verification Plan

### Per-commit checks:
1. Plugin activates without errors in WP admin
2. `npm run build` succeeds (commits 8+)
3. No PHP fatal errors in error log

### After Commit 2 (DB):
```sql
SHOW TABLES LIKE '%agent%';  -- Should return 4 tables
```

### After Commit 6 (REST):
```bash
# Should return 401 (no auth)
curl -s /wp-json/wp-agent/v1/settings | jq .code

# Should return 200 with valid cookie/nonce
curl -s /wp-json/wp-agent/v1/settings \
  -H "X-WP-Nonce: <nonce>" \
  -H "Cookie: <auth_cookie>"
```

### After Commit 9 (Chat UI):
- Open Gutenberg editor on any post/page
- Click WP Agent icon in toolbar -> sidebar opens
- Type message -> see streaming response
- AI proposes actions -> plan viewer appears
- Confirm action -> checkpoint created
- Undo action -> state restored

### After Commit 10 (Settings):
- Navigate to WP Admin > WP Agent
- Enter OpenRouter API key -> validation passes
- Select default model -> saves
- Configure role permissions -> saves

### Full E2E:
1. Install & activate plugin
2. Open any post in Gutenberg editor
3. Open WP Agent sidebar
4. See inline setup wizard (no API key yet)
5. Enter OpenRouter API key + select model in wizard
6. Type "Add a heading that says Hello World and a paragraph below it"
7. See streaming response with action plan (cherry-pickable checkboxes)
8. Select desired actions, click Execute
9. See blocks inserted smoothly into the editor (client-side, no reload)
10. Click Undo (30s banner) -> blocks removed, post moved to trash if applicable

---

## Phase 2 Roadmap (Deferred Features)

These features were discussed and scoped but deferred to keep Phase 1 focused and bulletproof.

### Phase 2a: Command Bar Modal
- Centered modal dialog triggered by `Cmd/Ctrl+K`
- Works in Gutenberg editor only (expand to all admin pages in Phase 3)
- Full chat UI (same components as sidebar, different container)
- Shared conversation state with sidebar via `@wordpress/data` store

### Phase 2b: Additional Actions (8+)
- `modify_blocks` - Edit existing block attributes client-side
- `upload_media` - Upload files to media library
- `list_users` - List/filter users with role info
- `switch_theme` - Change active theme
- `manage_menus` - Create/edit navigation menus
- `manage_widgets` - Add/remove widgets from widget areas
- `update_plugin` - Update plugins to latest version
- `delete_plugin` - Remove plugins from filesystem
- `manage_categories` - Create/edit/delete taxonomies
- `export_content` - Export posts/pages to various formats

### Phase 2c: Enhanced Editor Context
- Full editor awareness: cursor position, highlighted text, selection range
- "Rewrite this paragraph" without specifying which one
- Context sent with every API call (debounced on selection change)

### Phase 2d: Usage & Analytics
- Usage dashboard in settings page (tokens, costs, per-model breakdown)
- Daily/weekly/monthly usage charts
- Per-user usage tracking for admins
- Export usage reports

### Phase 2e: Advanced Features
- BSF shared API key via Cloudflare Worker proxy
- Configurable per-action auto-execute (skip confirmation for trusted actions)
- Filterable system prompt via `wp_agent_system_prompt` PHP hook
- Configurable data retention period (7/30/90 days/forever)
- Basic multisite support (per-site independent)
- AI self-correction ("I notice that heading should have been H2...")
- Mobile-responsive modal (full-screen on mobile)
