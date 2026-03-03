# Changelog

## v1.0.0 (2026-03-03)

Initial release.

### Features

- **AI Chat Sidebar** -- Natural language chat interface in the Gutenberg block editor
- **77 AI Actions** -- Content, plugins, themes, users, media, SEO, site management, WooCommerce, and more
- **Plan-Confirm-Execute Workflow** -- Agent plans multi-step tasks, confirms destructive actions, then executes autonomously
- **Multi-Provider AI** -- OpenRouter (100+ models), Anthropic, OpenAI, Google via BYOK
- **Model Router** -- Automatic model selection across 3 tiers (Fast/Balanced/Powerful) with complexity scoring
- **Streaming SSE** -- Real-time token-by-token response streaming
- **Pattern Library** -- 93 block patterns + 17 full-page blueprints across 24 categories
- **Conversation Persistence** -- Full chat history with 6 custom database tables
- **Undo/Rollback** -- Checkpoint system for reversing AI actions
- **Admin Dashboard** -- Dashboard, Settings, History, Schedules, Capabilities, Help, Usage pages
- **Floating Drawer** -- Chat access on non-editor admin pages
- **API Key Encryption** -- AES-256-CBC encryption for all stored API keys
- **Role-Based Access** -- Configurable allowed roles with per-action capability checks
- **Rate Limiting** -- Per-user per-minute and daily request limits
- **Scheduled Tasks** -- Recurring action chains with pause/resume/delete
- **Agent Memory** -- Cross-conversation context persistence
- **A/B Testing** -- Built-in A/B test creation and tracking
- **AI Pulse** -- Aggregated AI news feed from major providers
- **Voice Input** -- Browser Speech Recognition for voice commands
- **Design Score** -- AI-generated design quality assessment
- **WooCommerce Integration** -- Products, orders, coupons, inventory, shipping, analytics
- **Web Search** -- Tavily integration for real-time web search
- **Image Generation** -- DALL-E integration for AI-generated images
- **Reference Site Analysis** -- Analyze external websites for design inspiration

### Requirements

- WordPress 6.4+
- PHP 7.4+
- An AI provider API key (OpenRouter, Anthropic, OpenAI, or Google)
