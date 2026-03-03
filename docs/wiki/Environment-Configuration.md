# Environment Configuration

## Development Setup

### Prerequisites

- Node.js 18+
- PHP 7.4+
- Composer 2.x
- A local WordPress installation (Local, wp-env, etc.)

### Install Dependencies

```bash
npm install --legacy-peer-deps   # --legacy-peer-deps required for Force UI
composer install
```

### Build Commands

| Command | Purpose |
|---------|---------|
| `npm start` | Dev build with file watcher |
| `npm run build` | Production build (minified) |
| `npm run lint-js` | ESLint check |
| `npm run lint-js:fix` | ESLint auto-fix |
| `npm run lint-css` | Stylelint check |
| `npm run pretty:fix` | Prettier auto-fix |
| `composer lint` | PHPCS check (WordPress standard) |
| `composer format` | PHPCBF auto-fix |
| `composer test` | PHPUnit tests |
| `composer phpstan` | PHPStan static analysis |

## Plugin Options (wp_options)

### API Keys (Encrypted)

All API keys are stored encrypted using AES-256-CBC. The encryption key is derived from `AUTH_KEY` and `AUTH_SALT` in `wp-config.php`.

| Option Key | Description |
|------------|-------------|
| `jarvis_ai_openrouter_api_key` | OpenRouter API key (encrypted) |
| `jarvis_ai_anthropic_api_key` | Anthropic API key (encrypted) |
| `jarvis_ai_openai_api_key` | OpenAI API key (encrypted) |
| `jarvis_ai_google_api_key` | Google API key (encrypted) |
| `jarvis_ai_tavily_api_key` | Tavily search API key (encrypted) |

### General Settings

| Option Key | Default | Description |
|------------|---------|-------------|
| `jarvis_ai_default_model` | `openai/gpt-4o-mini` | Default AI model ID |
| `jarvis_ai_ai_backend` | `openrouter` | Backend: `openrouter` or `providers` |
| `jarvis_ai_allowed_roles` | `['administrator']` | Roles allowed to use JARVIS |
| `jarvis_ai_preferred_provider` | `['anthropic','openai','google']` | Provider priority for direct mode |
| `jarvis_ai_brand` | `{}` | Brand presets (name, tagline, colors, tone, font) |

### Rate Limiting

| Option Key | Default | Description |
|------------|---------|-------------|
| `jarvis_ai_rate_limit` | `30` | Max requests per user per minute |
| `jarvis_ai_daily_limit` | `500` | Max requests per user per day |

### Internal

| Option Key | Description |
|------------|-------------|
| `jarvis_ai_db_version` | Current database schema version |
| `jarvis_ai_activated_at` | Timestamp of first activation |

## Constants (jarvis-ai.php)

```php
JARVIS_AI_FILE   // Full path to main plugin file
JARVIS_AI_BASE   // Plugin basename (jarvis-ai/jarvis-ai.php)
JARVIS_AI_DIR    // Plugin directory path (with trailing slash)
JARVIS_AI_URL    // Plugin URL (with trailing slash)
JARVIS_AI_VER    // Plugin version string
JARVIS_AI_DB_VER // Database version string
```

## See Also

- [Getting Started](Getting-Started) -- Installation walkthrough
- [Security Model](Security-Model) -- How API keys are encrypted
- [Testing Guide](Testing-Guide) -- Running tests locally
