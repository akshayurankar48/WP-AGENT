# Getting Started

## Requirements

| Requirement | Minimum |
|-------------|---------|
| WordPress | 6.4+ |
| PHP | 7.4+ |
| Node.js | 18+ (development only) |
| AI Provider API Key | OpenRouter, Anthropic, OpenAI, or Google |

## Installation

### From ZIP

1. Download or build the `jarvis-ai.zip` distributable
2. Go to **Plugins > Add New > Upload Plugin** in WordPress admin
3. Upload the ZIP and click **Install Now**
4. Click **Activate**

### From Source

1. Clone the repository into `wp-content/plugins/jarvis-ai`
2. Run `npm install --legacy-peer-deps` and `composer install`
3. Run `npm run build` to compile frontend assets
4. Activate via **Plugins** menu

## First Setup

### 1. Configure API Key

1. Navigate to **JARVIS AI > Settings** in the admin menu
2. Choose your AI backend:
   - **OpenRouter** (default) -- single key for 100+ models
   - **Direct Providers** -- individual keys for Anthropic, OpenAI, or Google
3. Enter your API key and save
4. Keys are encrypted at rest using AES-256-CBC

### 2. Select a Default Model

Choose a default model from the dropdown. Models are organized into three tiers:

- **Fast** -- GPT-4o-mini, Gemini Flash, Haiku (quick tasks)
- **Balanced** -- GPT-4o, Claude Sonnet (default)
- **Powerful** -- Claude Opus, GPT-4 Turbo (complex tasks)

### 3. Set Allowed Roles

By default, only administrators can use JARVIS. Add additional roles (Editor, Author) under **Settings > Allowed Roles**.

### 4. Open the Editor Sidebar

1. Open any post or page in the block editor
2. Click the **JARVIS** icon in the top toolbar (or press **Cmd+J** / **Ctrl+J**)
3. The chat sidebar opens on the right

### 5. Start Chatting

Type a natural language command:

- *"Create a landing page for a coffee shop"*
- *"Install and activate Yoast SEO"*
- *"Add a hero section with a CTA button"*
- *"List all draft posts from this month"*

JARVIS will plan the task, ask for confirmation on destructive actions, then execute autonomously.

## Next Steps

- [Architecture Overview](Architecture-Overview) -- Understand how the plugin works
- [Action Catalog](Action-Catalog) -- See all 77 available actions
- [AI Providers](AI-Providers) -- Configure multi-provider setup
- [Troubleshooting FAQ](Troubleshooting-FAQ) -- Common issues
