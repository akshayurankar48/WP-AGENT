# Architecture Overview

JARVIS AI is a WordPress plugin (PHP + React) that connects an AI orchestrator to 77 WordPress actions via a streaming REST API.

## Directory Structure

```
jarvis-ai/
  jarvis-ai.php              # Entry point, constants, activation hooks
  plugin-loader.php           # PSR-4 autoloader, hook registration
  actions/                    # 77 AI-callable actions (Action_Interface)
  ai/                         # Orchestrator, prompt builder, model router, clients
  core/                       # Database, checkpoint manager
  rest/                       # 10 REST controllers, permissions helper
  admin/                      # Admin pages, menu registration, assets manager
  patterns/                   # Pattern manager + JSON library
    library/                  # 24 category dirs + blueprints/
  integrations/               # WP Abilities Bridge, MCP adapter
  lib/                        # Bundled SDK, provider clients
  src/                        # React frontend (3 entry points)
  assets/                     # CSS animations, JS utilities
```

## Layers

### Actions (77 files)

Each action implements `Action_Interface` with `get_name()`, `get_description()`, `get_parameters()`, `get_capability()`, and `execute()`. Registered in `Action_Registry` at boot. Categories: Content, Plugins, Themes, Users, Media, SEO, Site Management, AI/Research, Patterns, WooCommerce, System.

### AI Layer (7 files)

| File | Purpose |
|------|---------|
| `orchestrator.php` | Message lifecycle, tool loop (25 max), retry logic |
| `prompt-builder.php` | System prompt assembly (context, patterns, abilities) |
| `model-router.php` | Tier-based model selection (FAST/BALANCED/POWERFUL) |
| `open-router-client.php` | OpenRouter API client with SSE streaming |
| `ai-client-adapter.php` | Direct provider adapter (Anthropic, OpenAI, Google) |
| `context-collector.php` | Gathers site context for the system prompt |
| `rate-limiter.php` | Per-user rate limiting |

### REST Layer (10 files)

8 controllers + 1 permissions helper + 1 stream controller. See [REST API Reference](REST-API-Reference) for all 17 endpoints.

### Frontend (3 entry points)

| Entry | File | Purpose |
|-------|------|---------|
| Editor sidebar | `src/editor.js` | Gutenberg PluginSidebar chat panel |
| Admin dashboard | `src/index.js` | Admin pages (Dashboard, Settings, History, etc.) |
| Drawer | `src/drawer.js` | Floating chat drawer for non-editor pages |

### Patterns (93 + 17)

JSON-based block pattern library across 24 categories. 17 full-page blueprints. Injected into the system prompt for design-aware generation.

### Integrations

- **WP Abilities Bridge** -- Maps WordPress capabilities to action permissions
- **MCP Adapter** -- Model Context Protocol server for external tool integration

### Core (2 files)

- **Database** -- 6 custom tables, dbDelta migrations, version tracking
- **Checkpoint Manager** -- Pre/post action snapshots for undo/rollback

## Request Flow

```
User types message in sidebar
        |
        v
POST /jarvis-ai/v1/stream  (SSE connection opened)
        |
        v
Stream_Controller -> Orchestrator
        |
        v
Prompt Builder assembles system prompt
  (site context + patterns + action definitions)
        |
        v
Model Router selects provider + model
        |
        v
AI Client sends request (OpenRouter or direct provider)
        |
        v
Orchestrator enters tool loop (max 25 iterations):
  1. AI returns tool_calls -> dispatch to Action_Registry
  2. Action executes (with capability check + checkpoint)
  3. Result appended to conversation
  4. Loop back to AI with results
        |
        v
SSE events streamed to client:
  content -> progress -> action -> done
        |
        v
Redux store updates UI in real time
```

## See Also

- [Database Schema](Database-Schema)
- [AI Orchestrator](AI-Orchestrator)
- [Frontend Architecture](Frontend-Architecture)
- [Security Model](Security-Model)
