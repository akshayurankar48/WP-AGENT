# WP Agent — Product Requirements Document

**AI-Powered Autonomous Admin Assistant for WordPress**

| Field | Value |
|-------|-------|
| **Author** | Brainstorm Force |
| **Version** | 1.0 (Draft) |
| **Date** | 2026-02-28 |
| **Status** | Pre-Development |

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Problem Statement](#2-problem-statement)
3. [Target Users](#3-target-users)
4. [Product Vision](#4-product-vision)
5. [Competitive Landscape](#5-competitive-landscape)
6. [Core Architecture](#6-core-architecture)
7. [Feature Specification](#7-feature-specification)
8. [AI Provider Strategy](#8-ai-provider-strategy)
9. [Security & Privacy](#9-security--privacy)
10. [Revenue Model](#10-revenue-model)
11. [Development Roadmap](#11-development-roadmap)
12. [Tech Stack](#12-tech-stack)
13. [Success Metrics](#13-success-metrics)
14. [Risk Analysis](#14-risk-analysis)
15. [BSF Ecosystem Integration](#15-bsf-ecosystem-integration)

---

## 1. Executive Summary

**WP Agent** is an AI-powered autonomous admin assistant for WordPress. Users interact with their WordPress site through natural language — "Create a landing page for our Black Friday sale", "Update all plugins and check for conflicts", "Show me which pages have the highest bounce rate and suggest improvements." The AI understands the full site context (content, settings, plugins, theme, users, WooCommerce data) and can execute multi-step admin workflows autonomously.

**Thesis**: Every WordPress site owner becomes a power user. WP Agent collapses the expertise gap between a beginner and a seasoned WordPress developer. Instead of navigating 47 admin screens to accomplish a task, users describe what they want in plain English and the agent handles the rest.

**Company**: Brainstorm Force (BSF) — makers of Astra (5M+ active installs), Starter Templates, SureCart. 5M+ business reach, proven WordPress distribution.

**AI Backend**: Shared OpenRouter API key. Users never see API keys or provider complexity. Multi-model routing (GPT-4o, Claude Sonnet, Gemini Flash) abstracted behind a single intelligent layer.

**Revenue Model**: Lifetime ($199) + Annual ($99/yr) — BSF's proven pricing model.

**Timeline**: 6-8 months to V1.0 GA.

---

## 2. Problem Statement

WordPress powers 43% of the web, but managing a WordPress site is overwhelmingly complex:

- **Settings sprawl**: A typical WP site has 15-30 active plugins, each with its own settings page. Simple tasks require navigating multiple screens.
- **Knowledge gap**: Site owners know *what* they want but not *how* to do it in WordPress. "I want a contact form that emails my team" requires choosing a form plugin, installing it, creating the form, configuring SMTP, setting up notifications.
- **Repetitive admin tasks**: Bulk content updates, plugin maintenance, user management, SEO optimization — these consume hours of non-creative time weekly.
- **Agency bottleneck**: Non-technical clients constantly ask their agencies for trivial changes ("update the phone number", "add a new team member", "change the banner image"), creating support overhead.
- **No AI-native admin experience**: Existing AI WordPress plugins focus on content generation (writing blog posts). None provide a general-purpose admin assistant that can perform arbitrary WordPress operations.

**Market size**: WordPress AI plugin market estimated at $500M (2025), projected $2.5B by 2033 (CAGR ~22%). General-purpose AI admin assistants represent a new category within this market.

---

## 3. Target Users

### Primary: Small Business Site Owners (TAM: ~20M)

- Run their own WordPress site (business, portfolio, blog)
- Know what they want but struggle with WordPress admin complexity
- Currently pay freelancers $50-150/hr for simple changes
- **Job to be done**: "Manage my website without learning WordPress"

### Secondary: Freelancers & Small Agencies (TAM: ~2M)

- Manage 10-50 client sites
- Spend 30-40% of time on routine maintenance and client requests
- **Job to be done**: "Handle client requests in seconds instead of minutes"

### Tertiary: Content Teams (TAM: ~5M)

- Marketing teams managing content on WordPress
- Need to create, optimize, and maintain content at scale
- **Job to be done**: "Publish and manage content without technical help"

---

## 4. Product Vision

### The Command Bar

WP Agent lives as an always-available floating command bar in the WordPress admin (triggered by `Cmd/Ctrl + K` or a floating button). Users type or speak what they want:

```
User: "Create a new page called 'About Us' with a hero section, team grid,
       and a contact form at the bottom"

WP Agent: I'll create that for you. Here's my plan:
  1. Create new page "About Us"
  2. Add hero section with placeholder headline and description
  3. Add team grid block (I see you have 4 team members — I'll include them)
  4. Add a contact form using WPForms (already installed)
  5. Publish as draft for your review

  [Execute] [Modify Plan] [Cancel]
```

### Key Principles

1. **Plan, Confirm, Execute**: The agent always shows its plan before acting. Users approve, modify, or cancel. No blind execution.
2. **Context-Aware**: The agent understands your site — installed plugins, theme, content, users, settings, WooCommerce products. It doesn't suggest installing a form plugin if you already have one.
3. **Reversible**: Every action creates a checkpoint. One-click undo of any agent operation.
4. **Progressive Disclosure**: Simple answers for simple questions. Detailed plans for complex operations. The agent matches its response complexity to the task.
5. **Works With Everything**: Compatible with any theme, any plugin, any page builder. Uses WordPress core APIs, not plugin-specific hacks.

---

## 5. Competitive Landscape

| Product | What It Does | Limitation | WP Agent Advantage |
|---------|-------------|------------|-------------------|
| **Jetpack AI** | Content writing in editor | Editor-only, content-only, $10/mo | Full admin control, not just content |
| **Jeeves.ai** | AI admin chat ($120/yr) | Early stage, limited action set | Deeper WP integration, BSF distribution |
| **CodeWP** | Code generation for devs | Developer-only, BYOK | Non-technical users, no code shown |
| **Bertha AI** | Content + image generation | Content-only, $19/mo SaaS | Manages entire site, not just content |
| **AI Engine** | AI toolkit (chatbots, content) | Toolkit/framework, requires config | Zero-config, just talk to it |
| **ChatGPT/Claude** | General AI assistants | No WordPress context or actions | Native WP integration, can take action |

**Moat**: WP Agent's differentiation is **action, not just advice**. Other AI tools tell you what to do. WP Agent does it — safely, reversibly, in context.

---

## 6. Core Architecture

### System Overview

```
+-------------------------------------------------+
|                  WP Admin UI                     |
|  +--------------------------------------+       |
|  |         Command Bar (React)          |       |
|  |  [Cmd+K] Natural language input      |       |
|  |  Plan display + confirmation UI      |       |
|  |  Streaming response display          |       |
|  |  Action history + undo               |       |
|  +----------------+---------------------+       |
|                   | REST API                     |
|  +----------------v---------------------+       |
|  |         Agent Orchestrator (PHP)      |       |
|  |                                       |       |
|  |  +---------+  +------------------+   |       |
|  |  | Context  |  | Action Registry  |   |       |
|  |  | Collector|  | (30+ actions)    |   |       |
|  |  +----+-----+  +--------+--------+   |       |
|  |       |                 |             |       |
|  |  +----v-----------------v---------+   |       |
|  |  |     AI Router (OpenRouter)     |   |       |
|  |  |  Model selection + fallback    |   |       |
|  |  |  System prompt + context       |   |       |
|  |  |  Tool/function calling         |   |       |
|  |  +--------------------------------+   |       |
|  |                                       |       |
|  |  +--------------------------------+   |       |
|  |  |     Checkpoint System          |   |       |
|  |  |  Pre-action snapshots          |   |       |
|  |  |  One-click undo/rollback       |   |       |
|  |  +--------------------------------+   |       |
|  +---------------------------------------+       |
+-------------------------------------------------+
```

### Key Components

#### 1. Command Bar (Frontend — React)

- Floating overlay triggered by `Cmd/Ctrl+K` or floating button
- Text input with voice input option (Web Speech API)
- Streaming response display (SSE)
- Plan viewer with approve/modify/cancel
- Action history panel with undo
- Renders in any WP admin page (not just the editor)

#### 2. Agent Orchestrator (Backend — PHP)

- Receives natural language input from command bar
- Collects site context (see Context Collector)
- Builds system prompt with available actions and context
- Sends to AI via OpenRouter with function calling
- Parses AI response into executable action plan
- Executes approved actions via Action Registry
- Records checkpoints for undo

#### 3. Context Collector

Gathers relevant site state on each request:

- Active plugins + versions
- Current theme + active customizer settings
- Content summary (post types, counts, recent)
- User roles and current user capabilities
- WooCommerce state (if active): products, orders, settings
- Site settings: permalink structure, timezone, language
- Available page builders (Elementor, Beaver Builder, Spectra)
- Installed and available blocks

Context is compressed to stay within token limits. Cached per-session (transient, 5min TTL) to avoid repeated DB queries.

#### 4. Action Registry

- Each "action" is a PHP class implementing `Agent_Action` interface
- Actions are registered via `wp_agent_register_action` hook (extensible)
- Each action declares: `name`, `description`, `parameters`, `capabilities_required`, `reversible`
- The AI sees action descriptions and calls them via function calling
- Actions validate parameters, execute, and return results

#### 5. Checkpoint System

- Before any state-changing action, a checkpoint is created
- Checkpoint stores: action name, parameters, pre-state snapshot, timestamp
- Stored in custom DB table `wp_agent_checkpoints`
- Users can undo any action from the history panel
- Checkpoints auto-expire after 30 days

---

## 7. Feature Specification

### 7.1 Command Bar Interface

**Trigger**: `Cmd/Ctrl+K` globally in WP admin, or floating "Agent" button (bottom-right)

**States**:

| State | Description |
|-------|-------------|
| **Idle** | Input field with placeholder "Ask WP Agent anything..." |
| **Thinking** | Animated indicator, streaming text response |
| **Plan** | Numbered action plan with [Execute All] [Modify] [Cancel] |
| **Executing** | Progress bar per action step, live status updates |
| **Complete** | Summary of what was done + [Undo] button |
| **Error** | Clear error message + suggested next steps |

**Keyboard shortcuts**:

| Shortcut | Action |
|----------|--------|
| `Cmd/Ctrl+K` | Open/focus command bar |
| `Escape` | Close command bar |
| `Enter` | Send message |
| `Shift+Enter` | New line |
| `Cmd/Ctrl+Z` | Undo last agent action (when command bar is open) |
| `Up/Down` | Navigate suggestion history |

**Contextual awareness**: The command bar knows which admin page you're on. If you're on the Posts list, "delete the drafts" refers to draft posts. If you're on WooCommerce Orders, "refund the last order" works.

### 7.2 Action Categories (30+ actions in V1)

#### Content Management (8 actions)

| Action | Description | Example Prompt |
|--------|-------------|---------------|
| `create_post` | Create post/page with blocks | "Create a blog post about our new product launch" |
| `edit_post` | Modify existing content | "Update the pricing on our Services page" |
| `bulk_edit_posts` | Batch operations on posts | "Set all draft posts from 2024 to trash" |
| `manage_media` | Upload, edit, organize media | "Resize all images in the Media Library to max 1200px wide" |
| `create_menu` | Create/modify navigation menus | "Add a 'Blog' link to the main navigation" |
| `manage_categories` | Create/edit taxonomies | "Create categories for Product Updates, Tutorials, and News" |
| `generate_content` | AI content generation | "Write a 500-word blog post about remote work tips" |
| `translate_content` | Translate posts/pages | "Translate the About page to Spanish" |

#### Site Settings (6 actions)

| Action | Description | Example Prompt |
|--------|-------------|---------------|
| `update_settings` | Modify WordPress settings | "Change the site tagline to 'Building Better Businesses'" |
| `manage_permalinks` | Update permalink structure | "Switch to post-name permalinks" |
| `configure_reading` | Homepage, posts per page | "Set the homepage to our new Landing Page" |
| `manage_widgets` | Add/remove/reorder widgets | "Add a recent posts widget to the sidebar" |
| `customize_theme` | Theme customizer settings | "Change the header background color to navy blue" |
| `manage_smtp` | Email configuration | "Set up SMTP with our Gmail account" |

#### Plugin Management (4 actions)

| Action | Description | Example Prompt |
|--------|-------------|---------------|
| `install_plugin` | Search + install + activate | "Install and activate a contact form plugin" |
| `update_plugins` | Update one or all plugins | "Update all plugins to their latest versions" |
| `deactivate_plugin` | Deactivate without deleting | "Deactivate Jetpack" |
| `plugin_settings` | Configure a plugin's settings | "Configure Yoast SEO for our site" |

#### User Management (4 actions)

| Action | Description | Example Prompt |
|--------|-------------|---------------|
| `create_user` | Create new user account | "Create an editor account for john@example.com" |
| `manage_roles` | Change user roles | "Change all subscribers to contributors" |
| `bulk_user_actions` | Batch user operations | "Delete all users who haven't logged in for a year" |
| `user_notifications` | Send emails to users | "Email all editors about the new style guide" |

#### WooCommerce (6 actions, if WooCommerce active)

| Action | Description | Example Prompt |
|--------|-------------|---------------|
| `create_product` | Create products with details | "Create a simple product: Blue T-Shirt, $29.99, 50 in stock" |
| `manage_orders` | View, update, refund orders | "Show me all pending orders from this week" |
| `create_coupon` | Generate discount codes | "Create a 20% off coupon for returning customers, expires Dec 31" |
| `update_shipping` | Shipping zones and rates | "Add free shipping for orders over $75" |
| `sales_report` | Revenue and order analytics | "Show me sales revenue by month for the last quarter" |
| `manage_inventory` | Stock management | "Which products are low on stock?" |

#### SEO & Performance (4 actions)

| Action | Description | Example Prompt |
|--------|-------------|---------------|
| `seo_audit` | Analyze page/site SEO | "Audit the SEO of our homepage" |
| `generate_meta` | Create meta titles/descriptions | "Generate meta descriptions for all posts missing them" |
| `analyze_performance` | Site speed analysis | "What's slowing down my site?" |
| `manage_redirects` | Create/manage 301 redirects | "Redirect /old-page to /new-page" |

#### Maintenance (3 actions)

| Action | Description | Example Prompt |
|--------|-------------|---------------|
| `site_health` | WordPress Site Health checks | "What does Site Health say about my site?" |
| `database_cleanup` | Optimize database | "Clean up post revisions and spam comments" |
| `backup_check` | Verify backup status | "When was my last backup?" |

### 7.3 Conversation Memory

- **Session memory**: Within a session, the agent remembers context. "Create a page" followed by "Now add a hero section to it" — "it" refers to the just-created page.
- **Action history**: Last 100 actions stored per user. Users can reference: "Undo that product I created yesterday."
- **Site knowledge**: The agent builds a knowledge graph of the site over time — popular pages, common tasks, user preferences. Stored in `wp_agent_knowledge` table.
- **No cross-site data**: All data stays local to the WordPress installation. No cloud sync, no telemetry of conversation content.

### 7.4 Smart Suggestions

When the command bar opens, WP Agent shows contextual suggestions based on:

- Current admin page context
- Recent actions ("Continue where you left off")
- Site health issues ("3 plugins need updates")
- Content opportunities ("5 posts have no meta descriptions")

### 7.5 Multi-Step Workflows

Complex requests are decomposed into numbered steps:

```
User: "Set up a WooCommerce store selling digital ebooks"

WP Agent Plan:
  1. Verify WooCommerce is installed and active (already active)
  2. Configure WooCommerce for digital products (disable shipping)
  3. Create product category "Ebooks"
  4. Create sample product "Getting Started Guide" ($9.99, downloadable)
  5. Configure checkout for digital goods (disable address fields)
  6. Create a Shop page with product grid

  Estimated: 6 actions | Reversible: Yes
  [Execute All] [Execute Step-by-Step] [Modify] [Cancel]
```

**Step-by-step mode**: Pauses after each step for user confirmation. Useful for learning or sensitive operations.

---

## 8. AI Provider Strategy

### OpenRouter as Unified Backend

All AI requests route through OpenRouter (`https://openrouter.ai/api/v1`), which provides:

- Access to multiple model providers through a single API
- Automatic failover between providers
- Usage tracking and cost management
- OpenAI-compatible API format (drop-in replacement)

### Model Selection Strategy

| Model | Use Case | Cost (per 1M tokens) |
|-------|----------|---------------------|
| `gpt-4o-mini` | Simple queries, quick responses, suggestions | ~$0.15 input / $0.60 output |
| `gemini-2.0-flash` | Bulk operations, content generation, analysis | ~$0.10 input / $0.40 output |
| `claude-haiku` | Fast responses, simple planning | ~$1.00 input / $5.00 output |
| `gpt-4o` | Complex multi-step planning, code generation | ~$2.50 input / $10.00 output |
| `claude-sonnet` | Deep reasoning, complex workflows | ~$3.00 input / $15.00 output |

**Intelligent routing**: The orchestrator selects the model based on task complexity:

- **Tier 1 (Fast/Cheap)**: Simple lookups, settings changes, quick answers -> `gpt-4o-mini` or `gemini-2.0-flash`
- **Tier 2 (Balanced)**: Content generation, moderate planning -> `gpt-4o`
- **Tier 3 (Powerful)**: Multi-step workflows, complex reasoning -> `claude-sonnet`

Users never see model names. The plugin just "works." Advanced users can override in settings.

### Function Calling

WP Agent uses OpenAI-compatible function calling (supported by all models via OpenRouter). Each registered action is exposed as a tool/function the AI can call:

```json
{
  "tools": [
    {
      "type": "function",
      "function": {
        "name": "create_post",
        "description": "Create a new WordPress post or page",
        "parameters": {
          "type": "object",
          "properties": {
            "title": { "type": "string" },
            "content": { "type": "string" },
            "post_type": { "type": "string", "enum": ["post", "page"] },
            "status": { "type": "string", "enum": ["draft", "publish"] },
            "categories": { "type": "array", "items": { "type": "string" } }
          },
          "required": ["title", "content"]
        }
      }
    }
  ]
}
```

### Shared API Key Architecture

- BSF holds a single OpenRouter API key, bundled (encrypted) in the plugin
- All user requests route through a lightweight proxy endpoint on BSF infrastructure for:
  - Rate limiting per license key
  - Usage tracking per site
  - Key rotation without plugin updates
  - Cost monitoring and alerting
- **Proxy is stateless** — it only adds the API key header and forwards. No conversation data is stored server-side.
- If BSF proxy is unreachable, plugin falls back to a direct OpenRouter call with a secondary embedded key

### Token Budget Management

- **Free tier**: 10,000 tokens/day (roughly 20-30 simple commands)
- **Pro tier**: 500,000 tokens/day (hundreds of commands, including content generation)
- **Usage dashboard**: Real-time token usage display in WP Admin > WP Agent > Usage
- **Overage handling**: Graceful degradation — downgrade to cheaper model, then show "daily limit reached" with upgrade CTA

---

## 9. Security & Privacy

### Threat Model

| # | Threat |
|---|--------|
| 1 | **Prompt injection** — user or third-party content manipulating the agent |
| 2 | **Privilege escalation** — agent performing actions beyond user capabilities |
| 3 | **Data exfiltration** — sensitive site data leaking through AI requests |
| 4 | **API key exposure** — shared OpenRouter key being extracted |
| 5 | **Destructive actions** — agent accidentally deleting critical content |

### Mitigations

| Threat | Mitigation |
|--------|-----------|
| Prompt injection | All user-generated content in context is wrapped in clear delimiters. System prompt explicitly instructs the model to ignore instructions in user content. Content sanitization before inclusion in prompts. |
| Privilege escalation | Every action checks `current_user_can()` before execution. An Editor cannot use the agent to perform Administrator-level actions. Action registry declares required capabilities. |
| Data exfiltration | No site content is sent to BSF servers. Proxy only forwards to OpenRouter. Sensitive fields (passwords, API keys, wp-config values) are explicitly excluded from context collection. |
| API key exposure | Key is encrypted at rest (AES-256-CBC). Proxy architecture means the raw OpenRouter key never reaches the client. Plugin-bundled key is a per-version rotating derivative. |
| Destructive actions | Plan-Confirm-Execute pattern. Checkpoint system for all state-changing actions. High-risk actions (delete, bulk operations) require explicit typed confirmation ("type DELETE to confirm"). |

### Data Flow

```
User Input -> [WordPress REST API] -> Agent Orchestrator
  -> Context Collector (local DB queries only)
  -> Build prompt (site context + user query + available tools)
  -> [HTTPS] -> BSF Proxy (adds API key, rate-limits, forwards)
  -> [HTTPS] -> OpenRouter -> AI Model
  -> Response -> Parse action plan
  -> [Show plan to user] -> User approves
  -> Execute actions (all local WordPress API calls)
  -> Record checkpoint -> Return result
```

**Zero cloud storage of conversations.** All conversation history lives in the local WordPress database.

### Permissions Model

| WordPress Role | WP Agent Access |
|---------------|----------------|
| Super Admin | Full access — all actions |
| Administrator | Full access — all actions |
| Editor | Content actions only (create/edit posts, media, categories) |
| Author | Own content only (create/edit own posts) |
| Contributor | Draft content only |
| Subscriber | Read-only queries ("How many posts do we have?") |

Admins can customize per-role access in Settings.

---

## 10. Revenue Model

### Pricing (BSF Lifetime + Annual Model)

| Plan | Annual | Lifetime | Sites | Features |
|------|--------|----------|-------|----------|
| **Free** | $0 | $0 | 1 | 20 commands/day, basic actions (content, settings), gpt-4o-mini only |
| **Pro** | $99/yr | $199 | Unlimited | 500 commands/day, all actions, all models, WooCommerce, priority support |
| **Agency** | $199/yr | $399 | Unlimited | Everything in Pro + white-label, client sites, bulk license management |

### Revenue Projections (Conservative)

- **Year 1**: 5,000 Pro licenses (BSF existing customer base conversion) = $500K-$1M
- **Year 2**: 20,000 Pro licenses (organic + BSF cross-sell) = $2M-$4M
- **Year 3**: 50,000+ licenses (market expansion + Agency tier) = $5M-$10M

### Cost Structure

- **AI costs**: ~$0.01-0.05 per command (blended model costs). At 500 commands/day/user, worst case ~$25/user/month. Tier 1 routing keeps average well below this.
- **Proxy hosting**: Minimal — stateless forwarder, <$500/mo for initial scale
- **Key insight**: Model costs are dropping ~50% annually. Today's cost structure will halve by Year 2.

---

## 11. Development Roadmap

### Phase 1: Foundation (Months 1-2)

**Goal**: Plugin skeleton, command bar UI, AI communication pipeline.

- WordPress plugin boilerplate (custom post type for agent history, DB tables for checkpoints/knowledge)
- React command bar component (floating overlay, keyboard shortcut, input, streaming display)
- Agent Orchestrator class (receives input, builds prompt, calls OpenRouter, returns response)
- Context Collector (basic site info: plugins, theme, post counts, current page)
- BSF Proxy MVP (API key injection, per-license rate limiting)
- 3 starter actions: `create_post`, `update_settings`, `site_health`
- Checkpoint system (pre/post snapshots, undo)
- Settings page (license activation, usage dashboard, role permissions)

**Deliverable**: Users can open command bar, ask questions about their site, and create posts via natural language.

### Phase 2: Core Actions (Months 3-4)

**Goal**: Comprehensive action library covering 80% of common admin tasks.

- Content actions: `edit_post`, `bulk_edit_posts`, `manage_media`, `create_menu`, `manage_categories`, `generate_content`, `translate_content`
- Settings actions: `manage_permalinks`, `configure_reading`, `manage_widgets`, `customize_theme`
- Plugin actions: `install_plugin`, `update_plugins`, `deactivate_plugin`
- User actions: `create_user`, `manage_roles`, `bulk_user_actions`
- Multi-step workflow engine (decompose complex requests into action sequences)
- Plan-Confirm-Execute UI flow
- Conversation memory (session-level context retention)
- Smart suggestions (contextual command bar hints)

**Deliverable**: 20+ actions covering content, settings, plugins, users. Multi-step workflows work.

### Phase 3: WooCommerce + SEO (Months 5-6)

**Goal**: High-value integrations that justify Pro pricing.

- WooCommerce actions: `create_product`, `manage_orders`, `create_coupon`, `update_shipping`, `sales_report`, `manage_inventory`
- SEO actions: `seo_audit`, `generate_meta`, `analyze_performance`, `manage_redirects`
- Maintenance actions: `database_cleanup`, `backup_check`
- Intelligent model routing (auto-select model based on task complexity)
- Knowledge graph (site understanding that improves over time)
- Action history with search and filtering
- Voice input (Web Speech API integration)

**Deliverable**: WooCommerce store management via natural language. SEO audit and auto-fix. Full 30+ action library.

### Phase 4: Polish + Launch (Months 7-8)

**Goal**: Production hardening, onboarding, and public launch.

- Onboarding wizard ("What would you like WP Agent to help with?")
- Usage analytics dashboard (commands/day, tokens used, popular actions)
- White-label mode for Agency tier
- Comprehensive error handling and edge case coverage
- Performance optimization (context caching, response streaming tuning)
- Security audit (third-party penetration testing)
- Documentation site (docs, tutorials, video walkthroughs)
- Beta program with 100-200 BSF existing customers
- WordPress.org free version submission
- SureCart integration for license management
- Launch campaign (BSF email list, AppSumo consideration, ProductHunt)

**Deliverable**: Public launch on WordPress.org (free) + BSF store (Pro/Agency).

### Post-Launch (V1.1+)

- **Extensibility API**: `wp_agent_register_action()` hook for third-party plugins to add agent capabilities
- **Marketplace**: Community-built action packs (e.g., "WP Agent for LearnDash", "WP Agent for MemberPress")
- **Scheduled agents**: "Every Monday at 9am, generate a weekly content calendar"
- **Multi-site support**: Manage multiple sites from one command bar
- **Astra deep integration**: "Change my header layout to sticky transparent"
- **Starter Templates integration**: "Apply the SaaS Landing Page template to my homepage"

---

## 12. Tech Stack

| Layer | Technology | Rationale |
|-------|-----------|-----------|
| **Backend** | PHP 7.4+, WordPress 5.6+ | WordPress standard. Maximum host compatibility. |
| **Frontend** | React 18, @wordpress/scripts | WordPress ecosystem standard. Gutenberg-compatible build pipeline. |
| **UI Components** | @bsf/force-ui, Tailwind CSS 3 | BSF shared component library. Consistent with Astra/SureCart. |
| **Icons** | lucide-react | Lightweight, tree-shakeable. BSF standard. |
| **State** | @wordpress/data (createReduxStore) | WordPress-native state management. |
| **AI Communication** | OpenRouter API (OpenAI-compatible) | Multi-model, single API. Function calling support. |
| **Streaming** | Server-Sent Events via admin-ajax | WP REST doesn't support SSE. Admin-ajax allows progressive flush. |
| **HTTP (PHP to AI)** | cURL with CURLOPT_WRITEFUNCTION | Required for streaming. wp_remote_post for non-streaming fallback. |
| **HTTP (JS to PHP)** | @wordpress/api-fetch + fetch() for SSE | WordPress-native for REST, native fetch for SSE streaming. |
| **Database** | WordPress custom tables | `wp_agent_checkpoints`, `wp_agent_history`, `wp_agent_knowledge` |
| **Encryption** | AES-256-CBC (openssl) | API key encryption at rest. |
| **Licensing** | SureCart | BSF's licensing infrastructure. |
| **Proxy** | Cloudflare Workers or AWS Lambda | Stateless, edge-deployed, sub-10ms overhead. |
| **Testing** | PHPUnit, Jest, Playwright | PHP unit tests, JS unit tests, E2E tests. |
| **CI/CD** | GitHub Actions | Lint, test, build, deploy on every PR. |

### Directory Structure

```
wp-agent/
|-- wp-agent.php                    # Plugin entry point
|-- loader.php                      # PSR-4 autoloader
|-- composer.json                   # PHP dependencies
|-- package.json                    # JS dependencies
|
|-- core/
|   |-- agent/
|   |   |-- orchestrator.php        # Main agent brain
|   |   |-- context-collector.php   # Site context gathering
|   |   |-- checkpoint.php          # Undo/rollback system
|   |   |-- knowledge.php           # Site knowledge graph
|   |   +-- planner.php             # Multi-step decomposition
|   |
|   |-- actions/
|   |   |-- action-interface.php    # Agent_Action interface
|   |   |-- action-registry.php     # Action registration + lookup
|   |   |-- content/
|   |   |   |-- create-post.php
|   |   |   |-- edit-post.php
|   |   |   |-- bulk-edit-posts.php
|   |   |   |-- manage-media.php
|   |   |   +-- generate-content.php
|   |   |-- settings/
|   |   |   |-- update-settings.php
|   |   |   |-- manage-permalinks.php
|   |   |   +-- customize-theme.php
|   |   |-- plugins/
|   |   |   |-- install-plugin.php
|   |   |   +-- update-plugins.php
|   |   |-- users/
|   |   |   |-- create-user.php
|   |   |   +-- manage-roles.php
|   |   |-- woocommerce/
|   |   |   |-- create-product.php
|   |   |   |-- manage-orders.php
|   |   |   +-- create-coupon.php
|   |   +-- seo/
|   |       |-- seo-audit.php
|   |       +-- generate-meta.php
|   |
|   |-- ai/
|   |   |-- router.php              # Model selection logic
|   |   |-- openrouter-client.php   # OpenRouter API client
|   |   |-- prompt-builder.php      # System prompt construction
|   |   +-- rate-limiter.php        # Token/request budgets
|   |
|   |-- rest/
|   |   |-- agent-controller.php    # POST /agent/chat
|   |   |-- stream-controller.php   # SSE streaming endpoint
|   |   |-- history-controller.php  # GET /agent/history
|   |   +-- settings-controller.php # Agent settings CRUD
|   |
|   +-- db/
|       |-- schema.php              # Table creation
|       +-- migrations/             # Schema migrations
|
|-- admin/
|   |-- menu.php                    # Admin menu registration
|   |-- settings.php                # Settings page
|   +-- ajax-stream.php             # Admin-ajax SSE handler
|
|-- src/                            # React source
|   |-- command-bar/
|   |   |-- index.js                # Entry point + registration
|   |   |-- CommandBar.js           # Main floating component
|   |   |-- InputArea.js            # Text input + voice
|   |   |-- ResponseArea.js         # Streaming response display
|   |   |-- PlanViewer.js           # Action plan UI
|   |   |-- HistoryPanel.js         # Past actions + undo
|   |   |-- Suggestions.js          # Smart suggestions
|   |   |-- store.js                # Redux store
|   |   +-- use-streaming.js        # SSE hook
|   |
|   +-- settings/
|       |-- SettingsPage.js         # Admin settings SPA
|       |-- UsageDashboard.js       # Token/request charts
|       +-- RolePermissions.js      # Per-role access config
|
|-- assets/
|   +-- build/                      # Compiled JS/CSS
|
|-- tests/
|   |-- php/                        # PHPUnit tests
|   +-- js/                         # Jest tests
|
+-- languages/                      # i18n
```

---

## 13. Success Metrics

### North Star Metric

**Daily Active Agents (DAA)**: Number of unique sites that send at least one command per day.

### Engagement Metrics

| Metric | Target (6mo post-launch) |
|--------|-------------------------|
| DAA | 5,000 |
| Commands per active user per day | 8-12 |
| Plan approval rate | >85% (users approve the agent's plan) |
| Undo rate | <5% (agent rarely does the wrong thing) |
| Session length | >3 commands per session |

### Business Metrics

| Metric | Target (Year 1) |
|--------|-----------------|
| WordPress.org installs | 50,000+ |
| Free to Pro conversion | 8-12% |
| Pro annual retention | >70% |
| Revenue | $500K-$1M |
| NPS | >50 |

### Quality Metrics

| Metric | Target |
|--------|--------|
| Action success rate | >95% |
| Average response time (simple query) | <2s |
| Average response time (complex plan) | <5s |
| Error rate | <2% |
| Support tickets per 1000 users | <10/month |

---

## 14. Risk Analysis

| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|-----------|
| **AI costs exceed revenue** | Medium | High | Intelligent model routing (use cheap models for simple tasks). Monitor cost/user closely. Adjust free tier limits. Model costs dropping ~50%/yr. |
| **OpenRouter outage** | Low | High | Secondary fallback key. Local queue that retries. Graceful degradation (show cached suggestions, disable generation). |
| **Prompt injection attacks** | Medium | Medium | Input sanitization, content delimiters, capability enforcement, action confirmation for destructive ops. Security audit before launch. |
| **WordPress.org rejection** | Low | Medium | Follow all plugin guidelines. No phone-home beyond proxy. Free tier is genuinely useful. No dark patterns. |
| **Competitor launches similar** | Medium | Medium | BSF distribution advantage (5M+ reach). Ship fast. Extensibility API creates ecosystem moat. |
| **Users don't trust AI actions** | Medium | High | Plan-Confirm pattern builds trust gradually. Start with low-risk actions. Checkpoint/undo as safety net. Transparency. |
| **Scope creep delays launch** | High | High | Strict phase gates. Phase 1-2 is the real MVP (content + settings). WooCommerce/SEO are nice-to-have for V1. Ship early, iterate. |

---

## 15. BSF Ecosystem Integration

### Cross-Product Synergies

| BSF Product | Integration |
|-------------|------------|
| **Astra Theme** (5M+ installs) | Deep customizer integration. "Change header to sticky" works with Astra-specific options. Cross-promote WP Agent in Astra dashboard. |
| **Starter Templates** (1M+ installs) | "Apply the SaaS template" triggers Starter Templates import. Agent can customize template post-import. |
| **SureCart** | License management for WP Agent Pro. Agent can manage SureCart products/orders if installed. |
| **Spectra Blocks** | Agent-aware block creation. "Add a pricing table" uses Spectra block if available. |
| **Force UI** | Shared component library for consistent admin UI across BSF products. |
| **Powerful Docs** | Agent can create and manage documentation. "Set up a knowledge base with 5 categories." |

### Distribution Advantage

- **Built-in audience**: BSF email list (2M+), existing customer base, plugin dashboards
- **Cross-sell surface**: Every BSF plugin dashboard can show "Try WP Agent" banner
- **Trusted brand**: BSF has an established reputation in the WordPress ecosystem
- **Support infrastructure**: Existing support team, documentation processes, community

---

## Next Steps

1. **Stakeholder review**: Share with CEO and leadership for alignment
2. **Technical feasibility**: Build a proof-of-concept with 3 actions (create_post, update_settings, site_health) + command bar + OpenRouter integration (2 weeks)
3. **Cost modeling**: Simulate token costs with realistic usage patterns against OpenRouter pricing
4. **User research**: Interview 10-15 BSF customers about willingness to use an AI admin assistant
5. **Competitive deep-dive**: Test Jeeves.ai and similar products to identify UX patterns that work
6. **Architecture review**: Have senior engineers review the orchestrator + action registry design

Once approved, proceed to Phase 1 implementation.
